<script setup lang="ts">
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import CredentialIcon from '@/components/credentials/CredentialIcon.vue';
import { DataTable } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useTableQuery } from '@/composables/useTableQuery';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import { accessLevelBadgeVariant, accessLevelLabel, canEditWith, canManageWith } from '@/lib/credentials';
import { fromQueryParams } from '@/lib/table';
import type { BreadcrumbItem, PaginatedResponse, TableColumn } from '@/types';
import type { CredentialSummary } from '@/types/vault';
import { Head, Link, router } from '@inertiajs/vue3';
import { Globe, KeyRound, MoreHorizontal, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';

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

/**
 * Групування за групами паролів (user: "чим більше паролів, тим більше хаос") — акордеони
 * DataTable (groupBy). Пароль у кількох групах дублюється в кожній із них (окремий `row_key`,
 * бо `id` не унікальний серед розгорнутих рядків); без жодної групи (лише прямий/публічний
 * доступ) — "Без групи". Коли користувач сам обрав сортування колонки — групування вимкнено
 * (рядки тоді не кластеризовані), як на /assets.
 */
type GroupedCredential = CredentialSummary & { row_key: string; group_label: string };

const NO_GROUP = 'Без групи';
const isGrouped = computed(() => !props.query.sort);

const rows = computed<GroupedCredential[]>(() => {
    if (!isGrouped.value) {
        return props.credentials.data.map((credential) => ({ ...credential, row_key: String(credential.id), group_label: '' }));
    }
    const expanded = props.credentials.data.flatMap((credential) => {
        const labels = credential.groups.length ? credential.groups.map((g) => g.name) : [NO_GROUP];
        return labels.map((label) => ({ ...credential, row_key: `${label}:${credential.id}`, group_label: label }));
    });
    return expanded.sort((a, b) => {
        if (a.group_label === b.group_label) return a.name.localeCompare(b.name, 'uk');
        if (a.group_label === NO_GROUP) return 1;
        if (b.group_label === NO_GROUP) return -1;
        return a.group_label.localeCompare(b.group_label, 'uk');
    });
});

const groupBy = computed(() => (isGrouped.value ? (row: GroupedCredential) => row.group_label : undefined));

const query = useTableQuery({ path: route('passwords.index'), initial: fromQueryParams(props.query, []) });

const { success, error } = useToast();

/** Клацнути в меню рядка — на прохання користувача, за зразком меню Bitwarden. Логін вже є в
 *  рядку (не секрет), пароль запитуємо на вимогу через окремий JSON-ендпоінт (`reveal()`) —
 *  список НІКОЛИ не тримає розшифровані паролі всіх рядків одразу заради однієї кнопки. */
function copyLogin(row: CredentialSummary) {
    if (!row.login) return;
    navigator.clipboard.writeText(row.login);
    success('Логін скопійовано');
}

async function copyPassword(row: CredentialSummary) {
    const response = await fetch(route('passwords.reveal', row.id), { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        error('Не вдалося отримати пароль.');
        return;
    }
    const data = (await response.json()) as { password: string | null };
    if (!data.password) return;
    await navigator.clipboard.writeText(data.password);
    success('Пароль скопійовано');
}

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
                :rows="rows"
                row-key="row_key"
                :group-by="groupBy"
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
                            <DropdownMenuItem v-if="row.login" @click="copyLogin(row)">Копіювати логін</DropdownMenuItem>
                            <DropdownMenuItem @click="copyPassword(row)">Копіювати пароль</DropdownMenuItem>
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
