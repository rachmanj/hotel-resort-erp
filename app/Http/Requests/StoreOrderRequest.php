<?php

namespace App\Http\Requests;

use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fb.orders.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'restaurant_table_id' => [
                Rule::requiredIf(fn () => $this->input('order_type') === OrderType::DineIn->value),
                'nullable',
                'exists:restaurant_tables,id',
            ],
            'reservation_id' => [
                Rule::requiredIf(fn () => in_array($this->input('order_type'), [OrderType::RoomService->value], true) || $this->boolean('charged_to_room')),
                'nullable',
                'exists:reservations,id',
            ],
            'charged_to_room' => ['boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
