<?php

namespace App\Http\Requests\Admin;

use App\Enums\DivePackageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDivePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('dive-center.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hotelId = session('current_hotel_id');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:dive_packages,code,NULL,id,hotel_id,'.$hotelId,
            ],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(DivePackageType::class)],
            'price_per_person' => ['required', 'numeric', 'min:0'],
            'min_pax' => ['required', 'integer', 'min:1'],
            'includes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
