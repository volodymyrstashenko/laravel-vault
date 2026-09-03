<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { bandIcon, bandLabel } from '@/lib/wifi';
import type { WifiNetworkQuick } from '@/types/vault';
import { Link } from '@inertiajs/vue3';
import { Copy, Eye, EyeOff, QrCode, Wifi } from '@lucide/vue';
import { ref } from 'vue';
import WifiConnectModal from './WifiConnectModal.vue';

/**
 * Header quick-access widget — a Wi-Fi icon that drops down the list of networks the current
 * user may see (all `public` ones + their `group` ones). Lazy-loads on first open. Mount it in
 * the host's header component next to the theme toggle.
 */
const networks = ref<WifiNetworkQuick[]>([]);
const isLoading = ref(false);
const isLoaded = ref(false);

async function fetchNetworks() {
    if (isLoading.value || isLoaded.value) return;
    isLoading.value = true;
    try {
        const res = await fetch(route('wifi.quick'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const data = await res.json();
        networks.value = data.networks ?? [];
        isLoaded.value = true;
    } catch {
        // Silent — this is an auxiliary widget, the full /wifi page stays available.
    } finally {
        isLoading.value = false;
    }
}

function onOpenChange(open: boolean) {
    if (open) fetchNetworks();
}

const revealed = ref<Record<number, boolean>>({});
function toggle(id: number) {
    revealed.value[id] = !revealed.value[id];
}

const copiedId = ref<number | null>(null);
function copyPassword(network: WifiNetworkQuick) {
    if (!network.password) return;
    navigator.clipboard.writeText(network.password);
    copiedId.value = network.id;
    setTimeout(() => (copiedId.value = null), 1500);
}

const connectModal = ref<InstanceType<typeof WifiConnectModal> | null>(null);
</script>

<template>
    <DropdownMenu @update:open="onOpenChange">
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="size-8" title="Wi-Fi">
                <Wifi class="size-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80">
            <DropdownMenuLabel>Wi-Fi мережі</DropdownMenuLabel>
            <DropdownMenuSeparator />

            <div v-if="isLoading" class="py-6 text-center text-sm text-muted-foreground">Завантаження…</div>
            <div v-else-if="networks.length === 0" class="py-6 text-center text-sm text-muted-foreground">Мереж Wi-Fi ще немає</div>

            <div v-else class="max-h-[320px] overflow-y-auto">
                <DropdownMenuItem
                    v-for="network in networks"
                    :key="network.id"
                    class="flex flex-col items-start gap-1.5 p-2.5"
                    @select.prevent
                >
                    <div class="flex w-full items-center gap-2">
                        <component :is="bandIcon(network.max_band)" class="size-3.5 shrink-0 text-muted-foreground" />
                        <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ network.ssid }}</span>
                        <span v-if="network.max_band" class="shrink-0 text-[10px] text-muted-foreground">{{ bandLabel(network.max_band) }}</span>
                        <span v-else-if="network.location" class="shrink-0 truncate text-[10px] text-muted-foreground">{{ network.location }}</span>
                    </div>
                    <div class="flex w-full items-center gap-1.5">
                        <span
                            v-if="network.password && network.security !== 'nopass'"
                            class="min-w-0 flex-1 truncate rounded border border-border/50 bg-muted/40 px-2 py-1 font-mono text-xs"
                        >
                            {{ revealed[network.id] ? network.password : '••••••••' }}
                        </span>
                        <span v-else class="flex-1 text-[10px] text-muted-foreground/60">Без пароля</span>
                        <button
                            v-if="network.password && network.security !== 'nopass'"
                            type="button"
                            class="shrink-0 p-1 text-muted-foreground hover:text-foreground"
                            @click="toggle(network.id)"
                        >
                            <EyeOff v-if="revealed[network.id]" class="size-3.5" />
                            <Eye v-else class="size-3.5" />
                        </button>
                        <button
                            v-if="network.password && network.security !== 'nopass'"
                            type="button"
                            class="shrink-0 p-1 text-muted-foreground hover:text-foreground"
                            :class="copiedId === network.id && 'text-success-foreground'"
                            @click="copyPassword(network)"
                        >
                            <Copy class="size-3.5" />
                        </button>
                        <button type="button" class="shrink-0 p-1 text-primary hover:text-primary/80" @click="connectModal?.openFor(network)">
                            <QrCode class="size-3.5" />
                        </button>
                    </div>
                </DropdownMenuItem>
            </div>

            <DropdownMenuSeparator />
            <div class="p-2">
                <Link :href="route('wifi.index')" class="block text-center text-xs text-primary hover:underline">Переглянути всі мережі →</Link>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>

    <WifiConnectModal ref="connectModal" />
</template>
