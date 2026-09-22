<?php

return [
    'number_prefix' => env('PROFORMA_NUMBER_PREFIX', 'PRATA'),

    'company_name' => env('PROFORMA_COMPANY_NAME', 'PRATASABA RESORT'),

    'room_line_note' => '*Room Include Breakfast 2 pax',

    'prepared_by' => env('PROFORMA_PREPARED_BY', 'Marketing'),

    'bank_accounts' => [
        [
            'bank_name' => 'BCA',
            'account_no' => '781 0285758',
            'account_name' => 'Pratasaba Resort',
        ],
        [
            'bank_name' => 'Mandiri',
            'account_no' => '149 0075575557',
            'account_name' => 'Pratasaba Resort',
        ],
    ],

    'payment_terms' => [
        'Down Payment minimum 50% of the purchase total is required to secure the booking.',
        'Full payment is due H-14 before the check in date.',
        'All payments are non refundable.',
    ],
];
