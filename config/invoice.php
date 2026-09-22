<?php

return [
    'company_name' => env('INVOICE_COMPANY_NAME', 'PRATASABA RESORT'),

    'title' => 'INVOICE',

    'terms_title' => 'Terms and Conditions',

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

    'terms' => [
        'Payment is transferred to one of the company bank accounts listed above.',
        'Down Payment minimum 50% of the invoice total is required to secure the booking.',
        'Full payment is due H-14 before the check in date.',
        'Cancellation up to H-14 forfeits the down payment; cancellation after H-14 is charged in full.',
        'All payments are non refundable.',
    ],

    'signatures' => [
        'prepared_by' => 'Invoice Prepared by',
        'approved_by' => 'Invoice Approved by',
        'received_by' => 'Invoice Received by',
    ],
];
