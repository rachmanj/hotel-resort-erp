<?php

return [
    'statement_import_tolerance' => 0.01,

    'statement_qpdf_binary' => env('BANK_RECON_QPDF_BINARY', 'qpdf'),

    'ai' => [
        'base_url' => env('BANK_RECON_AI_BASE_URL'),
        'key' => env('BANK_RECON_AI_KEY'),
        'model' => env('BANK_RECON_AI_MODEL', 'gpt-4o-mini'),
    ],
];
