<?php

namespace Thevps\Vault\Concerns;

use Thevps\Vault\Models\CredentialGroup;

/**
 * Shared access logic for records whose visibility is governed by credential groups
 * (Credential, WifiNetwork). The using model must define `groups(): BelongsToMany` and have
 * `visibility` + `created_by_id` columns.
 *
 * A record can be in several groups at once — the effective level is the UNION (MAX rank) over
 * all of them: one group granting 'manage' wins even if another only grants 'view'. There is
 * deliberately NO admin "see everything" bypass (Bitwarden/1Password principle).
 *
 * `visibility === 'public'` widens VIEW to every authenticated user; edit/manage still come
 * from group membership — except the record's own creator, who keeps 'manage' so a public
 * record created without any group still has someone able to manage it.
 *
 * A model MAY additionally define `directUsers(): BelongsToMany` (Credential does — see there)
 * to grant access to one specific person without any group at all; the level folds into the
 * same union as group membership. Models that don't define it (WifiNetwork) are unaffected.
 */
trait HasGroupAccess
{
    public function accessLevelFor($user): ?string
    {
        $best = null;

        foreach ($this->groups as $group) {
            $level = $group->accessLevelFor($user);
            if (CredentialGroup::accessRank($level) > CredentialGroup::accessRank($best)) {
                $best = $level;
            }
        }

        if (method_exists($this, 'directUsers')) {
            $directLevel = $this->directAccessLevelFor($user);
            if (CredentialGroup::accessRank($directLevel) > CredentialGroup::accessRank($best)) {
                $best = $directLevel;
            }
        }

        if ($this->visibility === 'public') {
            $isCreator = $this->created_by_id !== null && (int) $this->created_by_id === (int) $user->getKey();
            $floor = $isCreator ? CredentialGroup::ACCESS_MANAGE : CredentialGroup::ACCESS_VIEW;

            if (CredentialGroup::accessRank($best) < CredentialGroup::accessRank($floor)) {
                $best = $floor;
            }
        }

        return $best;
    }

    /** This user's directly-granted level on this specific record, or null. */
    private function directAccessLevelFor($user): ?string
    {
        if ($this->relationLoaded('directUsers')) {
            return $this->directUsers->firstWhere($user->getKeyName(), $user->getKey())?->pivot?->access_level;
        }

        return $this->directUsers()->wherePivot('user_id', $user->getKey())->first()?->pivot?->access_level;
    }

    public function canView($user): bool
    {
        return $this->accessLevelFor($user) !== null;
    }

    public function canEdit($user): bool
    {
        return CredentialGroup::accessRank($this->accessLevelFor($user)) >= CredentialGroup::accessRank(CredentialGroup::ACCESS_EDIT);
    }

    public function canManage($user): bool
    {
        return $this->accessLevelFor($user) === CredentialGroup::ACCESS_MANAGE;
    }
}
