<?php

namespace Thevps\Vault\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Thevps\Vault\Models\CredentialGroup;

class UpdateCredentialGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CredentialGroup $group */
        $group = $this->route('credential_group');

        return $group->accessLevelFor($this->user()) === CredentialGroup::ACCESS_MANAGE;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'назва групи'];
    }
}
