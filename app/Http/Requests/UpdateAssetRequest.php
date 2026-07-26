<?php

namespace App\Http\Requests;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'asset_type' => ['sometimes', 'required', Rule::enum(AssetType::class)],
            'room_id' => ['sometimes', 'nullable', 'integer', 'exists:rooms,id'],
            'location' => ['sometimes', 'nullable', 'string', 'max:150'],
            'purchased_at' => ['sometimes', 'nullable', 'date'],
            'warranty_until' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::enum(AssetStatus::class)],
        ];
    }
}
