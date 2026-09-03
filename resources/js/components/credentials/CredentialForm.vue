<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { canManageWith, VISIBILITY_OPTIONS } from '@/lib/credentials';
import { generatePassword } from '@/lib/passwordGenerator';
import type { CredentialDetail, CredentialFieldType, CredentialGroupRef, CredentialVisibility } from '@/types/vault';
import { useForm } from '@inertiajs/vue3';
import { Dices, Eye, EyeOff, Globe, Lock, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

const CUSTOM_FIELD_TYPE_OPTIONS: { value: CredentialFieldType; label: string }[] = [
    { value: 'text', label: 'Текст' },
    { value: 'hidden', label: 'Приховане' },
    { value: 'boolean', label: 'Прапорець' },
];

const props = defineProps<{
    credential?: CredentialDetail;
    manageableGroups: CredentialGroupRef[];
}>();

const isEdit = computed(() => Boolean(props.credential));
// The group set can only be changed with 'manage' on the credential (not just 'edit') — see
// CredentialController::update(). On create it's always editable (you choose where it goes).
const canEditGroups = computed(() => !isEdit.value || canManageWith(props.credential?.access_level));

const form = useForm({
    visibility: (props.credential?.visibility ?? 'group') as CredentialVisibility,
    name: props.credential?.name ?? '',
    login: props.credential?.login ?? '',
    password: props.credential?.password ?? '',
    totp_secret: props.credential?.totp_secret ?? '',
    url: props.credential?.url ?? '',
    notes: props.credential?.notes ?? '',
    custom_fields: props.credential?.custom_fields.map((f) => ({ ...f })) ?? [],
    group_ids: props.credential?.groups.map((g) => g.id) ?? ([] as number[]),
});

const groupsRequired = computed(() => form.visibility !== 'public');

const showPassword = ref(false);

function generate() {
    form.password = generatePassword();
    showPassword.value = true;
}

function addField() {
    form.custom_fields = [...form.custom_fields, { label: '', value: '', type: 'text' as CredentialFieldType }];
}

function removeField(index: number) {
    form.custom_fields = form.custom_fields.filter((_, i) => i !== index);
}

function setFieldType(index: number, type: CredentialFieldType) {
    const field = form.custom_fields[index];
    field.type = type;
    field.value = type === 'boolean' ? false : '';
}

function toggleGroup(id: number) {
    if (form.group_ids.includes(id)) {
        form.group_ids = form.group_ids.filter((g) => g !== id);
    } else {
        form.group_ids = [...form.group_ids, id];
    }
}

function submit() {
    if (isEdit.value && props.credential) {
        // If groups aren't editable (no 'manage') — don't send group_ids at all, so the
        // backend right-check (which only fires when the field is present) isn't triggered.
        form.transform((data) => (canEditGroups.value ? data : { ...data, group_ids: undefined })).put(
            route('passwords.update', props.credential!.id),
        );
    } else {
        form.post(route('passwords.store'));
    }
}
</script>

<template>
    <form class="flex flex-1 flex-col gap-4 overflow-hidden" @submit.prevent="submit">
        <div class="flex-1 overflow-auto">
            <div class="flex w-full flex-col gap-4 p-6">
                <div class="rounded-lg border bg-card p-5">
                    <h2 class="mb-4 text-sm font-semibold">Основна інформація</h2>

                    <div class="grid grid-cols-2 items-start gap-4 lg:grid-cols-4">
                        <div class="col-span-2 grid gap-2">
                            <Label for="name">Назва <span class="text-destructive">*</span></Label>
                            <Input id="name" v-model="form.name" placeholder="напр. Хостинг — cPanel" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="login">Логін</Label>
                            <Input id="login" v-model="form.login" placeholder="напр. admin@company.ua" />
                            <InputError :message="form.errors.login" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="url">Посилання</Label>
                            <Input id="url" v-model="form.url" placeholder="https://…" />
                            <InputError :message="form.errors.url" />
                        </div>

                        <div class="col-span-2 grid gap-2">
                            <Label for="password">Пароль</Label>
                            <div class="flex gap-1.5">
                                <div class="relative flex-1">
                                    <Input
                                        id="password"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        class="pr-9 font-mono"
                                        autocomplete="off"
                                    />
                                    <button
                                        type="button"
                                        class="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                        @click="showPassword = !showPassword"
                                    >
                                        <EyeOff v-if="showPassword" class="size-4" />
                                        <Eye v-else class="size-4" />
                                    </button>
                                </div>
                                <Button type="button" variant="outline" size="icon" title="Згенерувати пароль" @click="generate">
                                    <Dices class="size-4" />
                                </Button>
                            </div>
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="col-span-2 grid gap-2">
                            <Label for="totp_secret">TOTP секрет (2FA)</Label>
                            <Input
                                id="totp_secret"
                                v-model="form.totp_secret"
                                class="font-mono"
                                placeholder="напр. JBSWY3DPEHPK3PXP"
                                autocomplete="off"
                            />
                            <p class="text-xs text-muted-foreground">Опційно — секретний ключ (не 6-значний код) із налаштування 2FA сервісу.</p>
                            <InputError :message="form.errors.totp_secret" />
                        </div>

                        <div class="col-span-full grid gap-2">
                            <Label for="notes">Нотатки</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                placeholder="Додаткова інформація — коди відновлення, підказки тощо…"
                            />
                            <InputError :message="form.errors.notes" />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border bg-card p-5">
                    <h2 class="mb-1 text-sm font-semibold">Видимість <span class="text-destructive">*</span></h2>
                    <p class="mb-4 text-xs text-muted-foreground">
                        «Публічно» — пароль бачать усі співробітники (лише перегляд); редагування все одно потребує групи.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in VISIBILITY_OPTIONS"
                            :key="option.value"
                            type="button"
                            class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors"
                            :class="
                                form.visibility === option.value
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-input text-muted-foreground hover:border-primary/50'
                            "
                            @click="form.visibility = option.value"
                        >
                            <component :is="option.value === 'public' ? Globe : Lock" class="size-4" />
                            <span>
                                <span class="font-medium">{{ option.label }}</span>
                                <span class="ml-1 text-xs opacity-70">{{ option.hint }}</span>
                            </span>
                        </button>
                    </div>
                    <InputError :message="form.errors.visibility" class="mt-2" />
                </div>

                <div class="rounded-lg border bg-card p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-semibold">Додаткові поля</h2>
                        <Button type="button" variant="outline" size="sm" class="gap-1.5" @click="addField">
                            <Plus class="size-3.5" />
                            Додати поле
                        </Button>
                    </div>

                    <p v-if="form.custom_fields.length === 0" class="text-sm text-muted-foreground">Немає додаткових полів.</p>

                    <div v-else class="flex flex-col gap-3">
                        <div v-for="(field, index) in form.custom_fields" :key="index" class="flex items-end gap-2">
                            <div class="grid flex-1 gap-1.5">
                                <Label :for="`field-label-${index}`" class="text-xs">Назва</Label>
                                <Input :id="`field-label-${index}`" v-model="field.label" placeholder="напр. Секретне питання" />
                            </div>

                            <div class="grid flex-1 gap-1.5">
                                <Label class="text-xs">Значення</Label>
                                <label v-if="field.type === 'boolean'" class="flex h-10 items-center gap-2 rounded-md border border-input px-3">
                                    <Checkbox :model-value="field.value === true" @update:model-value="(v) => (field.value = v)" />
                                    <span class="text-sm text-muted-foreground">{{ field.value ? 'Так' : 'Ні' }}</span>
                                </label>
                                <Input
                                    v-else
                                    v-model="field.value as string"
                                    :type="field.type === 'hidden' ? 'password' : 'text'"
                                    autocomplete="off"
                                />
                            </div>

                            <div class="grid gap-1.5">
                                <Label class="text-xs">Тип</Label>
                                <Select :model-value="field.type" @update:model-value="(v) => setFieldType(index, v as CredentialFieldType)">
                                    <SelectTrigger class="w-36"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="option in CUSTOM_FIELD_TYPE_OPTIONS" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <button type="button" class="mb-2 p-2 text-muted-foreground hover:text-destructive" @click="removeField(index)">
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </div>
                    <InputError :message="form.errors.custom_fields" class="mt-2" />
                </div>

                <div class="rounded-lg border bg-card p-5">
                    <h2 class="mb-1 text-sm font-semibold">
                        Групи <span v-if="groupsRequired" class="text-destructive">*</span>
                    </h2>
                    <p class="mb-4 text-xs text-muted-foreground">
                        <template v-if="groupsRequired">Доступ до пароля визначається групами — оберіть хоча б одну.</template>
                        <template v-else>Для публічного пароля групи необов'язкові — додайте, щоб надати комусь право редагування.</template>
                        <span v-if="!canEditGroups"> У вас немає права «Керування» цим паролем, щоб змінити склад груп.</span>
                    </p>

                    <div v-if="manageableGroups.length === 0 && canEditGroups" class="text-sm text-muted-foreground">
                        У вас немає груп із правом «Керування». Спершу
                        <a :href="route('password-groups.create')" class="underline">створіть групу</a>.
                    </div>

                    <div v-else class="flex flex-wrap gap-2">
                        <template v-if="canEditGroups">
                            <button
                                v-for="group in manageableGroups"
                                :key="group.id"
                                type="button"
                                class="rounded-md border px-3 py-1.5 text-sm transition-colors"
                                :class="
                                    form.group_ids.includes(group.id)
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-input text-muted-foreground hover:border-primary/50'
                                "
                                @click="toggleGroup(group.id)"
                            >
                                {{ group.name }}
                            </button>
                        </template>
                        <template v-else>
                            <span
                                v-for="group in credential?.groups"
                                :key="group.id"
                                class="rounded-md border border-input px-3 py-1.5 text-sm text-muted-foreground"
                            >
                                {{ group.name }}
                            </span>
                        </template>
                    </div>
                    <InputError :message="form.errors.group_ids" class="mt-2" />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-sidebar-border/70 px-6 py-3">
            <Button type="button" variant="outline" as-child>
                <a :href="isEdit ? route('passwords.show', credential!.id) : route('passwords.index')">Скасувати</a>
            </Button>
            <Button type="submit" :disabled="form.processing">Зберегти пароль</Button>
        </div>
    </form>
</template>
