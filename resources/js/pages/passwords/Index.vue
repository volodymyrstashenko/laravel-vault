<script setup lang="ts">
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import CredentialDetailPanel from '@/components/credentials/CredentialDetailPanel.vue';
import CredentialIcon from '@/components/credentials/CredentialIcon.vue';
import CredentialMenuItems from '@/components/credentials/CredentialMenuItems.vue';
import { useCredentialActions } from '@/components/credentials/useCredentialActions';
import { Button } from '@/components/ui/button';
import { ContextMenu, ContextMenuContent, ContextMenuTrigger } from '@/components/ui/context-menu';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { CredentialSelection, CredentialSummary } from '@/types/vault';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ArrowUpDown, Copy, Globe, KeyRound, LoaderCircle, MoreHorizontal, MousePointerClick, Plus, Search, X } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    credentials: CredentialSummary[];
    selected: CredentialSelection | null;
}>();

const selectedId = computed(() => props.selected?.credential.id ?? null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Паролі', href: route('passwords.index') },
    ...(props.selected ? [{ title: props.selected.credential.name, href: route('passwords.show', props.selected.credential.id) }] : []),
]);

// ── Фільтр груп і пошук — на клієнті: список уже весь на сторінці ────────────────────────
type GroupFilter = 'all' | 'none' | number;
const GROUP_KEY = 'passwords.group';
const NO_GROUP = 'none';

function readGroup(): GroupFilter {
    try {
        const raw = localStorage.getItem(GROUP_KEY);
        if (raw === 'all' || raw === NO_GROUP) return raw;
        if (raw && /^\d+$/.test(raw)) return Number(raw);
    } catch {
        // private mode / blocked storage — default below
    }
    return 'all';
}

const group = ref<GroupFilter>(readGroup());
watch(group, (value) => {
    try {
        localStorage.setItem(GROUP_KEY, String(value));
    } catch {
        // ignore
    }
});

const groupChips = computed(() => {
    const counts = new Map<number, { id: number; name: string; count: number }>();
    let ungrouped = 0;
    for (const credential of props.credentials) {
        if (!credential.groups.length) ungrouped++;
        for (const g of credential.groups) {
            const entry = counts.get(g.id) ?? { id: g.id, name: g.name, count: 0 };
            entry.count++;
            counts.set(g.id, entry);
        }
    }
    return {
        groups: [...counts.values()].sort((a, b) => a.name.localeCompare(b.name, 'uk')),
        ungrouped,
    };
});

// Обрана група зникла (видалили / забрали доступ) — повертаємось до «Усі».
watch(
    groupChips,
    (chips) => {
        if (typeof group.value === 'number' && !chips.groups.some((g) => g.id === group.value)) group.value = 'all';
        if (group.value === NO_GROUP && !chips.ungrouped) group.value = 'all';
    },
    { immediate: true },
);

const search = ref('');
const searchInput = ref<InstanceType<typeof Input> | null>(null);

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.credentials.filter((c) => {
        if (group.value === NO_GROUP && c.groups.length) return false;
        if (typeof group.value === 'number' && !c.groups.some((g) => g.id === group.value)) return false;
        if (!q) return true;
        return [c.name, c.login, c.url, ...c.groups.map((g) => g.name)].some((v) => v?.toLowerCase().includes(q));
    });
});

// ── Вибір пароля: partial reload лише `selected`, список не перезапитується ──────────────
const loading = ref(false);

// Long-press on a phone opens the context menu, and lifting the finger then fires a click on
// the row — which would open the credential underneath the menu.
let menuOpenedAt = 0;

function onMenuOpen(open: boolean) {
    if (open) menuOpenedAt = Date.now();
}

function onRowClick(id: number) {
    if (Date.now() - menuOpenedAt < 1000) return;
    select(id);
}
let listScrollY = 0;

function isDesktop() {
    return window.matchMedia('(min-width: 1024px)').matches;
}

function select(id: number) {
    if (id === selectedId.value) return;
    if (!isDesktop()) listScrollY = window.scrollY;
    router.get(
        route('passwords.show', id),
        {},
        {
            only: ['selected'],
            preserveState: true,
            preserveScroll: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
            onSuccess: () => {
                if (!isDesktop()) window.scrollTo({ top: 0 });
            },
        },
    );
}

function closeSelected() {
    router.get(
        route('passwords.index'),
        {},
        {
            only: ['selected'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                if (!isDesktop()) nextTick(() => window.scrollTo({ top: listScrollY }));
            },
        },
    );
}

watch(selectedId, (id) => {
    if (id) nextTick(() => document.getElementById(`credential-${id}`)?.scrollIntoView({ block: 'nearest' }));
});

// ── Клавіатура: / — пошук, ↑↓ — сусідній пароль, Esc — закрити ──────────────────────────
function onKeydown(e: KeyboardEvent) {
    if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return;
    if (document.querySelector('[role="dialog"], [role="menu"]')) return;

    const target = e.target as HTMLElement | null;
    const inSearch = target?.id === 'credential-search';
    const typing = !inSearch && !!target?.closest('input, textarea, select, [contenteditable="true"]');
    if (typing) return;

    if (e.key === '/' && !inSearch) {
        e.preventDefault();
        (searchInput.value?.$el as HTMLInputElement | undefined)?.focus();
        return;
    }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        const list = filtered.value;
        if (!list.length) return;
        e.preventDefault();
        const index = list.findIndex((c) => c.id === selectedId.value);
        const next = index === -1 ? (e.key === 'ArrowDown' ? 0 : list.length - 1) : index + (e.key === 'ArrowDown' ? 1 : -1);
        if (next >= 0 && next < list.length) select(list[next].id);
        return;
    }
    if (e.key === 'Escape') {
        if (inSearch && search.value) search.value = '';
        else if (selectedId.value) closeSelected();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));

// ── Дії рядка ──────────────────────────────────────────────────────────────────────────────
const { copyText, copyPassword } = useCredentialActions();

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

const chipClass = (active: boolean) =>
    [
        'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1 text-xs whitespace-nowrap transition-colors',
        active
            ? 'border-primary bg-primary text-primary-foreground'
            : 'border-sidebar-border/70 text-muted-foreground hover:bg-muted dark:border-sidebar-border',
    ].join(' ');
const rowButton =
    'inline-flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-background hover:text-foreground focus-visible:opacity-100';
</script>

<template>
    <Head :title="selected ? selected.credential.name : 'Паролі'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-4 p-3 sm:p-4 lg:flex-row lg:items-start">
            <!-- Список -->
            <aside
                class="min-w-0 flex-col rounded-xl border border-sidebar-border/70 bg-card lg:sticky lg:top-4 lg:max-h-[calc(100svh-6rem)] lg:w-[22rem] lg:shrink-0 xl:w-[26rem] dark:border-sidebar-border"
                :class="selected ? 'hidden lg:flex' : 'flex'"
            >
                <div class="flex flex-col gap-3 border-b border-sidebar-border/70 p-3 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-2">
                        <h1 class="text-base font-semibold">
                            Паролі <span class="font-normal text-muted-foreground">{{ credentials.length }}</span>
                        </h1>
                        <div class="flex items-center gap-1">
                            <Button as-child size="sm" class="gap-1.5">
                                <Link :href="route('passwords.create')"><Plus class="size-4" /> Новий</Link>
                            </Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="size-8" title="Ще"><MoreHorizontal class="size-4" /></Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem as-child>
                                        <Link :href="route('password-groups.index')"><KeyRound class="size-4" /> Групи паролів</Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem as-child>
                                        <Link :href="route('passwords.transfer')"><ArrowUpDown class="size-4" /> Імпорт / експорт</Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>

                    <div class="relative">
                        <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="credential-search"
                            ref="searchInput"
                            v-model="search"
                            type="search"
                            placeholder="Пошук: назва, логін, сайт"
                            class="pr-8 pl-8"
                            autocomplete="off"
                        />
                        <kbd
                            v-if="!search"
                            class="pointer-events-none absolute top-1/2 right-2 hidden -translate-y-1/2 rounded border px-1.5 text-[10px] text-muted-foreground lg:block"
                            >/</kbd
                        >
                        <button
                            v-else
                            type="button"
                            class="absolute top-1/2 right-1.5 -translate-y-1/2 rounded p-1 text-muted-foreground hover:text-foreground"
                            title="Очистити"
                            @click="search = ''"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>

                    <div v-if="groupChips.groups.length" class="-mx-3 flex [scrollbar-width:none] gap-1.5 overflow-x-auto px-3 pb-0.5">
                        <button type="button" :class="chipClass(group === 'all')" @click="group = 'all'">
                            Усі <span class="opacity-70">{{ credentials.length }}</span>
                        </button>
                        <button v-for="g in groupChips.groups" :key="g.id" type="button" :class="chipClass(group === g.id)" @click="group = g.id">
                            {{ g.name }} <span class="opacity-70">{{ g.count }}</span>
                        </button>
                        <button v-if="groupChips.ungrouped" type="button" :class="chipClass(group === 'none')" @click="group = 'none'">
                            Без групи <span class="opacity-70">{{ groupChips.ungrouped }}</span>
                        </button>
                    </div>
                </div>

                <ul class="min-h-0 flex-1 overflow-y-auto p-1.5">
                    <li v-if="!credentials.length" class="p-6 text-center text-sm text-muted-foreground">
                        Паролів ще немає. Створіть групу паролів або одразу новий пароль.
                    </li>
                    <li v-else-if="!filtered.length" class="p-6 text-center text-sm text-muted-foreground">Нічого не знайдено</li>
                    <li v-for="credential in filtered" :id="`credential-${credential.id}`" :key="credential.id">
                        <ContextMenu @update:open="onMenuOpen">
                            <ContextMenuTrigger as-child>
                                <div
                                    class="group flex items-center gap-1 rounded-lg pr-1 transition-colors select-none [-webkit-touch-callout:none]"
                                    :class="credential.id === selectedId ? 'bg-accent text-accent-foreground' : 'hover:bg-muted/60'"
                                >
                                    <a
                                        :href="route('passwords.show', credential.id)"
                                        class="flex min-w-0 flex-1 items-center gap-3 px-2 py-2"
                                        @click.exact.prevent="onRowClick(credential.id)"
                                    >
                                        <CredentialIcon :icon-url="credential.icon_url" size="sm" />
                                        <div class="min-w-0">
                                            <p class="flex items-center gap-1.5 truncate text-sm font-medium">
                                                <span class="truncate">{{ credential.name }}</span>
                                                <Globe v-if="credential.visibility === 'public'" class="size-3 shrink-0 text-muted-foreground" />
                                            </p>
                                            <p class="truncate text-xs text-muted-foreground">
                                                {{ credential.login || credential.groups.map((g) => g.name).join(', ') || '—' }}
                                            </p>
                                        </div>
                                    </a>
                                    <div
                                        class="hidden shrink-0 items-center lg:flex lg:opacity-0 lg:group-focus-within:opacity-100 lg:group-hover:opacity-100"
                                    >
                                        <button
                                            v-if="credential.login"
                                            type="button"
                                            :class="rowButton"
                                            title="Копіювати логін"
                                            @click="copyText(credential.login, 'Логін')"
                                        >
                                            <Copy class="size-3.5" />
                                        </button>
                                        <button type="button" :class="rowButton" title="Копіювати пароль" @click="copyPassword(credential)">
                                            <KeyRound class="size-3.5" />
                                        </button>
                                    </div>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button type="button" :class="rowButton" title="Дії">
                                                <MoreHorizontal class="size-4" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="min-w-44">
                                            <CredentialMenuItems
                                                :credential="credential"
                                                kind="dropdown"
                                                @open="select(credential.id)"
                                                @delete="deletingCredential = credential"
                                            />
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </ContextMenuTrigger>
                            <ContextMenuContent>
                                <CredentialMenuItems
                                    :credential="credential"
                                    kind="context"
                                    @open="select(credential.id)"
                                    @delete="deletingCredential = credential"
                                />
                            </ContextMenuContent>
                        </ContextMenu>
                    </li>
                </ul>
                <p class="hidden border-t border-sidebar-border/70 px-3 py-2 text-[11px] text-muted-foreground lg:block dark:border-sidebar-border">
                    Правий клік — дії · ↑↓ — сусідній пароль · / — пошук
                </p>
            </aside>

            <!-- Перегляд -->
            <section class="min-w-0 flex-1 flex-col gap-3" :class="selected ? 'flex' : 'hidden lg:flex'">
                <div class="flex items-center justify-between gap-2 lg:hidden">
                    <button type="button" class="flex items-center gap-1 py-1 text-sm font-medium text-primary" @click="closeSelected">
                        <ArrowLeft class="size-4" /> Паролі
                    </button>
                    <LoaderCircle v-if="loading" class="size-4 animate-spin text-muted-foreground" />
                </div>

                <div v-if="selected" class="relative" :class="loading ? 'opacity-60 transition-opacity' : ''">
                    <CredentialDetailPanel
                        :key="selected.credential.id"
                        :credential="selected.credential"
                        :available-users-for-access="selected.availableUsersForAccess"
                        :linked-assets="selected.linkedAssets"
                    />
                </div>
                <div
                    v-else
                    class="flex min-h-80 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground dark:border-sidebar-border"
                >
                    <MousePointerClick class="size-6" />
                    Оберіть пароль зі списку — він відкриється тут.
                    <span class="text-xs">Правий клік по паролю — швидкі дії: копіювати логін, пароль, код 2FA.</span>
                </div>
            </section>
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
