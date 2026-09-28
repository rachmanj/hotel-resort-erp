<?php

return [
    'statement_import_tolerance' => 0.01,

    'ai' => [
        'base_url' => env('BANK_RECON_AI_BASE_URL'),
        'key' => env('BANK_RECON_AI_KEY'),
        'model' => env('BANK_RECON_AI_MODEL', 'gpt-4o-mini'),
    ],
];
