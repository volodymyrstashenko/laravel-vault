<?php

namespace Thevps\Vault\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Thevps\Vault\Models\CredentialGroup;
use Thevps\Vault\Vault;

class StoreCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        // "Create" = 'manage' on each chosen group — checked in the controller (needs the loop
        // over group_ids, which FormRequest::authorize() cannot reach before validate()).
        // A 'public' credential with no groups needs no group at all. Direct access grants
        // (direct_access) need no authorization of their own — the creator can share their own
        // new credential with anyone, same as attaching it to a group they manage.
        return true;
    }

    public function rules(): array
    {
        return [
            'visibility' => ['required', Rule::in(['group', 'public'])],
            'name' => ['required', 'string', 'max:255'],
            'login' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
            'totp_secret' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'url', 'max:2048'],
            'notes' => ['nullable', 'string'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*.label' => ['required', 'string', 'max:255'],
            'custom_fields.*.value' => ['nullable'],
            'custom_fields.*.type' => ['required', Rule::in(['text', 'hidden', 'boolean'])],
            'group_ids' => ['required_unless:visibility,public', 'array'],
            'group_ids.*' => [Rule::exists('credential_groups', 'id')],
            // Initial direct-access grants, chosen at creation time (Credential::directUsers()) —
            // same view/edit/manage vocabulary as group membership. user_id is nullable (not
            // required) because the UI adds a blank row before a user is picked — the controller
            // filters those out rather than rejecting the whole submission over an empty row.
            'direct_access' => ['nullable', 'array'],
            'direct_access.*.user_id' => ['nullable', Rule::exists(Vault::usersTable(), 'id')],
            'direct_access.*.access_level' => ['required_with:direct_access.*.user_id', Rule::in(CredentialGroup::ACCESS_LEVELS)],
        ];
    }

    public function attributes(): array
    {
        return [
            'login' => 'логін',
            'totp_secret' => 'TOTP секрет',
            'url' => 'посилання',
            'notes' => 'нотатки',
            'custom_fields' => 'додаткові поля',
            'group_ids' => 'групи',
            'direct_access' => 'персональний доступ',
        ];
    }
}
