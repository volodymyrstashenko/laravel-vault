// No characters that are easy to confuse when copying by hand (0/O, 1/l/I etc.).
const CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%^&*()-_=+';

/** Cryptographically strong generator — crypto.getRandomValues, not Math.random(). */
export function generatePassword(length = 20): string {
    const bytes = new Uint32Array(length);
    crypto.getRandomValues(bytes);
    return Array.from(bytes, (n) => CHARSET[n % CHARSET.length]).join('');
}
