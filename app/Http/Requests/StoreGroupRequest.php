<?php

namespace App\Http\Requests;

use App\Enums\GroupInvoiceMode;
use App\Enums\GroupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('groups.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'group_type' => ['required', Rule::enum(GroupType::class)],
            'pic_guest_id' => ['nullable', 'integer', 'exists:guests,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'invoice_mode' => ['sometimes', Rule::enum(GroupInvoiceMode::class)],
            'deposit_amount' => ['sometimes', 'numeric', 'min:0'],
            'special_requests' => ['nullable', 'string'],
            'arrival_date' => ['required_if:group_type,single_multi_room', 'nullable', 'date'],
            'departure_date' => ['required_if:group_type,single_multi_room', 'nullable', 'date', 'after:arrival_date'],
            'room_selections' => ['required_if:group_type,single_multi_room', 'array', 'min:1'],
            'room_selections.*.room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'room_selections.*.room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'room_selections.*.rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
            'reservation_data.guest_id' => ['nullable', 'integer', 'exists:guests,id'],
            'reservation_data.adults' => ['sometimes', 'integer', 'min:1'],
            'reservation_data.children' => ['sometimes', 'integer', 'min:0'],
            'reservation_data.special_requests' => ['nullable', 'string'],
        ];
    }
}
