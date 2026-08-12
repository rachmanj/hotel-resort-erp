<?php

namespace App\Http\Requests\Admin;

class UpdatePromotionRequest extends StorePromotionRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('promotions.manage') ?? false;
    }
}
