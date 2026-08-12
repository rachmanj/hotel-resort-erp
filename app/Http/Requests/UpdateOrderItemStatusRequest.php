<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderItemStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->can('fb.orders.update_status') || $user->can('fb.manage')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowed = ['preparing', 'ready'];

        if ($this->user()?->can('fb.manage')) {
            $allowed[] = 'served';
        }

        return [
            'status' => ['required', Rule::in($allowed)],
        ];
    }
}
