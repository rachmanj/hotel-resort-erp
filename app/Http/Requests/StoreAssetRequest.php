<?php

namespace App\Http\Requests;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'asset_type' => ['required', Rule::enum(AssetType::class)],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'location' => ['nullable', 'string', 'max:150'],
            'purchased_at' => ['nullable', 'date'],
            'warranty_until' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(AssetStatus::class)],
        ];
    }
}
