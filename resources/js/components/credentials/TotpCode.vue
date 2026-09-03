<script setup lang="ts">
import { generateTotp, secondsRemainingInStep } from '@/lib/totp';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    secret: string;
}>();

const code = ref('······');
const error = ref(false);
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
