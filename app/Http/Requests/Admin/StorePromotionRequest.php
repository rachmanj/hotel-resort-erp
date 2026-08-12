<?php

namespace App\Http\Requests\Admin;

use App\Enums\PackageItemType;
use App\Enums\PromoDiscountType;
use App\Enums\PromotionConditionType;
use App\Enums\PromotionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('promotions.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->promotionRules();
    }

    /**
     * @return array<string, mixed>
     */
    protected function promotionRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'promo_type' => ['required', Rule::enum(PromotionType::class)],
            'discount_type' => ['required', Rule::enum(PromoDiscountType::class)],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'lead_time_min_days' => ['nullable', 'integer', 'min:0'],
            'lead_time_max_days' => ['nullable', 'integer', 'min:0'],
            'min_nights' => ['nullable', 'integer', 'min:1'],
            'max_nights' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['required', 'date', 'after_or_equal:valid_from'],
            'is_stackable' => ['sometimes', 'boolean'],
            'requires_code' => ['sometimes', 'boolean'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'room_type_ids' => ['sometimes', 'array'],
            'room_type_ids.*' => ['integer', 'exists:room_types,id'],
            'conditions' => ['sometimes', 'array'],
            'conditions.*.condition_type' => ['required', Rule::enum(PromotionConditionType::class)],
            'conditions.*.value' => ['required', 'array'],
            'package_items' => ['sometimes', 'array'],
            'package_items.*.item_type' => ['required', Rule::enum(PackageItemType::class)],
            'package_items.*.reference_id' => ['required', 'integer', 'min:1'],
            'package_items.*.quantity' => ['required', 'integer', 'min:1'],
            'package_items.*.package_value' => ['required', 'numeric', 'min:0'],
        ];
    }
}
