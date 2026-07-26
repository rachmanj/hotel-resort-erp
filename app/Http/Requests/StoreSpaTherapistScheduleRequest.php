<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpaTherapistScheduleRequest extends FormRequest
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
            'spa_therapist_id' => ['required', 'exists:spa_therapists,id'],
            'work_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ];
    }
}
