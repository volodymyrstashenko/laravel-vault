<?php

namespace Thevps\Vault\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Thevps\Vault\Concerns\ScopedToInstitution;
use Thevps\Vault\Vault;

/**
 * An access group — the unit of authorization in the vault. Credentials and Wi-Fi networks
 * know nothing about permissions directly: access is decided entirely through membership of
 * the group(s) a record is attached to, and membership carries a LEVEL (view/edit/manage).
 *
 * Anyone authenticated can create a group (like a Kanban board) and becomes its first 'manage'
 * member automatically — there is no separate permission for this module.
 */
class CredentialGroup extends Model
{
    use HasFactory, ScopedToInstitution;

    public const ACCESS_VIEW = 'view';

    public const ACCESS_EDIT = 'edit';

    public const ACCESS_MANAGE = 'manage';

    public const ACCESS_LEVELS = [self::ACCESS_VIEW, self::ACCESS_EDIT, self::ACCESS_MANAGE];

    protected $fillable = [
        'institution_id',
        'name',
        'description',
        'created_by_id',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Vault::userModel(), 'created_by_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Vault::userModel(), 'credential_group_members', 'credential_group_id', 'user_id')
            ->withPivot('access_level')
            ->withTimestamps();
    }

    public function credentials(): BelongsToMany
    {
        return $this->belongsToMany(Credential::class, 'credential_group_credential');
    }

    public function wifiNetworks(): BelongsToMany
    {
        return $this->belongsToMany(WifiNetwork::class, 'credential_group_wifi_network');
    }

    /** This user's access level TO THIS group, or null — not a member at all. */
    public function accessLevelFor($user): ?string
    {
        if ($this->relationLoaded('members')) {
            return $this->members->firstWhere($user->getKeyName(), $user->getKey())?->pivot?->access_level;
        }

        return $this->members()->wherePivot('user_id', $user->getKey())->first()?->pivot?->access_level;
    }

    /** view=1 < edit=2 < manage=3 — a higher rank includes the lower capabilities. */
    public static function accessRank(?string $level): int
    {
        return match ($level) {
            self::ACCESS_MANAGE => 3,
            self::ACCESS_EDIT => 2,
            self::ACCESS_VIEW => 1,
            default => 0,
        };
    }
}
