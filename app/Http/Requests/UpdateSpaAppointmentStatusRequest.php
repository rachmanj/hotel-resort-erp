<?php

namespace App\Http\Requests;

use App\Enums\SpaAppointmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSpaAppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('spa.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(SpaAppointmentStatus::class)],
        ];
    }
}
