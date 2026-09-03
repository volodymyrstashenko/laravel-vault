<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { buildWifiQrPayload } from '@/lib/wifi';
import type { WifiSecurity } from '@/types/vault';
import { Check, Copy, Download, Laptop, Smartphone, Wifi } from '@lucide/vue';
import { computed, ref } from 'vue';
import WifiQrCode from './WifiQrCode.vue';

interface WifiNetworkLike {
    id: number;
    ssid: string;
    password: string | null;
    security: WifiSecurity;
    is_hidden: boolean;
}

const isOpen = ref(false);
const network = ref<WifiNetworkLike | null>(null);
const copied = ref<string | null>(null);

function openFor(target: WifiNetworkLike) {
    network.value = target;
    isOpen.value = true;
}

const qrPayload = computed(() => (network.value ? buildWifiQrPayload(network.value) : ''));
const windowsProfileHref = computed(() => (network.value ? route('wifi.windows-profile', network.value.id) : null));
const netshCommand = 'netsh wlan add profile filename="ШЛЯХ_ДО_ФАЙЛУ.xml"';

function copy(value: string | null, key: string) {
    if (!value) return;
    navigator.clipboard.writeText(value);
    copied.value = key;
    setTimeout(() => (copied.value = null), 1500);
}

defineExpose({ openFor });
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Wifi class="size-5 text-primary" />
                    {{ network?.ssid }}
                </DialogTitle>
                <DialogDescription>Підключення до мережі Wi-Fi.</DialogDescription>
            </DialogHeader>

            <Tabs default-value="qr" class="w-full">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="qr" class="flex items-center gap-1.5">
                        <Smartphone class="size-3.5" /> Телефон (QR)
                    </TabsTrigger>
                    <TabsTrigger value="windows" class="flex items-center gap-1.5">
                        <Laptop class="size-3.5" /> Windows
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="qr" class="space-y-3 pt-4">
                    <div class="flex flex-col items-center gap-2">
                        <div class="flex size-[200px] items-center justify-center rounded-xl border bg-white p-2">
                            <WifiQrCode v-if="qrPayload" :payload="qrPayload" :size="184" />
                        </div>
                        <p class="max-w-[280px] text-center text-xs text-muted-foreground">
                            Наведіть камеру телефона (Android/iOS) на QR-код — пристрій запропонує підключитися автоматично, без ручного введення пароля.
                        </p>
                    </div>
                </TabsContent>

                <TabsContent value="windows" class="space-y-4 pt-4">
                    <p class="text-xs text-muted-foreground">
                        Завантажте файл профілю мережі та імпортуйте його — Windows додасть мережу до відомих і підключатиметься автоматично.
                    </p>
                    <Button v-if="windowsProfileHref" as-child variant="outline" class="w-full">
                        <a :href="windowsProfileHref" target="_blank" rel="noopener noreferrer">
                            <Download class="mr-2 size-4" /> Завантажити профіль (.xml)
                        </a>
                    </Button>

                    <div class="space-y-1.5">
                        <Label class="text-xs text-muted-foreground">Команда для імпорту (PowerShell/CMD)</Label>
                        <div class="flex gap-2">
                            <Input :model-value="netshCommand" readonly class="h-9 font-mono text-xs" />
                            <Button type="button" variant="outline" size="icon" class="h-9 shrink-0" @click="copy(netshCommand, 'netsh')">
                                <Check v-if="copied === 'netsh'" class="size-3.5 text-success-foreground" />
                                <Copy v-else class="size-3.5" />
                            </Button>
                        </div>
                        <p class="text-[11px] text-muted-foreground">
                            Замініть <span class="font-mono">ШЛЯХ_ДО_ФАЙЛУ.xml</span> на шлях до завантаженого файлу і виконайте від імені адміністратора.
                        </p>
                    </div>
                </TabsContent>
            </Tabs>

            <div v-if="network?.password && network.security !== 'nopass'" class="space-y-1.5 border-t pt-3">
                <Label class="text-xs text-muted-foreground">Пароль (вручну)</Label>
                <div class="flex gap-2">
                    <Input :model-value="network.password" readonly class="h-9 font-mono" />
                    <Button type="button" variant="outline" size="icon" class="h-9 shrink-0" @click="copy(network?.password ?? null, 'pw')">
                        <Check v-if="copied === 'pw'" class="size-3.5 text-success-foreground" />
                        <Copy v-else class="size-3.5" />
                    </Button>
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="ghost" @click="isOpen = false">Закрити</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
