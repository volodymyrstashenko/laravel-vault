<script setup lang="ts">
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import CredentialIcon from '@/components/credentials/CredentialIcon.vue';
import { DataTable } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { accessLevelBadgeVariant, accessLevelLabel, canEditWith, canManageWith } from '@/lib/credentials';
import { fromQueryParams } from '@/lib/table';
import type { BreadcrumbItem, PaginatedResponse, TableColumn } from '@/types';
import type { CredentialSummary } from '@/types/vault';
import { Head, Link, router } from '@inertiajs/vue3';
import { Globe, KeyRound, MoreHorizontal, Plus } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps<{
    credentials: PaginatedResponse<CredentialSummary>;
    query: Record<string, string | undefined>;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Паролі', href: route('passwords.index') }];

const columns: TableColumn<CredentialSummary>[] = [
    { key: 'name', label: 'Назва', sortable: true },
    { key: 'groups', label: 'Групи' },
    { key: 'access_level', label: 'Мій доступ', hideOnMobile: true },
    { key: 'created_at', label: 'Створено', sortable: true, hideOnMobile: true },
    { key: 'actions', label: '', class: 'w-10' },
];

const query = useTableQuery({ path: route('passwords.index'), initial: fromQueryParams(props.query, []) });

const deletingCredential = ref<CredentialSummary | null>(null);
const deleting = ref(false);

function confirmDelete() {
    if (!deletingCredential.value) return;
    deleting.value = true;
    router.delete(route('passwords.destroy', deletingCredential.value.id), {
        onFinish: () => {
            deleting.value = false;
            deletingCredential.value = null;
        },
    });
}
</script>

<template>
    <Head title="Паролі" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <DataTable
                :columns="columns"
                :rows="credentials.data"
                :meta="credentials.meta"
                :state="query.state"
                search-placeholder="Пошук за назвою або логіном…"
                empty-message="Паролів не знайдено. Спочатку створіть групу паролів — див. «Групи паролів»."
                @update:search="query.setSearch"
                @sort="query.setSort"
                @page="query.setPage"
            >
                <template #toolbar-actions>
                    <Button as-child variant="outline" size="sm" class="gap-1.5">
                        <Link :href="route('password-groups.index')">
                            <KeyRound class="size-4" />
                            Групи паролів
                        </Link>
                    </Button>
                    <Button as-child size="sm" class="gap-1.5">
                        <Link :href="route('passwords.create')">
                            <Plus class="size-4" />
                            Новий пароль
                        </Link>
                    </Button>
                </template>

                <template #cell-name="{ row }">
                    <Link :href="route('passwords.show', row.id)" class="flex items-center gap-3 py-1">
                        <CredentialIcon :icon-url="row.icon_url" />
                        <div class="min-w-0">
                            <p class="flex items-center gap-1.5 truncate font-medium text-foreground hover:underline">
                                {{ row.name }}
                                <Globe v-if="row.visibility === 'public'" class="size-3 shrink-0 text-muted-foreground" />
                            </p>
                            <p v-if="row.login" class="truncate text-xs text-muted-foreground">{{ row.login }}</p>
                        </div>
                    </Link>
                </template>

                <template #cell-groups="{ row }">
                    <div class="flex flex-wrap gap-1">
                        <Badge v-for="group in row.groups" :key="group.id" variant="outline" class="text-[11px]">{{ group.name }}</Badge>
                    </div>
                </template>

                <template #cell-access_level="{ value }">
                    <Badge :variant="accessLevelBadgeVariant(value as string)">{{ accessLevelLabel(value as string) }}</Badge>
                </template>

                <template #cell-actions="{ row }">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="icon" class="size-8">
                                <MoreHorizontal class="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem as-child>
                                <Link :href="route('passwords.show', row.id)">Переглянути</Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="canEditWith(row.access_level)" as-child>
                                <Link :href="route('passwords.edit', row.id)">Редагувати</Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="canManageWith(row.access_level)" class="text-destructive" @click="deletingCredential = row">
                                Видалити
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>
            </DataTable>
        </div>

        <ConfirmDeleteDialog
            :open="deletingCredential !== null"
            :processing="deleting"
            :title="`Видалити «${deletingCredential?.name}»?`"
            description="Цю дію неможливо скасувати."
            @update:open="(open) => !open && (deletingCredential = null)"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
