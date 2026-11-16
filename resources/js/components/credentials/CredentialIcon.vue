<script setup lang="ts">
import { KeyRound } from '@lucide/vue';
import { ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        iconUrl?: string | null;
        size?: 'sm' | 'md';
    }>(),
    { size: 'md' },
);

// A cached icon whose file is gone from storage would otherwise render as a broken image.
const failed = ref(false);
watch(
    () => props.iconUrl,
    () => (failed.value = false),
);
</script>

<template>
    <div class="flex shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted" :class="size === 'sm' ? 'size-8' : 'size-9'">
        <img v-if="iconUrl && !failed" :src="iconUrl" alt="" class="size-full object-cover" @error="failed = true" />
        <KeyRound v-else class="text-muted-foreground" :class="size === 'sm' ? 'size-4' : 'size-[18px]'" />
    </div>
</template>
