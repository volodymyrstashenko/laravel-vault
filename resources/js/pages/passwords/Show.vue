<script setup lang="ts">
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import CredentialIcon from '@/components/credentials/CredentialIcon.vue';
import TotpCode from '@/components/credentials/TotpCode.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { accessLevelBadgeVariant, accessLevelLabel, canEditWith, canManageWith } from '@/lib/credentials';
import type { BreadcrumbItem } from '@/types';
import type { CredentialDetail } from '@/types/vault';
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, Copy, Download, Eye, EyeOff, Globe, Lock, Paperclip, Plus, ShieldCheck, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    credential: CredentialDetail;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Паролі', href: route('passwords.index') },
    { title: props.credential.name, href: route('passwords.show', props.credential.id) },
]);

const showPassword = ref(false);
const copiedField = ref<string | null>(null);

function copy(value: string | null, field: string) {
    if (!value) return;
    navigator.clipboard.writeText(value);
    copiedField.value = field;
    setTimeout(() => (copiedField.value = null), 1500);
}

const deleting = ref(false);

function confirmDelete() {
    deleting.value = true;
    router.delete(route('passwords.destroy', props.credential.id), { onFinish: () => (deleting.value = false) });
}

const isDeleteOpen = ref(false);

const revealedFields = ref<Set<number>>(new Set());

function toggleField(index: number) {
    if (revealedFields.value.has(index)) {
        revealedFields.value.delete(index);
    } else {
        revealedFields.value.add(index);
    }
}

const fileInput = ref<HTMLInputElement | null>(null);

function triggerFileInput() {
    fileInput.value?.click();
}

function handleFileChange(e: Event) {
    const files = (e.target as HTMLInputElement).files;
    if (!files || files.length === 0) return;
    router.post(
        route('passwords.attachments.store', props.credential.id),
        { files: Array.from(files) },
        { forceFormData: true, preserveScroll: true },
    );
}

const deletingAttachmentId = ref<number | null>(null);

function confirmDeleteAttachment() {
    if (!deletingAttachmentId.value) return;
    router.delete(route('passwords.attachments.destroy', [props.credential.id, deletingAttachmentId.value]), {
        preserveScroll: true,
        onFinish: () => (deletingAttachmentId.value = null),
    });
}

function formatSize(bytes: number): string {
    return bytes < 1024 * 1024 ? `${Math.ceil(bytes / 1024)} КБ` : `${(bytes / (1024 * 1024)).toFixed(1)} МБ`;
}
</script>

<template>
    <Head :title="credential.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-auto p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <CredentialIcon :icon-url="credential.icon_url" size="md" />
                    <div>
                        <h1 class="text-lg font-semibold">{{ credential.name }}</h1>
                        <p class="text-sm text-muted-foreground">Створив {{ credential.created_by?.name }} · {{ credential.created_at }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <Badge variant="outline" class="gap-1">
                        <component :is="credential.visibility === 'public' ? Globe : Lock" class="size-3" />
                        {{ credential.visibility === 'public' ? 'Публічно' : 'Група' }}
                    </Badge>
                    <Badge :variant="accessLevelBadgeVariant(credential.access_level)">{{ accessLevelLabel(credential.access_level) }}</Badge>
                    <Button v-if="canEditWith(credential.access_level)" as-child variant="outline" size="sm">
                        <Link :href="route('passwords.edit', credential.id)">Редагувати</Link>
                    </Button>
                    <Button v-if="canManageWith(credential.access_level)" variant="destructive" size="sm" @click="isDeleteOpen = true">
                        Видалити
                    </Button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="flex flex-col gap-4 lg:col-span-2">
                    <div class="rounded-lg border bg-card p-5">
                        <h2 class="mb-4 text-sm font-semibold">Облікові дані</h2>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label class="text-xs text-muted-foreground">Логін</Label>
                                <div v-if="credential.login" class="flex items-center gap-2">
                                    <span class="font-mono text-sm">{{ credential.login }}</span>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="copy(credential.login, 'login')"
                                    >
                                        <Check v-if="copiedField === 'login'" class="size-3.5 text-success-foreground" />
                                        <Copy v-else class="size-3.5" />
                                    </button>
                                </div>
                                <span v-else class="text-sm text-muted-foreground">—</span>
                            </div>

                            <div class="grid gap-1.5">
                                <Label class="text-xs text-muted-foreground">Пароль</Label>
                                <div v-if="credential.password" class="flex items-center gap-2">
                                    <span class="font-mono text-sm">{{ showPassword ? credential.password : '••••••••••••' }}</span>
                                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="showPassword = !showPassword">
                                        <EyeOff v-if="showPassword" class="size-3.5" />
                                        <Eye v-else class="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="copy(credential.password, 'password')"
                                    >
                                        <Check v-if="copiedField === 'password'" class="size-3.5 text-success-foreground" />
                                        <Copy v-else class="size-3.5" />
                                    </button>
                                </div>
                                <span v-else class="text-sm text-muted-foreground">—</span>
                            </div>

                            <div v-if="credential.url" class="grid gap-1.5 sm:col-span-2">
                                <Label class="text-xs text-muted-foreground">Посилання</Label>
                                <a :href="credential.url" target="_blank" rel="noopener" class="truncate text-sm text-primary underline">{{
                                    credential.url
                                }}</a>
                            </div>
                        </div>
                    </div>

                    <div v-if="credential.totp_secret" class="rounded-lg border bg-card p-5">
                        <h2 class="mb-3 flex items-center gap-1.5 text-sm font-semibold">
                            <ShieldCheck class="size-4 text-primary" />
                            Код двофакторної автентифікації
                        </h2>
                        <TotpCode :secret="credential.totp_secret" />
                    </div>

                    <div v-if="credential.notes" class="rounded-lg border bg-card p-5">
                        <h2 class="mb-2 text-sm font-semibold">Нотатки</h2>
                        <p class="text-sm whitespace-pre-line text-muted-foreground">{{ credential.notes }}</p>
                    </div>

                    <div v-if="credential.custom_fields.length" class="rounded-lg border bg-card p-5">
                        <h2 class="mb-3 text-sm font-semibold">Додаткові поля</h2>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div v-for="(field, index) in credential.custom_fields" :key="index" class="grid gap-1.5">
                                <Label class="text-xs text-muted-foreground">{{ field.label }}</Label>

                                <div v-if="field.type === 'boolean'" class="text-sm">
                                    <Badge :variant="field.value ? 'success' : 'outline'">{{ field.value ? 'Так' : 'Ні' }}</Badge>
                                </div>
                                <div v-else-if="field.type === 'hidden'" class="flex items-center gap-2">
                                    <span class="font-mono text-sm">{{ revealedFields.has(index) ? field.value || '—' : '••••••••' }}</span>
                                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="toggleField(index)">
                                        <EyeOff v-if="revealedFields.has(index)" class="size-3.5" />
                                        <Eye v-else class="size-3.5" />
                                    </button>
                                    <button
                                        v-if="field.value"
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="copy(field.value as string, `field-${index}`)"
                                    >
                                        <Check v-if="copiedField === `field-${index}`" class="size-3.5 text-success-foreground" />
                                        <Copy v-else class="size-3.5" />
                                    </button>
                                </div>
                                <span v-else class="text-sm">{{ field.value || '—' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    <div class="rounded-lg border bg-card p-5">
                        <h2 class="mb-3 text-sm font-semibold">Групи</h2>
                        <div v-if="credential.groups.length" class="flex flex-wrap gap-1.5">
                            <Link v-for="group in credential.groups" :key="group.id" :href="route('password-groups.show', group.id)">
                                <Badge variant="outline" class="hover:bg-accent">{{ group.name }}</Badge>
                            </Link>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">Без груп (публічний пароль)</p>
                    </div>

                    <div class="rounded-lg border bg-card p-5">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="flex items-center gap-1.5 text-sm font-semibold">
                                <Paperclip class="size-4" />
                                Файли
                            </h2>
                            <template v-if="canEditWith(credential.access_level)">
                                <input ref="fileInput" type="file" multiple class="hidden" @change="handleFileChange" />
                                <button type="button" class="text-muted-foreground hover:text-primary" @click="triggerFileInput">
                                    <Plus class="size-4" />
                                </button>
                            </template>
                        </div>

                        <div v-if="credential.attachments.length === 0" class="text-sm text-muted-foreground">Немає прикріплених файлів</div>

                        <div v-else class="flex flex-col gap-1.5">
                            <div
                                v-for="file in credential.attachments"
                                :key="file.id"
                                class="group flex items-center gap-2 rounded-md px-1 py-1 text-sm hover:bg-muted/50"
                            >
                                <span class="min-w-0 flex-1 truncate" :title="file.file_name">{{ file.file_name }}</span>
                                <span class="shrink-0 text-xs text-muted-foreground">{{ formatSize(file.size) }}</span>
                                <a :href="file.url" target="_blank" rel="noopener" class="shrink-0 text-muted-foreground hover:text-primary">
                                    <Download class="size-3.5" />
                                </a>
                                <button
                                    v-if="canEditWith(credential.access_level)"
                                    type="button"
                                    class="shrink-0 text-muted-foreground hover:text-destructive"
                                    @click="deletingAttachmentId = file.id"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDeleteDialog
            :open="isDeleteOpen"
            :processing="deleting"
            :title="`Видалити «${credential.name}»?`"
            description="Цю дію неможливо скасувати."
            @update:open="(open) => (isDeleteOpen = open)"
            @confirm="confirmDelete"
        />

        <ConfirmDeleteDialog
            :open="deletingAttachmentId !== null"
            title="Видалити файл?"
            description="Цю дію неможливо скасувати."
            @update:open="(open) => !open && (deletingAttachmentId = null)"
            @confirm="confirmDeleteAttachment"
        />
    </AppLayout>
</template>
