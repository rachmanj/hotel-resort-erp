<?php

return [
    /*
     * Days a tentative booking is held before reservations:expire-holds cancels it.
     */
    'hold_days' => (int) env('RESERVATION_HOLD_DAYS', 3),
];
