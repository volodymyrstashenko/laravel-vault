<?php

namespace Thevps\Vault\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        // "Create" = 'manage' on each chosen group — checked in the controller (needs the loop
        // over group_ids, which FormRequest::authorize() cannot reach before validate()).
        // A 'public' credential with no groups needs no group at all.
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
        ];
    }
}
