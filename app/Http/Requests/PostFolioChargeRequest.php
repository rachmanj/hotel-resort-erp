<?php

namespace App\Http\Requests;

use App\Enums\DivePackageType;
use App\Enums\DiveRateItemType;
use App\Models\DivePackage;
use App\Models\DiveRateItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PostFolioChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.post') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'revenue_category_id' => ['nullable', 'integer', 'exists:revenue_categories,id'],
            'dive_package_id' => ['nullable', 'integer', 'exists:dive_packages,id'],
            'dive_boat_rate_item_id' => [
                Rule::requiredIf(fn (): bool => $this->requiresBoatRoute()),
                'nullable',
                'integer',
                'exists:dive_rate_items,id',
            ],
            'rate_item_id' => [
                'nullable',
                'integer',
                'exists:dive_rate_items,id',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('rate_item_id') && $this->filled('dive_package_id')) {
                $validator->errors()->add(
                    'rate_item_id',
                    'Choose either a dive package or a daily trip rate item, not both.',
                );
            }

            $rateItemId = $this->integer('rate_item_id');
            if ($rateItemId !== 0) {
                $rateItem = DiveRateItem::query()->find($rateItemId);
                if ($rateItem !== null && ! in_array($rateItem->item_type, [
                    DiveRateItemType::DailyTrip,
                    DiveRateItemType::DailyTripRental,
                    DiveRateItemType::Guide,
                ], true)) {
                    $validator->errors()->add(
                        'rate_item_id',
                        'Selected rate item is not a daily trip, rental, or guide charge.',
                    );
                }
            }

            if (! $this->requiresBoatRoute()) {
                return;
            }

            $boatRateId = $this->integer('dive_boat_rate_item_id');
            if ($boatRateId === 0) {
                return;
            }

            $boatRate = DiveRateItem::query()->find($boatRateId);
            if ($boatRate === null) {
                return;
            }

            if ($boatRate->item_type !== DiveRateItemType::BoatRent || $boatRate->route === null) {
                $validator->errors()->add(
                    'dive_boat_rate_item_id',
                    'Selected boat rate is invalid for a dive package charge.',
                );
            }
        });
    }

    private function requiresBoatRoute(): bool
    {
        $packageId = $this->integer('dive_package_id');
        if ($packageId === 0) {
            return false;
        }

        $package = DivePackage::query()->find($packageId);

        return $package?->type === DivePackageType::DivePackage;
    }
}
