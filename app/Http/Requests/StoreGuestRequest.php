<?php

namespace App\Http\Requests;

use App\Enums\GuestIdType;
use App\Enums\VipTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('guests.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'id_number' => ['nullable', 'string', 'max:50'],
            'id_type' => ['nullable', Rule::enum(GuestIdType::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
            'nationality' => ['nullable', 'string', 'max:60'],
            'vip_tier' => ['nullable', Rule::enum(VipTier::class)],
            'is_blacklisted' => ['boolean'],
            'blacklist_reason' => ['nullable', 'string', 'required_if:is_blacklisted,true'],
        ];
    }
}
