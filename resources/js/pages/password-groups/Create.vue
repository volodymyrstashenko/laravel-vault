<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Паролі', href: route('passwords.index') },
    { title: 'Групи паролів', href: route('password-groups.index') },
    { title: 'Нова група', href: route('password-groups.create') },
];

const form = useForm({
    name: '',
    description: '',
});

function submit() {
    form.post(route('password-groups.store'));
}
</script>

<template>
    <Head title="Нова група паролів" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex h-full flex-1 flex-col gap-4 overflow-hidden" @submit.prevent="submit">
            <div class="flex-1 overflow-auto">
                <div class="flex w-full flex-col gap-4 p-6">
                    <div class="rounded-lg border bg-card p-5">
                        <h2 class="mb-4 text-sm font-semibold">Нова група паролів</h2>
                        <p class="mb-4 text-xs text-muted-foreground">Ви автоматично станете учасником із правом «Керування».</p>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="name">Назва <span class="text-destructive">*</span></Label>
                                <Input id="name" v-model="form.name" placeholder="напр. Хостинг та домени" />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="col-span-full grid gap-2">
                                <Label for="description">Опис</Label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="3"
                                    class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                />
                                <InputError :message="form.errors.description" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-sidebar-border/70 px-6 py-3">
                <Button type="button" variant="outline" as-child>
                    <a :href="route('password-groups.index')">Скасувати</a>
                </Button>
                <Button type="submit" :disabled="form.processing">Створити групу</Button>
            </div>
        </form>
    </AppLayout>
</template>
