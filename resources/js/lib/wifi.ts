import { Wifi, WifiHigh, WifiLow } from '@lucide/vue';
import type { Component } from 'vue';
import type { WifiBand, WifiSecurity } from '@/types/vault';

export const SECURITY_OPTIONS: { value: WifiSecurity; label: string }[] = [
    { value: 'WPA', label: 'WPA/WPA2/WPA3' },
    { value: 'WEP', label: 'WEP' },
    { value: 'nopass', label: 'Без пароля (відкрита)' },
];

export function securityLabel(security: WifiSecurity): string {
    return SECURITY_OPTIONS.find((o) => o.value === security)?.label ?? security;
}

export const BAND_OPTIONS: { value: WifiBand; label: string }[] = [
    { value: '2.4', label: '2.4 ГГц' },
    { value: '5', label: '5 ГГц' },
    { value: '6', label: '6 ГГц' },
];

/** Strongest band the network broadcasts on (6 > 5 > 2.4), or null. */
export function maxBand(bands: WifiBand[] | null | undefined): WifiBand | null {
    for (const band of ['6', '5', '2.4'] as WifiBand[]) {
        if (bands?.includes(band)) return band;
    }
    return null;
}

export function bandLabel(band: WifiBand | null | undefined): string {
    if (!band) return '';
    return BAND_OPTIONS.find((o) => o.value === band)?.label ?? `${band} ГГц`;
}

/** A different icon per band — the list shows the one for the strongest band. */
export function bandIcon(band: WifiBand | null | undefined): Component {
    if (band === '6') return Wifi;
    if (band === '5') return WifiHigh;
    return WifiLow; // '2.4' or fallback
}

/** Escapes the special characters of the WIFI: QR format (backslash, semicolon, comma,
 *  colon, quote) — without this an SSID/password containing them would break the payload. */
function escapeWifiField(value: string): string {
    return value.replace(/([\\;,:"])/g, '\\$1');
}

/**
 * Builds the standard Wi-Fi join QR payload
 * (`WIFI:T:<type>;S:<ssid>;P:<password>;H:<true|false>;;`) — the format modern phone cameras
 * (Android/iOS) recognise as "join this network". Computed on the frontend — the backend only
 * returns the raw network fields, the QR image is never stored.
 */
export function buildWifiQrPayload(network: { ssid: string; password: string | null; security: WifiSecurity; is_hidden: boolean }): string {
    const type = network.security;
    const passwordPart = type === 'nopass' ? '' : `P:${escapeWifiField(network.password ?? '')};`;

    return `WIFI:T:${type};S:${escapeWifiField(network.ssid)};${passwordPart}H:${network.is_hidden ? 'true' : 'false'};;`;
}
