<script setup lang="ts">
import { ContextMenuItem, ContextMenuSeparator } from '@/components/ui/context-menu';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { canEditWith, canManageWith } from '@/lib/credentials';
import type { CredentialSummary } from '@/types/vault';
import { router } from '@inertiajs/vue3';
import { Copy, ExternalLink, Eye, KeyRound, Pencil, ShieldCheck, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { useCredentialActions } from './useCredentialActions';

/** The same items for the right-click menu and the "⋯" dropdown of a list row. */
const props = defineProps<{ credential: CredentialSummary; kind: 'context' | 'dropdown' }>();
const emit = defineEmits<{ open: []; delete: [] }>();

const Item = computed(() => (props.kind === 'context' ? ContextMenuItem : DropdownMenuItem));
const Separator = computed(() => (props.kind === 'context' ? ContextMenuSeparator : DropdownMenuSeparator));

const { copyText, copyPassword, copyTotp, openSite } = useCredentialActions();
</script>

<template>
    <component :is="Item" @select="emit('open')"><Eye /> Відкрити</component>
    <component :is="Separator" />
    <component :is="Item" v-if="credential.login" @select="copyText(credential.login, 'Логін')"><Copy /> Копіювати логін</component>
    <component :is="Item" @select="copyPassword(credential)"><KeyRound /> Копіювати пароль</component>
    <component :is="Item" v-if="credential.has_totp" @select="copyTotp(credential)"><ShieldCheck /> Копіювати код 2FA</component>
    <component :is="Item" v-if="credential.url" @select="openSite(credential.url)"><ExternalLink /> Відкрити сайт</component>
    <template v-if="canEditWith(credential.access_level)">
        <component :is="Separator" />
        <component :is="Item" @select="router.visit(route('passwords.edit', credential.id))"><Pencil /> Редагувати</component>
        <component :is="Item" v-if="canManageWith(credential.access_level)" class="text-destructive focus:text-destructive" @select="emit('delete')">
            <Trash2 /> Видалити
        </component>
    </template>
</template>
