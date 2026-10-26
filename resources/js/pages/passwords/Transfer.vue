<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Select, SelectClearItem, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ClipboardPaste, Download, FileSpreadsheet, ShieldAlert, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';

type Group = { id: number; name: string };

const props = defineProps<{
    manageableGroups: Group[];
    visibleGroups: Group[];
    columns: Record<string, string>;
    maxRows: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Паролі', href: route('passwords.index') },
    { title: 'Імпорт / експорт', href: route('passwords.transfer') },
];

/** Джерело даних: файл (.xlsx / .csv) або текст, скопійований прямо з Excel (клітинки через Tab). */
const source = ref<'file' | 'text'>('file');
const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm<{ file: File | null; text: string; default_group_id: number | null; skip_duplicates: boolean }>({
    file: null,
    text: '',
    default_group_id: props.manageableGroups.length === 1 ? props.manageableGroups[0].id : null,
    skip_duplicates: true,
});

const rowErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key.startsWith('row_'))
        .map(([, message]) => message),
);

const canSubmit = computed(() => (source.value === 'file' ? form.file !== null : form.text.trim() !== ''));

function pickFile(event: Event) {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function submit() {
    form.transform((data) => ({
        ...data,
        file: source.value === 'file' ? data.file : null,
        text: source.value === 'text' ? data.text : '',
    })).post(route('passwords.transfer.import'), {
        forceFormData: source.value === 'file',
        preserveScroll: true,
        // Вставлені дані з паролями не тримаємо у формі довше, ніж треба.
        onSuccess: () => {
            form.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

const exportGroupId = ref<number | null>(null);
const exportUrl = computed(() => route('passwords.transfer.export', exportGroupId.value ? { group_id: exportGroupId.value } : {}));
const columnList = computed(() => Object.values(props.columns).join(' · '));
</script>

<template>
    <Head title="Імпорт / експорт паролів" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
            <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-3">
                <!-- Імпорт -->
                <div class="rounded-lg border bg-card p-5 xl:col-span-2">
                    <h2 class="mb-1 flex items-center gap-2 text-sm font-semibold"><Upload class="size-4" /> Імпорт</h2>
                    <p class="mb-4 text-xs text-muted-foreground">
                        Колонки: {{ columnList }}. Перший рядок — заголовки. Обов'язкова лише «Назва». Кілька груп в одній клітинці — через
                        кому. Максимум {{ maxRows }} рядків за раз. Якщо хоч один рядок містить помилку, не імпортується нічого.
                    </p>

                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <Button as-child variant="outline" size="sm" class="gap-1.5">
                            <a :href="route('passwords.transfer.template')"><FileSpreadsheet class="size-4" /> Завантажити шаблон</a>
                        </Button>
                        <div class="ml-auto inline-flex rounded-md border p-0.5">
                            <Button type="button" size="sm" :variant="source === 'file' ? 'secondary' : 'ghost'" class="h-7 gap-1.5" @click="source = 'file'">
                                <Upload class="size-3.5" /> Файл
                            </Button>
                            <Button type="button" size="sm" :variant="source === 'text' ? 'secondary' : 'ghost'" class="h-7 gap-1.5" @click="source = 'text'">
                                <ClipboardPaste class="size-3.5" /> Вставити з Excel
                            </Button>
                        </div>
                    </div>

                    <form class="grid gap-4" @submit.prevent="submit">
                        <div v-if="source === 'file'" class="grid gap-2">
                            <Label for="file">Файл Excel (.xlsx) або CSV</Label>
                            <input
                                id="file"
                                ref="fileInput"
                                type="file"
                                accept=".xlsx,.csv,.txt"
                                class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-1.5 file:text-sm file:text-secondary-foreground"
                                @change="pickFile"
                            />
                            <p class="text-xs text-muted-foreground">З .xlsx читається лише перший аркуш.</p>
                            <InputError :message="form.errors.file" />
                        </div>

                        <div v-else class="grid gap-2">
                            <Label for="text">Дані</Label>
                            <textarea
                                id="text"
                                v-model="form.text"
                                rows="10"
                                spellcheck="false"
                                autocomplete="off"
                                placeholder="Виділіть у Excel таблицю разом із рядком заголовків, скопіюйте (Ctrl+C) і вставте сюди (Ctrl+V)"
                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs placeholder:font-sans placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            />
                            <InputError :message="form.errors.text" />
                        </div>

                        <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label>Група для рядків без колонки «Групи»</Label>
                                <Select
                                    :model-value="form.default_group_id ? String(form.default_group_id) : undefined"
                                    @update:model-value="(v) => (form.default_group_id = v ? Number(v) : null)"
                                >
                                    <SelectTrigger><SelectValue placeholder="Не обрано" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectClearItem />
                                        <SelectItem v-for="group in manageableGroups" :key="group.id" :value="String(group.id)">{{ group.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="!manageableGroups.length" class="text-xs text-destructive">
                                    У вас немає групи з правом «Керування». Спершу створіть групу паролів.
                                </p>
                                <InputError :message="form.errors.default_group_id" />
                            </div>

                            <label class="flex items-start gap-2 pt-7 text-sm">
                                <Checkbox v-model="form.skip_duplicates" class="mt-0.5" />
                                <span>
                                    Пропускати дублікати
                                    <span class="block text-xs text-muted-foreground">Якщо пароль із такою самою назвою й логіном у вас уже є</span>
                                </span>
                            </label>
                        </div>

                        <div v-if="rowErrors.length" class="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm">
                            <p class="mb-1 font-medium text-destructive">Нічого не імпортовано. Виправте рядки й спробуйте ще раз:</p>
                            <ul class="list-disc space-y-0.5 pl-5 text-xs">
                                <li v-for="message in rowErrors" :key="message">{{ message }}</li>
                            </ul>
                        </div>

                        <div class="flex justify-end">
                            <Button type="submit" :disabled="form.processing || !canSubmit || !manageableGroups.length" class="gap-1.5">
                                <Upload class="size-4" /> Імпортувати
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Експорт -->
                <div class="rounded-lg border bg-card p-5">
                    <h2 class="mb-1 flex items-center gap-2 text-sm font-semibold"><Download class="size-4" /> Експорт</h2>
                    <p class="mb-4 text-xs text-muted-foreground">
                        Файл CSV із тими самими колонками, що й шаблон: його можна відкрити в Excel або імпортувати назад. Потрапляють лише
                        паролі, які ви бачите.
                    </p>

                    <div class="grid gap-2">
                        <Label>Що експортувати</Label>
                        <Select
                            :model-value="exportGroupId ? String(exportGroupId) : undefined"
                            @update:model-value="(v) => (exportGroupId = v ? Number(v) : null)"
                        >
                            <SelectTrigger><SelectValue placeholder="Усі доступні мені" /></SelectTrigger>
                            <SelectContent>
                                <SelectClearItem />
                                <SelectItem v-for="group in visibleGroups" :key="group.id" :value="String(group.id)">Група «{{ group.name }}»</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="mt-4 flex gap-2 rounded-md bg-muted/60 p-3 text-xs text-muted-foreground">
                        <ShieldAlert class="size-4 shrink-0 text-warning-foreground" />
                        <span>
                            У файлі паролі не зашифровані. Не надсилайте його поштою чи в месенджерах і видаліть одразу після використання,
                            разом із копією в «Завантаженнях» і в кошику.
                        </span>
                    </div>

                    <Button as-child class="mt-4 w-full gap-1.5">
                        <a :href="exportUrl"><Download class="size-4" /> Завантажити CSV</a>
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
