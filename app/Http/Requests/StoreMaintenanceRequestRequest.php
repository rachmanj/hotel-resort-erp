<?php

namespace App\Http\Requests;

use App\Enums\MaintenancePriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'room_id' => ['nullable', 'integer', 'exists:rooms,id', 'required_without:asset_id'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id', 'required_without:room_id'],
            'description' => ['required', 'string'],
            'priority' => ['nullable', Rule::enum(MaintenancePriority::class)],
        ];
    }
}
