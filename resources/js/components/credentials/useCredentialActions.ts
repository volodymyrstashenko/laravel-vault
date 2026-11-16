import { useToast } from '@/composables/useToast';
import { generateTotp } from '@/lib/totp';
import type { CredentialSummary } from '@/types/vault';

interface RevealedSecrets {
    password: string | null;
    totp_secret: string | null;
}

/** Secrets are never in the list props — fetched on demand, one credential at a time. */
async function reveal(id: number): Promise<RevealedSecrets> {
    const response = await fetch(route('passwords.reveal', id), { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error('reveal failed');
    return (await response.json()) as RevealedSecrets;
}

/**
 * Safari drops clipboard writes that happen after an `await` (the user gesture is gone), but
 * accepts a ClipboardItem whose content is a promise — so the fetch runs inside the write.
 */
async function copyFromPromise(text: Promise<string | null>): Promise<boolean> {
    if (typeof ClipboardItem !== 'undefined') {
        try {
            const blob = text.then((value) => {
                if (!value) throw new Error('empty');
                return new Blob([value], { type: 'text/plain' });
            });
            await navigator.clipboard.write([new ClipboardItem({ 'text/plain': blob })]);
            return true;
        } catch {
            // Falls through: older engines without promise support in ClipboardItem.
        }
    }
    const value = await text.catch(() => null);
    if (!value) return false;
    await navigator.clipboard.writeText(value);
    return true;
}

export function siteHref(url: string): string {
    return /^[a-z][a-z0-9+.-]*:\/\//i.test(url) ? url : `https://${url}`;
}

export function useCredentialActions() {
    const { success, error } = useToast();

    async function copyText(value: string | null | undefined, label: string) {
        if (!value) return;
        await navigator.clipboard.writeText(value);
        success(`${label} скопійовано`);
    }

    async function copyPassword(credential: Pick<CredentialSummary, 'id'>) {
        const ok = await copyFromPromise(reveal(credential.id).then((s) => s.password)).catch(() => false);
        if (ok) success('Пароль скопійовано');
        else error('Не вдалося скопіювати пароль.');
    }

    async function copyTotp(credential: Pick<CredentialSummary, 'id'>) {
        const code = reveal(credential.id).then((s) => (s.totp_secret ? generateTotp(s.totp_secret) : null));
        const ok = await copyFromPromise(code).catch(() => false);
        if (ok) success('Код 2FA скопійовано');
        else error('Не вдалося отримати код 2FA.');
    }

    function openSite(url: string | null | undefined) {
        if (url) window.open(siteHref(url), '_blank', 'noopener');
    }

    return { copyText, copyPassword, copyTotp, openSite };
}
