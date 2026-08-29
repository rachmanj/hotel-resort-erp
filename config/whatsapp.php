<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenWA WhatsApp Gateway
    |--------------------------------------------------------------------------
    |
    | Gateway self-hosted (OpenWA) yang dipakai untuk kirim pesan WhatsApp.
    | base_url: alamat gateway (Tailscale IP GEEKOM), api_key dari log startup,
    | session_id: UUID session OpenWA.
    |
    */

    'base_url' => env('OPENWA_BASE_URL', 'http://100.87.250.66:2785'),

    'api_key' => env('OPENWA_API_KEY'),

    'session_id' => env('OPENWA_SESSION_ID'),

];
