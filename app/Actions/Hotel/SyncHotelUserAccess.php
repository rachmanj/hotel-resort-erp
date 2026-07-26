<?php

namespace App\Actions\Hotel;

use App\Models\Hotel;

class SyncHotelUserAccess
{
    /**
     * @param  list<int>  $userIds
     */
    public function __invoke(Hotel $hotel, array $userIds): void
    {
        $hotel->users()->sync($userIds);
    }
}
