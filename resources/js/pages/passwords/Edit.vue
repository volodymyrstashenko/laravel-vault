<script setup lang="ts">
import CredentialForm from '@/components/credentials/CredentialForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { CredentialDetail, CredentialGroupRef } from '@/types/vault';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    credential: CredentialDetail;
    manageableGroups: CredentialGroupRef[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Паролі', href: route('passwords.index') },
    { title: props.credential.name, href: route('passwords.show', props.credential.id) },
    { title: 'Редагування', href: route('passwords.edit', props.credential.id) },
]);
</script>

<template>
    <Head :title="`Редагування — ${credential.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <CredentialForm :credential="credential" :manageable-groups="manageableGroups" />
    </AppLayout>
</template>
