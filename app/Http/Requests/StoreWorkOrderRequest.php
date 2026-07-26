<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkOrderRequest extends FormRequest
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
            'maintenance_request_id' => ['nullable', 'integer', 'exists:maintenance_requests,id', 'required_without:asset_id'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id', 'required_without:maintenance_request_id'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'description' => ['required', 'string'],
        ];
    }
}
