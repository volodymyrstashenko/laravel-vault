<?php

namespace Thevps\Vault\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCredentialGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Anyone authenticated can create a group (like a Kanban board) — becomes 'manage'
        // automatically, see CredentialGroupController::store().
        return true;
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
