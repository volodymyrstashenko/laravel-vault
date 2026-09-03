<?php

namespace Thevps\Vault\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Thevps\Vault\Models\CredentialGroup;

/**
 * Fired when a user is added to a credential group. The package does not notify anyone itself —
 * the host application listens and routes this to Telegram / mail / broadcast / nothing.
 *
 * `$user` is the host's user model instance that was granted access; `$accessLevel` is one of
 * CredentialGroup::ACCESS_*.
 */
class CredentialGroupAccessGranted
{
    use Dispatchable;

    public function __construct(
        public CredentialGroup $group,
        public mixed $user,
        public string $accessLevel,
    ) {}
}
