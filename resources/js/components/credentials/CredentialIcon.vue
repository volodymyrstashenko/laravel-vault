<script setup lang="ts">
import { KeyRound } from '@lucide/vue';

withDefaults(
    defineProps<{
        iconUrl?: string | null;
        size?: 'sm' | 'md';
    }>(),
    { size: 'md' },
);
</script>

<template>
    <div class="flex shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted" :class="size === 'sm' ? 'size-8' : 'size-9'">
        <img v-if="iconUrl" :src="iconUrl" alt="" class="size-full object-cover" />
        <KeyRound v-else class="text-muted-foreground" :class="size === 'sm' ? 'size-4' : 'size-[18px]'" />
    </div>
</template>
