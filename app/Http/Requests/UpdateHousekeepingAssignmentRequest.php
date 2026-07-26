<?php

namespace App\Http\Requests;

use App\Enums\HousekeepingAssignmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHousekeepingAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('housekeeping.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(HousekeepingAssignmentStatus::class)],
        ];
    }
}
