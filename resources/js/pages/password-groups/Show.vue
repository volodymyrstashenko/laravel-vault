<script setup lang="ts">
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import CredentialIcon from '@/components/credentials/CredentialIcon.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import { ACCESS_LEVEL_OPTIONS, accessLevelBadgeVariant, accessLevelLabel, canManageWith } from '@/lib/credentials';
import type { BreadcrumbItem } from '@/types';
import type { CredentialGroupDetail, CredentialGroupMember } from '@/types/vault';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { KeyRound, Trash2, UserPlus } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    group: CredentialGroupDetail;
    availableUsers: { id: number; name: string; email: string }[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Паролі', href: route('passwords.index') },
    { title: 'Групи паролів', href: route('password-groups.index') },
    { title: props.group.name, href: route('password-groups.show', props.group.id) },
]);

const { getInitials } = useInitials();
const canManage = computed(() => canManageWith(props.group.access_level));

const isEditingInfo = ref(false);
const infoForm = useForm({ name: props.group.name, description: props.group.description ?? '' });

function saveInfo() {
    infoForm.put(route('password-groups.update', props.group.id), {
        preserveScroll: true,
        onSuccess: () => (isEditingInfo.value = false),
    });
}

function changeMemberAccess(member: CredentialGroupMember, access_level: string) {
    router.put(route('password-groups.members.update', [props.group.id, member.id]), { access_level }, { preserveScroll: true });
}

function removeMember(member: CredentialGroupMember) {
    router.delete(route('password-groups.members.destroy', [props.group.id, member.id]), { preserveScroll: true });
}

const addMemberForm = useForm({ user_id: null as number | null, access_level: 'view' });

function addMember() {
    if (!addMemberForm.user_id) return;
    addMemberForm.post(route('password-groups.members.store', props.group.id), {
        preserveScroll: true,
        onSuccess: () => (addMemberForm.user_id = null),
    });
}

const isDeleteOpen = ref(false);
const deleting = ref(false);

function confirmDelete() {
    deleting.value = true;
    router.delete(route('password-groups.destroy', props.group.id), { onFinish: () => (deleting.value = false) });
}
</script>

<template>
    <Head :title="group.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-auto p-6">
            <div class="flex items-start justify-between gap-4">
                <div v-if="!isEditingInfo" class="min-w-0">
                    <h1 class="text-lg font-semibold">{{ group.name }}</h1>
                    <p v-if="group.description" class="text-sm text-muted-foreground">{{ group.description }}</p>
                </div>
                <form v-else class="flex flex-1 flex-col gap-2" @submit.prevent="saveInfo">
                    <Input v-model="infoForm.name" class="max-w-md font-semibold" />
                    <textarea
                        v-model="infoForm.description"
                        rows="2"
                        class="max-w-md rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    />
                    <div class="flex gap-2">
                        <Button size="sm" type="submit" :disabled="infoForm.processing">Зберегти</Button>
                        <Button size="sm" type="button" variant="ghost" @click="isEditingInfo = false">Скасувати</Button>
                    </div>
                </form>

                <div v-if="!isEditingInfo" class="flex shrink-0 items-center gap-2">
                    <Badge :variant="accessLevelBadgeVariant(group.access_level)">{{ accessLevelLabel(group.access_level) }}</Badge>
                    <Button v-if="canManage" variant="outline" size="sm" @click="isEditingInfo = true">Редагувати</Button>
                    <Button v-if="canManage" variant="destructive" size="sm" @click="isDeleteOpen = true">Видалити</Button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-lg border bg-card p-5">
                    <h2 class="mb-3 text-sm font-semibold">Учасники</h2>

                    <div class="flex flex-col gap-2">
                        <div
                            v-for="member in group.members"
                            :key="member.id"
                            class="flex items-center gap-2.5 rounded-lg border border-sidebar-border/70 p-2.5 dark:border-sidebar-border"
                        >
                            <Avatar size="sm" class="size-8">
                                <AvatarFallback class="bg-primary/10 text-xs text-primary">{{ getInitials(member.name) }}</AvatarFallback>
                            </Avatar>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-foreground">{{ member.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ member.email }}</p>
                            </div>

                            <template v-if="canManage">
                                <Select :model-value="member.access_level" @update:model-value="(v) => changeMemberAccess(member, v as string)">
                                    <SelectTrigger class="h-8 w-36 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="option in ACCESS_LEVEL_OPTIONS" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <button type="button" class="p-1.5 text-muted-foreground hover:text-destructive" @click="removeMember(member)">
                                    <Trash2 class="size-4" />
                                </button>
                            </template>
                            <Badge v-else :variant="accessLevelBadgeVariant(member.access_level)">{{ accessLevelLabel(member.access_level) }}</Badge>
                        </div>
                    </div>

                    <form v-if="canManage" class="mt-4 flex items-end gap-2 border-t pt-4" @submit.prevent="addMember">
                        <div class="grid flex-1 gap-1.5">
                            <Label class="text-xs">Додати учасника</Label>
                            <Select
                                :model-value="addMemberForm.user_id ? String(addMemberForm.user_id) : undefined"
                                @update:model-value="(v) => (addMemberForm.user_id = v ? Number(v) : null)"
                            >
                                <SelectTrigger><SelectValue placeholder="Обрати користувача…" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="user in availableUsers" :key="user.id" :value="String(user.id)">{{ user.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Select v-model="addMemberForm.access_level">
                            <SelectTrigger class="w-36"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in ACCESS_LEVEL_OPTIONS" :key="option.value" :value="option.value">{{
                                    option.label
                                }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button type="submit" size="icon" :disabled="!addMemberForm.user_id || addMemberForm.processing">
                            <UserPlus class="size-4" />
                        </Button>
                    </form>
                </div>

                <div class="rounded-lg border bg-card p-5">
                    <h2 class="mb-3 text-sm font-semibold">Паролі в цій групі</h2>

                    <div v-if="group.credentials.length === 0" class="flex flex-col items-center justify-center gap-2 py-8 text-muted-foreground">
                        <KeyRound class="size-8 opacity-20" />
                        <p class="text-sm">Паролів ще немає</p>
                    </div>

                    <div v-else class="flex flex-col gap-1.5">
                        <Link
                            v-for="credential in group.credentials"
                            :key="credential.id"
                            :href="route('passwords.show', credential.id)"
                            class="flex items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-muted/50"
                        >
                            <CredentialIcon :icon-url="credential.icon_url" size="sm" />
                            <span class="min-w-0 flex-1 truncate font-medium text-foreground">{{ credential.name }}</span>
                            <span class="shrink-0 text-xs text-muted-foreground">{{ credential.login ?? '—' }}</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDeleteDialog
            :open="isDeleteOpen"
            :processing="deleting"
            :title="`Видалити групу «${group.name}»?`"
            description="Цю дію неможливо скасувати. Якщо в групі є паролі без інших груп, видалення буде заблоковано."
            @update:open="(open) => (isDeleteOpen = open)"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
