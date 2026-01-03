<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorized via Controller Gate policy
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();
        if (!$user) {
            return;
        }

        $roleName = $user->role?->name;

        // Security Enforcement: Admin Unit (before validation)
        if ($roleName === 'admin_unit') {
            $effectiveUnitId = $user->currentUnitId();
            $this->merge([
                'scope_type' => 'unit',
                'organization_unit_id' => $effectiveUnitId,
            ]);
        }

        // Ensure unit_id is null for global scopes
        if (in_array($this->input('scope_type'), ['global_all', 'global_officers'], true)) {
            $this->merge(['organization_unit_id' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'scope_type' => ['required', Rule::in(['global_all', 'global_officers', 'unit'])],
            'organization_unit_id' => [
                'nullable',
                'exists:organization_units,id',
                // Required if scope is unit
                Rule::requiredIf(fn() => $this->input('scope_type') === 'unit'),
                // Prohibited if scope is global (unless we want to allow storing it harmlessly, but safer to prohibit)
                Rule::prohibitedIf(fn() => in_array($this->input('scope_type'), ['global_all', 'global_officers'])),
            ],
            'is_active' => ['boolean'],
            'pin_to_dashboard' => ['boolean'],
        ];
    }
}
