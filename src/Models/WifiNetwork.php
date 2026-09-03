<?php

namespace Thevps\Vault\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Thevps\Vault\Concerns\HasGroupAccess;
use Thevps\Vault\Concerns\ScopedToInstitution;
use Thevps\Vault\Vault;

/**
 * A company Wi-Fi network — SSID + password (encrypted at rest) + a QR payload for one-tap
 * joining. `visibility='public'` (default) means every authenticated user can see it — the
 * whole point of a "where's the Wi-Fi password" directory; `visibility='group'` restricts it
 * to members of an attached CredentialGroup (same mechanism as Credential).
 */
class WifiNetwork extends Model
{
    use HasFactory, HasGroupAccess, ScopedToInstitution;

    public const SECURITY_WPA = 'WPA';

    public const SECURITY_WEP = 'WEP';

    public const SECURITY_NONE = 'nopass';

    /** Frequency bands a network may broadcast on — dual-band networks hold several at once. */
    public const BANDS = ['2.4', '5', '6'];

    protected $fillable = [
        'institution_id',
        'visibility',
        'ssid',
        'password',
        'security',
        'is_hidden',
        'bands',
        'location',
        'notes',
        'created_by_id',
    ];

    protected $attributes = [
        'visibility' => 'public',
        'security' => self::SECURITY_WPA,
        'is_hidden' => false,
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'is_hidden' => 'boolean',
            'bands' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Vault::userModel(), 'created_by_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(CredentialGroup::class, 'credential_group_wifi_network');
    }

    /** Strongest band the network broadcasts on (6 > 5 > 2.4), or null when unspecified. */
    public function maxBand(): ?string
    {
        foreach (['6', '5', '2.4'] as $band) {
            if (in_array($band, (array) $this->bands, true)) {
                return $band;
            }
        }

        return null;
    }

    /**
     * Standard `WIFI:` URI QR payload — byte-identical to resources/js/lib/wifi.ts on the
     * frontend. Kept here too for any server-side QR/Telegram consumer in a host app; the code
     * cannot be shared between PHP and JS, the format is deliberately identical in both.
     */
    public function qrPayload(): string
    {
        $escape = fn (string $value) => preg_replace('/([\\\\;,:"])/', '\\\\$1', $value);

        $passwordPart = $this->security === self::SECURITY_NONE ? '' : 'P:'.$escape($this->password ?? '').';';

        return "WIFI:T:{$this->security};S:{$escape($this->ssid)};{$passwordPart}H:".($this->is_hidden ? 'true' : 'false').';;';
    }
}
