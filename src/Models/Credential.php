<?php

namespace Thevps\Vault\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Thevps\Vault\Concerns\HasGroupAccess;
use Thevps\Vault\Concerns\ScopedToInstitution;
use Thevps\Vault\Vault;

/**
 * One password-manager entry. No ACL of its own — visibility/rights come from accessLevelFor()
 * (see HasGroupAccess): the highest level the user holds across ALL groups this credential is
 * attached to, or (for `visibility='public'`) at least 'view' for everyone.
 */
class Credential extends Model implements HasMedia
{
    use HasFactory, HasGroupAccess, InteractsWithMedia, ScopedToInstitution;

    protected $fillable = [
        'institution_id',
        'visibility',
        'name',
        'login',
        'password',
        'totp_secret',
        'url',
        'notes',
        'custom_fields',
        'created_by_id',
    ];

    protected $attributes = [
        'visibility' => 'group',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'totp_secret' => 'encrypted',
            'notes' => 'encrypted',
            // [{label, value, type}] — encrypted:array encrypts the whole JSON blob at once.
            'custom_fields' => 'encrypted:array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Vault::userModel(), 'created_by_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(CredentialGroup::class, 'credential_group_credential');
    }

    /**
     * `icon` — the service favicon, one per credential, fetched & cached server-side
     * (CredentialController::refreshIcon()). `attachments` — arbitrary user files (recovery
     * codes, a scan…), unlimited.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('icon')->singleFile();
        $this->addMediaCollection('attachments');
    }

    public function iconUrl(): ?string
    {
        return $this->getFirstMediaUrl('icon') ?: null;
    }

    public function attachmentsList(): array
    {
        return $this->getMedia('attachments')->map(fn ($media) => [
            'id' => $media->id,
            'file_name' => $media->file_name,
            'size' => $media->size,
            'mime_type' => $media->mime_type,
            'url' => $media->getUrl(),
            'created_at' => $media->created_at?->toIso8601String(),
        ])->values()->all();
    }
}
