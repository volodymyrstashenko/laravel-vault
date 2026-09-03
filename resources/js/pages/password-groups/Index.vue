<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { accessLevelBadgeVariant, accessLevelLabel } from '@/lib/credentials';
import type { BreadcrumbItem } from '@/types';
import type { CredentialGroupSummary } from '@/types/vault';
import { Head, Link } from '@inertiajs/vue3';
import { KeyRound, Plus, Users } from '@lucide/vue';

defineProps<{
    groups: CredentialGroupSummary[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Паролі', href: route('passwords.index') },
    { title: 'Групи паролів', href: route('password-groups.index') },
];
</script>

<template>
    <Head title="Групи паролів" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm text-muted-foreground">Групи визначають, хто бачить чи редагує паролі. Показано лише групи, де ви учасник.</p>
                <Button as-child size="sm" class="gap-1.5">
                    <Link :href="route('password-groups.create')">
                        <Plus class="size-4" />
                        Нова група
                    </Link>
                </Button>
            </div>

            <div v-if="groups.length === 0" class="flex flex-1 flex-col items-center justify-center gap-2 text-muted-foreground">
                <KeyRound class="size-10 opacity-20" />
                <p class="text-sm">Ви ще не в жодній групі паролів.</p>
            </div>

            <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="group in groups"
                    :key="group.id"
                    :href="route('password-groups.show', group.id)"
                    class="flex flex-col gap-3 rounded-lg border bg-card p-4 transition-colors hover:border-primary/50"
                >
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-medium text-foreground">{{ group.name }}</h3>
                        <Badge :variant="accessLevelBadgeVariant(group.access_level)" class="shrink-0">{{
                            accessLevelLabel(group.access_level)
                        }}</Badge>
                    </div>
                    <p v-if="group.description" class="line-clamp-2 text-xs text-muted-foreground">{{ group.description }}</p>
                    <div class="mt-auto flex items-center gap-4 text-xs text-muted-foreground">
                        <span class="flex items-center gap-1"><Users class="size-3.5" /> {{ group.members_count }}</span>
                        <span class="flex items-center gap-1"><KeyRound class="size-3.5" /> {{ group.credentials_count }}</span>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
