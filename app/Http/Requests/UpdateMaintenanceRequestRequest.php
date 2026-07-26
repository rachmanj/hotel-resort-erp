<?php

namespace App\Http\Requests;

use App\Enums\MaintenancePriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'priority' => ['sometimes', Rule::enum(MaintenancePriority::class)],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'description' => ['sometimes', 'required', 'string'],
        ];
    }
}
