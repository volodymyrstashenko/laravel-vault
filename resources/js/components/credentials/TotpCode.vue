<script setup lang="ts">
import { generateTotp, secondsRemainingInStep } from '@/lib/totp';
import { Check, Copy } from '@lucide/vue';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    secret: string;
}>();

const code = ref('······');
const error = ref(false);
const copied = ref(false);
const remaining = ref(secondsRemainingInStep());
let intervalId: number | undefined;

async function refreshCode() {
    try {
        code.value = await generateTotp(props.secret);
        error.value = false;
    } catch {
        error.value = true;
        code.value = 'помилка';
    }
}

// Копіюємо ПОТОЧНИЙ код без пробілів (на екрані він розбитий на 3+3 для читання) — код
// живе 30с, тож кнопка бере саме те, що показано в цю мить.
async function copyCode() {
    if (error.value || !/^\d+$/.test(code.value)) return;

    await navigator.clipboard.writeText(code.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
}

function tick() {
    const next = secondsRemainingInStep();
    // Just crossed into a new 30-second window — recompute.
    if (next > remaining.value) refreshCode();
    remaining.value = next;
}

onMounted(() => {
    refreshCode();
    intervalId = window.setInterval(tick, 1000);
});
onUnmounted(() => {
    if (intervalId) clearInterval(intervalId);
});
watch(() => props.secret, refreshCode);

const circumference = 2 * Math.PI * 10;
</script>

<template>
    <div class="flex items-center gap-3">
        <span class="font-mono text-2xl font-semibold tracking-widest text-foreground" :class="error && 'text-sm text-destructive'">
            {{ error ? code : code.match(/.{1,3}/g)?.join(' ') }}
        </span>
        <button v-if="!error" type="button" class="text-muted-foreground hover:text-foreground" title="Копіювати код" @click="copyCode">
            <Check v-if="copied" class="size-4 text-success-foreground" />
            <Copy v-else class="size-4" />
        </button>
        <div v-if="!error" class="relative size-6 shrink-0">
            <svg viewBox="0 0 24 24" class="size-6 -rotate-90">
                <circle cx="12" cy="12" r="10" fill="none" stroke="hsl(var(--muted))" stroke-width="3" />
                <circle
                    cx="12"
                    cy="12"
                    r="10"
                    fill="none"
                    :stroke="remaining <= 5 ? 'hsl(var(--destructive))' : 'hsl(var(--primary))'"
                    stroke-width="3"
                    :stroke-dasharray="circumference"
                    :stroke-dashoffset="circumference * (1 - remaining / 30)"
                    class="transition-all duration-1000 ease-linear"
                />
            </svg>
        </div>
    </div>
</template>
