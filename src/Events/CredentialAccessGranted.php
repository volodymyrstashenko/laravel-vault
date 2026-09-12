<?php

namespace Thevps\Vault\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Thevps\Vault\Models\Credential;

/**
 * Fired when a user is granted DIRECT access to a single credential (no group involved) — the
 * per-credential counterpart to CredentialGroupAccessGranted. The package does not notify anyone
 * itself; the host application listens and routes this to Telegram / mail / broadcast / nothing.
 *
 * `$user` is the host's user model instance that was granted access; `$accessLevel` is one of
 * CredentialGroup::ACCESS_*.
 */
class CredentialAccessGranted
{
    use Dispatchable;

    public function __construct(
        public Credential $credential,
        public mixed $user,
        public string $accessLevel,
    ) {}
}
