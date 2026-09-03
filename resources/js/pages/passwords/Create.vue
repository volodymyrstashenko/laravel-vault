<script setup lang="ts">
import CredentialForm from '@/components/credentials/CredentialForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { CredentialGroupRef } from '@/types/vault';
import { Head } from '@inertiajs/vue3';

defineProps<{
    manageableGroups: CredentialGroupRef[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Паролі', href: route('passwords.index') },
    { title: 'Новий пароль', href: route('passwords.create') },
];
</script>

<template>
    <Head title="Новий пароль" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <CredentialForm :manageable-groups="manageableGroups" />
    </AppLayout>
</template>
