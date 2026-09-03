<script setup lang="ts">
/**
 * Renders a Wi-Fi join QR code on a <canvas> — the 'qrcode' library works entirely in the
 * browser (no request to any external service, important: the payload contains the password).
 * Redraws when the payload changes (e.g. after editing a network without a page reload).
 */
import QRCode from 'qrcode';
import { onMounted, ref, watch } from 'vue';

const props = withDefaults(defineProps<{ payload: string; size?: number }>(), { size: 176 });

const canvas = ref<HTMLCanvasElement | null>(null);

async function render() {
    if (!canvas.value) return;
    await QRCode.toCanvas(canvas.value, props.payload, {
        width: props.size,
        margin: 1,
        color: { dark: '#000000ff', light: '#ffffffff' },
    });
}

onMounted(render);
watch(() => props.payload, render);
</script>

<template>
    <canvas ref="canvas" class="rounded-md" :width="size" :height="size" />
</template>
