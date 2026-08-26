<?php

namespace App\Http\Requests\Admin;

use App\Enums\DivePackageType;
use App\Models\DivePackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDivePackageRequest extends FormRequest
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
        /** @var DivePackage $divePackage */
        $divePackage = $this->route('dive_package') ?? $this->route('divePackage');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:dive_packages,code,'.$divePackage->id.',id,hotel_id,'.$divePackage->hotel_id,
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
