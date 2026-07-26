<?php

namespace App\Actions\Hotel;

use App\Models\Hotel;

class UpdateHotel
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Hotel $hotel, array $data): Hotel
    {
        foreach (['default_checkin_time', 'default_checkout_time'] as $field) {
            if (isset($data[$field]) && strlen((string) $data[$field]) === 5) {
                $data[$field] = $data[$field].':00';
            }
        }

        $hotel->update($data);

        return $hotel->fresh();
    }
}
