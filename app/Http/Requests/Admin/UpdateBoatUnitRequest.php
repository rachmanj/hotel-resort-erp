<?php

namespace App\Http\Requests\Admin;

use App\Models\BoatUnit;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBoatUnitRequest extends FormRequest
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
        /** @var BoatUnit $boatUnit */
        $boatUnit = $this->route('boat_unit') ?? $this->route('boatUnit');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:boat_units,code,'.$boatUnit->id.',id,hotel_id,'.$boatUnit->hotel_id,
            ],
            'name' => ['required', 'string', 'max:150'],
            'capacity' => ['required', 'integer', 'min:1'],
            'engine_hp' => ['required', 'integer', 'min:1'],
            'is_own' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
