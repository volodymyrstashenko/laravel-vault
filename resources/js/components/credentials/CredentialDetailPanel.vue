<script setup lang="ts">
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import CredentialIcon from '@/components/credentials/CredentialIcon.vue';
import TotpCode from '@/components/credentials/TotpCode.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInitials } from '@/composables/useInitials';
import { ACCESS_LEVEL_OPTIONS, accessLevelBadgeVariant, accessLevelLabel, canEditWith, canManageWith } from '@/lib/credentials';
import type { CredentialDirectUser, CredentialSelection } from '@/types/vault';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Copy, Cpu, Download, ExternalLink, Eye, EyeOff, Globe, Lock, Paperclip, Pencil, Plus, ShieldCheck, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { siteHref, useCredentialActions } from './useCredentialActions';

const props = defineProps<CredentialSelection>();

const { getInitials } = useInitials();
const { copyText } = useCredentialActions();

const showPassword = ref(false);
const revealedFields = ref<Set<number>>(new Set());

function toggleField(index: number) {
    if (revealedFields.value.has(index)) revealedFields.value.delete(index);
    else revealedFields.value.add(index);
}

function changeDirectAccess(directUser: CredentialDirectUser, access_level: string) {
    router.put(
        route('passwords.access.update', [props.credential.id, directUser.id]),
        { access_level },
        { preserveScroll: true, preserveState: true },
    );
}

function removeDirectAccess(directUser: CredentialDirectUser) {
    router.delete(route('passwords.access.destroy', [props.credential.id, directUser.id]), { preserveScroll: true, preserveState: true });
}

const addAccessForm = useForm({ user_id: null as number | null, access_level: 'view' });

function addDirectAccess() {
    if (!addAccessForm.user_id) return;
    addAccessForm.post(route('passwords.access.store', props.credential.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (addAccessForm.user_id = null),
    });
}

const isDeleteOpen = ref(false);
const deleting = ref(false);

function confirmDelete() {
    deleting.value = true;
    router.delete(route('passwords.destroy', props.credential.id), { onFinish: () => (deleting.value = false) });
}

const fileInput = ref<HTMLInputElement | null>(null);

function handleFileChange(e: Event) {
    const files = (e.target as HTMLInputElement).files;
    if (!files || files.length === 0) return;
    router.post(
        route('passwords.attachments.store', props.credential.id),
        { files: Array.from(files) },
        { forceFormData: true, preserveScroll: true, preserveState: true },
    );
}

const deletingAttachmentId = ref<number | null>(null);

function confirmDeleteAttachment() {
    if (!deletingAttachmentId.value) return;
    router.delete(route('passwords.attachments.destroy', [props.credential.id, deletingAttachmentId.value]), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (deletingAttachmentId.value = null),
    });
}

function formatSize(bytes: number): string {
    return bytes < 1024 * 1024 ? `${Math.ceil(bytes / 1024)} КБ` : `${(bytes / (1024 * 1024)).toFixed(1)} МБ`;
}

const iconButton = 'inline-flex size-9 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground';
const card = 'rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border';
</script>

<template>
    <div class="@container flex min-w-0 flex-col gap-4">
        <div :class="card" class="p-4">
            <div class="flex items-start gap-3">
                <CredentialIcon :icon-url="credential.icon_url" size="md" />
                <div class="min-w-0 flex-1">
                    <h1 class="text-lg leading-tight font-semibold break-words">{{ credential.name }}</h1>
                    <p class="mt-0.5 text-xs text-muted-foreground">Створив {{ credential.created_by?.name ?? '—' }} · {{ credential.created_at }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <Badge :variant="accessLevelBadgeVariant(credential.access_level)">{{ accessLevelLabel(credential.access_level) }}</Badge>
                        <Badge variant="outline" class="gap-1">
                            <component :is="credential.visibility === 'public' ? Globe : Lock" class="size-3" />
                            {{ credential.visibility === 'public' ? 'Публічно' : 'Група' }}
                        </Badge>
                        <Link v-for="group in credential.groups" :key="group.id" :href="route('password-groups.show', group.id)">
                            <Badge variant="outline" class="hover:bg-accent">{{ group.name }}</Badge>
                        </Link>
                    </div>
                </div>
            </div>
            <div v-if="canEditWith(credential.access_level)" class="mt-3 flex flex-wrap gap-2">
                <Button as-child variant="outline" size="sm" class="gap-1.5">
                    <Link :href="route('passwords.edit', credential.id)"><Pencil class="size-4" /> Редагувати</Link>
                </Button>
                <Button
                    v-if="canManageWith(credential.access_level)"
                    variant="outline"
                    size="sm"
                    class="gap-1.5 text-destructive"
                    @click="isDeleteOpen = true"
                >
                    <Trash2 class="size-4" /> Видалити
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-1 items-start gap-4 @3xl:grid-cols-5">
            <div class="flex min-w-0 flex-col gap-4 @3xl:col-span-3">
                <div :class="card" class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                    <div class="flex items-center gap-2 px-4 py-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-muted-foreground">Логін</p>
                            <p class="truncate font-mono text-sm">{{ credential.login || '—' }}</p>
                        </div>
                        <button
                            v-if="credential.login"
                            type="button"
                            :class="iconButton"
                            title="Копіювати логін"
                            @click="copyText(credential.login, 'Логін')"
                        >
                            <Copy class="size-4" />
                        </button>
                    </div>
                    <div class="flex items-center gap-2 px-4 py-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-muted-foreground">Пароль</p>
                            <p class="font-mono text-sm break-all">
                                {{ credential.password ? (showPassword ? credential.password : '••••••••••••') : '—' }}
                            </p>
                        </div>
                        <template v-if="credential.password">
                            <button
                                type="button"
                                :class="iconButton"
                                :title="showPassword ? 'Сховати' : 'Показати'"
                                @click="showPassword = !showPassword"
                            >
                                <EyeOff v-if="showPassword" class="size-4" />
                                <Eye v-else class="size-4" />
                            </button>
                            <button type="button" :class="iconButton" title="Копіювати пароль" @click="copyText(credential.password, 'Пароль')">
                                <Copy class="size-4" />
                            </button>
                        </template>
                    </div>
                    <div v-if="credential.totp_secret" class="flex items-center gap-2 px-4 py-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1 text-xs text-muted-foreground"><ShieldCheck class="size-3.5" /> Код 2FA</p>
                            <TotpCode :key="credential.id" :secret="credential.totp_secret" />
                        </div>
                    </div>
                    <div v-if="credential.url" class="flex items-center gap-2 px-4 py-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-muted-foreground">Посилання</p>
                            <a
                                :href="siteHref(credential.url)"
                                target="_blank"
                                rel="noopener"
                                class="block truncate text-sm text-primary hover:underline"
                            >
                                {{ credential.url }}
                            </a>
                        </div>
                        <button type="button" :class="iconButton" title="Копіювати посилання" @click="copyText(credential.url, 'Посилання')">
                            <Copy class="size-4" />
                        </button>
                        <a :href="siteHref(credential.url)" target="_blank" rel="noopener" :class="iconButton" title="Відкрити сайт">
                            <ExternalLink class="size-4" />
                        </a>
                    </div>
                    <div v-for="(field, index) in credential.custom_fields" :key="index" class="flex items-center gap-2 px-4 py-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-muted-foreground">{{ field.label }}</p>
                            <Badge v-if="field.type === 'boolean'" :variant="field.value ? 'success' : 'outline'" class="mt-0.5">
                                {{ field.value ? 'Так' : 'Ні' }}
                            </Badge>
                            <p v-else-if="field.type === 'hidden'" class="font-mono text-sm break-all">
                                {{ revealedFields.has(index) ? field.value || '—' : '••••••••' }}
                            </p>
                            <p v-else class="text-sm break-words">{{ field.value || '—' }}</p>
                        </div>
                        <button v-if="field.type === 'hidden'" type="button" :class="iconButton" @click="toggleField(index)">
                            <EyeOff v-if="revealedFields.has(index)" class="size-4" />
                            <Eye v-else class="size-4" />
                        </button>
                        <button
                            v-if="field.type !== 'boolean' && field.value"
                            type="button"
                            :class="iconButton"
                            title="Копіювати"
                            @click="copyText(field.value as string, field.label)"
                        >
                            <Copy class="size-4" />
                        </button>
                    </div>
                </div>

                <div v-if="credential.notes" :class="card" class="p-4">
                    <h2 class="mb-2 text-sm font-semibold">Нотатки</h2>
                    <p class="text-sm break-words whitespace-pre-line text-muted-foreground">{{ credential.notes }}</p>
                </div>

                <div v-if="linkedAssets.length" :class="card" class="p-4">
                    <h2 class="mb-2 flex items-center gap-1.5 text-sm font-semibold"><Cpu class="size-4 text-primary" /> Пов'язані пристрої</h2>
                    <div class="flex flex-col gap-1">
                        <component
                            :is="asset.url ? Link : 'div'"
                            v-for="asset in linkedAssets"
                            :key="asset.id"
                            :href="asset.url ?? undefined"
                            class="flex items-center justify-between gap-2 rounded-md px-1 py-1.5 text-sm"
                            :class="asset.url ? 'hover:bg-muted/50' : ''"
                        >
                            <span class="truncate" :class="asset.url ? 'text-primary' : ''">{{ asset.name }}</span>
                            <span v-if="asset.subtitle" class="shrink-0 text-xs text-muted-foreground">{{ asset.subtitle }}</span>
                        </component>
                    </div>
                </div>
            </div>

            <div class="flex min-w-0 flex-col gap-4 @3xl:col-span-2">
                <div :class="card" class="p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <h2 class="flex items-center gap-1.5 text-sm font-semibold"><Paperclip class="size-4" /> Файли</h2>
                        <template v-if="canEditWith(credential.access_level)">
                            <input ref="fileInput" type="file" multiple class="hidden" @change="handleFileChange" />
                            <button type="button" :class="iconButton" title="Прикріпити файл" @click="fileInput?.click()">
                                <Plus class="size-4" />
                            </button>
                        </template>
                    </div>
                    <p v-if="credential.attachments.length === 0" class="text-sm text-muted-foreground">Немає прикріплених файлів</p>
                    <div v-else class="flex flex-col gap-0.5">
                        <div
                            v-for="file in credential.attachments"
                            :key="file.id"
                            class="flex items-center gap-1 rounded-md pl-1 text-sm hover:bg-muted/50"
                        >
                            <span class="min-w-0 flex-1 truncate" :title="file.file_name">{{ file.file_name }}</span>
                            <span class="shrink-0 text-xs text-muted-foreground">{{ formatSize(file.size) }}</span>
                            <a :href="file.url" target="_blank" rel="noopener" :class="iconButton" title="Завантажити"><Download class="size-4" /></a>
                            <button
                                v-if="canEditWith(credential.access_level)"
                                type="button"
                                :class="iconButton"
                                class="hover:text-destructive"
                                title="Видалити файл"
                                @click="deletingAttachmentId = file.id"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <div :class="card" class="p-4">
                    <h2 class="mb-2 text-sm font-semibold">Персональний доступ</h2>
                    <p v-if="credential.direct_users.length === 0" class="text-sm text-muted-foreground">Немає користувачів з окремим доступом</p>
                    <div v-else class="flex flex-col gap-2">
                        <div
                            v-for="directUser in credential.direct_users"
                            :key="directUser.id"
                            class="flex flex-col gap-2 rounded-lg border border-sidebar-border/70 p-2.5 dark:border-sidebar-border"
                        >
                            <div class="flex items-center gap-2.5">
                                <Avatar size="sm" class="size-8 shrink-0">
                                    <AvatarFallback class="bg-primary/10 text-xs text-primary">{{ getInitials(directUser.name) }}</AvatarFallback>
                                </Avatar>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ directUser.name }}</p>
                                    <p class="truncate text-xs text-muted-foreground">{{ directUser.email }}</p>
                                </div>
                                <Badge v-if="!canManageWith(credential.access_level)" :variant="accessLevelBadgeVariant(directUser.access_level)">
                                    {{ accessLevelLabel(directUser.access_level) }}
                                </Badge>
                            </div>
                            <div v-if="canManageWith(credential.access_level)" class="flex items-center gap-2 pl-[42px]">
                                <Select
                                    :model-value="directUser.access_level"
                                    @update:model-value="(v) => changeDirectAccess(directUser, v as string)"
                                >
                                    <SelectTrigger class="h-8 flex-1 text-xs"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="option in ACCESS_LEVEL_OPTIONS" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <button
                                    type="button"
                                    :class="iconButton"
                                    class="hover:text-destructive"
                                    title="Прибрати доступ"
                                    @click="removeDirectAccess(directUser)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <form v-if="canManageWith(credential.access_level)" class="mt-3 grid gap-2 border-t pt-3" @submit.prevent="addDirectAccess">
                        <Label class="text-xs">Надати доступ користувачу</Label>
                        <Select
                            :model-value="addAccessForm.user_id ? String(addAccessForm.user_id) : undefined"
                            @update:model-value="(v) => (addAccessForm.user_id = v ? Number(v) : null)"
                        >
                            <SelectTrigger><SelectValue placeholder="Обрати користувача…" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="user in availableUsersForAccess" :key="user.id" :value="String(user.id)">{{
                                    user.name
                                }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <div class="flex gap-2">
                            <Select v-model="addAccessForm.access_level">
                                <SelectTrigger class="flex-1"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in ACCESS_LEVEL_OPTIONS" :key="option.value" :value="option.value">{{
                                        option.label
                                    }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <Button type="submit" :disabled="!addAccessForm.user_id || addAccessForm.processing">Надати</Button>
                        </div>
                    </form>
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
    </div>
</template>
