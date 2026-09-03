<script setup lang="ts">
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import WifiConnectModal from '@/components/wifi/WifiConnectModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { bandIcon, bandLabel, BAND_OPTIONS, securityLabel, SECURITY_OPTIONS } from '@/lib/wifi';
import { VISIBILITY_OPTIONS } from '@/lib/credentials';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { CredentialGroupRef, CredentialVisibility, WifiBand, WifiNetwork, WifiSecurity } from '@/types/vault';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Copy, Eye, EyeOff, Globe, Lock, MoreHorizontal, Pencil, Plus, QrCode, Trash2, Wifi } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    networks: WifiNetwork[];
    canCreatePublic: boolean;
    manageableGroups: CredentialGroupRef[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Wi-Fi', href: route('wifi.index') }];

const canAddAny = computed(() => props.canCreatePublic || props.manageableGroups.length > 0);

const visiblePasswordIds = ref<Set<number>>(new Set());
function togglePasswordVisible(id: number) {
    const next = new Set(visiblePasswordIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    visiblePasswordIds.value = next;
}

const copiedField = ref<string | null>(null);
function copy(value: string | null, field: string) {
    if (!value) return;
    navigator.clipboard.writeText(value);
    copiedField.value = field;
    setTimeout(() => (copiedField.value = null), 1500);
}

const connectModal = ref<InstanceType<typeof WifiConnectModal> | null>(null);

// --- Create / edit ---

const isFormOpen = ref(false);
const editingNetwork = ref<WifiNetwork | null>(null);
const form = useForm({
    visibility: 'public' as CredentialVisibility,
    ssid: '',
    password: '',
    security: 'WPA' as WifiSecurity,
    is_hidden: false,
    bands: [] as WifiBand[],
    location: '',
    notes: '',
    group_ids: [] as number[],
});

function openCreateForm() {
    editingNetwork.value = null;
    form.reset();
    form.clearErrors();
    form.visibility = props.canCreatePublic ? 'public' : 'group';
    isFormOpen.value = true;
}

function openEditForm(network: WifiNetwork) {
    editingNetwork.value = network;
    form.visibility = network.visibility;
    form.ssid = network.ssid;
    form.password = network.password ?? '';
    form.security = network.security;
    form.is_hidden = network.is_hidden;
    form.bands = [...(network.bands ?? [])];
    form.location = network.location ?? '';
    form.notes = network.notes ?? '';
    form.group_ids = network.groups.map((g) => g.id);
    form.clearErrors();
    isFormOpen.value = true;
}

function toggleBand(band: WifiBand) {
    form.bands = form.bands.includes(band) ? form.bands.filter((b) => b !== band) : [...form.bands, band];
}

function toggleGroup(id: number) {
    form.group_ids = form.group_ids.includes(id) ? form.group_ids.filter((g) => g !== id) : [...form.group_ids, id];
}

function submitForm() {
    const options = { preserveScroll: true, onSuccess: () => (isFormOpen.value = false) };
    if (editingNetwork.value) {
        form.put(route('wifi.update', editingNetwork.value.id), options);
    } else {
        form.post(route('wifi.store'), options);
    }
}

const deletingNetwork = ref<WifiNetwork | null>(null);
const isDeleting = ref(false);

function confirmDelete() {
    if (!deletingNetwork.value) return;
    isDeleting.value = true;
    router.delete(route('wifi.destroy', deletingNetwork.value.id), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            deletingNetwork.value = null;
        },
    });
}
</script>

<template>
    <Head title="Wi-Fi" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-lg font-semibold">Wi-Fi мережі</h1>
                    <p class="text-sm text-muted-foreground">SSID, пароль і QR-код для швидкого підключення</p>
                </div>
                <Button v-if="canAddAny" size="sm" class="gap-1.5" @click="openCreateForm">
                    <Plus class="size-4" />
                    Додати мережу
                </Button>
            </div>

            <div v-if="networks.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="network in networks"
                    :key="network.id"
                    class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex min-w-0 items-start gap-2.5">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-viz-sky/15 text-viz-sky">
                                <component :is="network.max_band ? bandIcon(network.max_band) : Wifi" class="size-4" />
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-foreground">{{ network.ssid }}</p>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <Badge v-if="network.max_band" variant="secondary" class="px-1.5 py-0 text-[10px]">
                                        {{ bandLabel(network.max_band) }}
                                    </Badge>
                                    <Badge variant="outline" class="px-1.5 py-0 text-[10px]">{{ securityLabel(network.security) }}</Badge>
                                    <Badge v-if="network.is_hidden" variant="outline" class="px-1.5 py-0 text-[10px]">Прихована</Badge>
                                    <Badge variant="outline" class="gap-0.5 px-1.5 py-0 text-[10px]">
                                        <component :is="network.visibility === 'public' ? Globe : Lock" class="size-2.5" />
                                        {{ network.visibility === 'public' ? 'Публічна' : 'Група' }}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <DropdownMenu v-if="network.can_manage">
                            <DropdownMenuTrigger as-child>
                                <Button variant="ghost" size="icon" class="size-8 shrink-0">
                                    <MoreHorizontal class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem @click="openEditForm(network)"><Pencil class="mr-2 size-3.5" /> Редагувати</DropdownMenuItem>
                                <DropdownMenuItem class="text-destructive" @click="deletingNetwork = network">
                                    <Trash2 class="mr-2 size-3.5" /> Видалити
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <p v-if="network.location" class="text-xs text-muted-foreground">{{ network.location }}</p>
                    <p v-if="network.notes" class="text-xs text-muted-foreground whitespace-pre-line">{{ network.notes }}</p>

                    <div v-if="network.security !== 'nopass'" class="flex items-center gap-2 rounded-md border border-dashed p-2">
                        <span class="min-w-0 flex-1 truncate font-mono text-sm">
                            {{ visiblePasswordIds.has(network.id) ? (network.password ?? '—') : '••••••••••••' }}
                        </span>
                        <button
                            v-if="network.password"
                            type="button"
                            class="shrink-0 text-muted-foreground hover:text-foreground"
                            @click="togglePasswordVisible(network.id)"
                        >
                            <EyeOff v-if="visiblePasswordIds.has(network.id)" class="size-3.5" />
                            <Eye v-else class="size-3.5" />
                        </button>
                        <button
                            v-if="network.password"
                            type="button"
                            class="shrink-0 text-muted-foreground hover:text-foreground"
                            @click="copy(network.password, `password-${network.id}`)"
                        >
                            <Check v-if="copiedField === `password-${network.id}`" class="size-3.5 text-success-foreground" />
                            <Copy v-else class="size-3.5" />
                        </button>
                    </div>
                    <p v-else class="text-xs text-muted-foreground italic">Відкрита мережа, без пароля</p>

                    <Button variant="outline" size="sm" class="gap-1.5" @click="connectModal?.openFor(network)">
                        <QrCode class="size-4" />
                        Підключитися (QR / Windows)
                    </Button>
                </div>
            </div>

            <div v-else class="flex flex-1 items-center justify-center rounded-xl border border-dashed p-10 text-center">
                <div>
                    <Wifi class="mx-auto mb-2 size-6 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">Мереж ще немає{{ canAddAny ? ' — додайте першу кнопкою вище' : '' }}</p>
                </div>
            </div>
        </div>

        <Dialog v-model:open="isFormOpen">
            <DialogContent class="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle>{{ editingNetwork ? 'Редагувати мережу' : 'Нова Wi-Fi мережа' }}</DialogTitle>
                    <DialogDescription>Публічну мережу бачать усі співробітники; групову — лише учасники обраних груп.</DialogDescription>
                </DialogHeader>
                <div class="grid max-h-[70vh] gap-4 overflow-auto py-2">
                    <div class="grid gap-2">
                        <Label for="wifi-ssid">Назва мережі (SSID)</Label>
                        <Input id="wifi-ssid" v-model="form.ssid" placeholder="напр. Office-Staff" autofocus />
                        <span v-if="form.errors.ssid" class="text-xs text-destructive">{{ form.errors.ssid }}</span>
                    </div>

                    <div class="grid grid-cols-2 items-start gap-3">
                        <div class="grid gap-2">
                            <Label>Тип захисту</Label>
                            <Select v-model="form.security">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in SECURITY_OPTIONS" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="wifi-password">Пароль</Label>
                            <Input id="wifi-password" v-model="form.password" :disabled="form.security === 'nopass'" placeholder="напр. sup3rSecret" />
                            <span v-if="form.errors.password" class="text-xs text-destructive">{{ form.errors.password }}</span>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>Діапазон <span class="font-normal text-muted-foreground">(необов'язково)</span></Label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="option in BAND_OPTIONS"
                                :key="option.value"
                                type="button"
                                class="flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-sm transition-colors"
                                :class="
                                    form.bands.includes(option.value)
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-input text-muted-foreground hover:border-primary/50'
                                "
                                @click="toggleBand(option.value)"
                            >
                                <component :is="bandIcon(option.value)" class="size-3.5" />
                                {{ option.label }}
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="wifi-location">Розташування <span class="font-normal text-muted-foreground">(необов'язково)</span></Label>
                        <Input id="wifi-location" v-model="form.location" placeholder="напр. 2 поверх, учительська" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="wifi-notes">Примітки</Label>
                        <textarea
                            id="wifi-notes"
                            v-model="form.notes"
                            rows="2"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox v-model="form.is_hidden" />
                        Прихована мережа (SSID не транслюється)
                    </label>

                    <div class="grid gap-2 border-t pt-3">
                        <Label>Видимість</Label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="option in VISIBILITY_OPTIONS"
                                :key="option.value"
                                type="button"
                                :disabled="option.value === 'public' && !canCreatePublic"
                                class="flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-sm transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                                :class="
                                    form.visibility === option.value
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-input text-muted-foreground hover:border-primary/50'
                                "
                                @click="form.visibility = option.value"
                            >
                                <component :is="option.value === 'public' ? Globe : Lock" class="size-3.5" />
                                {{ option.label }}
                            </button>
                        </div>
                        <div v-if="form.visibility === 'group'" class="flex flex-wrap gap-2">
                            <button
                                v-for="group in manageableGroups"
                                :key="group.id"
                                type="button"
                                class="rounded-md border px-2.5 py-1 text-xs transition-colors"
                                :class="
                                    form.group_ids.includes(group.id)
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-input text-muted-foreground hover:border-primary/50'
                                "
                                @click="toggleGroup(group.id)"
                            >
                                {{ group.name }}
                            </button>
                            <p v-if="manageableGroups.length === 0" class="text-xs text-muted-foreground">
                                У вас немає груп із правом «Керування».
                            </p>
                        </div>
                        <span v-if="form.errors.group_ids" class="text-xs text-destructive">{{ form.errors.group_ids }}</span>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="secondary" @click="isFormOpen = false">Скасувати</Button>
                    <Button :disabled="form.processing" @click="submitForm">{{ editingNetwork ? 'Зберегти' : 'Додати' }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <WifiConnectModal ref="connectModal" />

        <ConfirmDeleteDialog
            :open="deletingNetwork !== null"
            :processing="isDeleting"
            title="Видалити мережу?"
            :description="`«${deletingNetwork?.ssid}» більше не з'явиться в довіднику.`"
            @update:open="(open) => !open && (deletingNetwork = null)"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
