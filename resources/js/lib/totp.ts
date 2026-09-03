const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/** RFC 4648 base32 decode — secrets are often pasted with spaces / in lowercase. */
function base32Decode(input: string): Uint8Array {
    const clean = input.replace(/\s+/g, '').replace(/=+$/, '').toUpperCase();
    let bits = '';
    for (const char of clean) {
        const value = BASE32_ALPHABET.indexOf(char);
        if (value === -1) continue; // ignore junk rather than throw — the secret may have a stray char
        bits += value.toString(2).padStart(5, '0');
    }
    const bytes: number[] = [];
    for (let i = 0; i + 8 <= bits.length; i += 8) {
        bytes.push(parseInt(bits.slice(i, i + 8), 2));
    }
    return new Uint8Array(bytes);
}

/**
 * RFC 6238 TOTP (HMAC-SHA1, 30s step, 6 digits) — the same algorithm as Google/Microsoft/
 * GitHub Authenticator. Computed entirely client-side via Web Crypto (secure context — the app
 * is on https anyway); the secret never leaves the browser again, it already arrived in the
 * page props (same trust level as the password itself, both need 'view' access).
 */
export async function generateTotp(secretBase32: string, stepSeconds = 30, digits = 6): Promise<string> {
    const key = base32Decode(secretBase32);
    if (key.length === 0) {
        throw new Error('Порожній або некоректний TOTP-секрет');
    }

    const counter = Math.floor(Date.now() / 1000 / stepSeconds);
    const counterBytes = new ArrayBuffer(8);
    new DataView(counterBytes).setUint32(4, counter, false);

    const cryptoKey = await crypto.subtle.importKey('raw', key as BufferSource, { name: 'HMAC', hash: 'SHA-1' }, false, ['sign']);
    const signature = new Uint8Array(await crypto.subtle.sign('HMAC', cryptoKey, counterBytes));

    const offset = signature[signature.length - 1] & 0x0f;
    const binaryCode =
        ((signature[offset] & 0x7f) << 24) |
        ((signature[offset + 1] & 0xff) << 16) |
        ((signature[offset + 2] & 0xff) << 8) |
        (signature[offset + 3] & 0xff);

    return (binaryCode % 10 ** digits).toString().padStart(digits, '0');
}

export function secondsRemainingInStep(stepSeconds = 30): number {
    return stepSeconds - (Math.floor(Date.now() / 1000) % stepSeconds);
}
