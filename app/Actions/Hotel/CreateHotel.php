<?php

namespace App\Actions\Hotel;

use App\Models\Hotel;

class CreateHotel
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data): Hotel
    {
        $data = $this->normalizeTimes($data);

        return Hotel::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeTimes(array $data): array
    {
        foreach (['default_checkin_time', 'default_checkout_time'] as $field) {
            if (isset($data[$field]) && strlen((string) $data[$field]) === 5) {
                $data[$field] = $data[$field].':00';
            }
        }

        return $data;
    }
}
