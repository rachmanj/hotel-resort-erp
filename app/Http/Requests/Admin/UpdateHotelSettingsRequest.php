<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHotelSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'max:64'],
            'default_checkin_time' => ['required', 'date_format:H:i'],
            'default_checkout_time' => ['required', 'date_format:H:i'],
        ];
    }
}
