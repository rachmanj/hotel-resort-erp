<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBoatUnitRequest extends FormRequest
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
                'unique:boat_units,code,NULL,id,hotel_id,'.$hotelId,
            ],
            'name' => ['required', 'string', 'max:150'],
            'capacity' => ['required', 'integer', 'min:1'],
            'engine_hp' => ['required', 'integer', 'min:1'],
            'is_own' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
