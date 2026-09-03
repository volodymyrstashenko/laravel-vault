<?php

namespace Thevps\Vault\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Thevps\Vault\Vault;

/**
 * Institution-scopes every query whenever the host has configured
 * `config('vault.institution_resolver')` — a complete no-op (no `where` added) for single-tenant
 * hosts, so this never touches an install that never sets the resolver. New rows are stamped
 * with the current tenant id on create.
 */
trait ScopedToInstitution
{
    public static function bootScopedToInstitution(): void
    {
        static::addGlobalScope('institution', function (Builder $query) {
            if ($institutionId = Vault::currentInstitutionId()) {
                $query->where($query->getModel()->getTable().'.institution_id', $institutionId);
            }
        });

        static::creating(function ($model) {
            if ($model->institution_id === null && ($institutionId = Vault::currentInstitutionId())) {
                $model->institution_id = $institutionId;
            }
        });
    }
}
