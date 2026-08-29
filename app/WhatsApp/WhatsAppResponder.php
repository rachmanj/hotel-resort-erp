<?php

namespace App\WhatsApp;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP client untuk OpenWA gateway (self-hosted WhatsApp API gateway).
 * Config: config/whatsapp.php (base_url, api_key, session_id).
 */
class WhatsAppResponder
{
    /**
     * Kirim pesan teks ke satu nomor WhatsApp.
     *
     * @param  string  $phone  nomor format internasional tanpa '+' (mis. 6281234567890)
     * @param  string  $text   isi pesan
     * @return array<string, mixed>
     */
    public function sendText(string $phone, string $text): array
    {
        $baseUrl = config('whatsapp.base_url');
        $apiKey = config('whatsapp.api_key');
        $sessionId = config('whatsapp.session_id');

        if (empty($baseUrl) || empty($apiKey) || empty($sessionId)) {
            throw new RuntimeException('WhatsApp gateway is not configured (config/whatsapp.php).');
        }

        $chatId = preg_replace('/\D+/', '', $phone);

        if ($chatId === '') {
            throw new RuntimeException('Invalid WhatsApp phone number.');
        }

        $response = Http::baseUrl($baseUrl)
            ->withHeaders(['X-API-Key' => $apiKey])
            ->timeout(20)
            ->post("/api/sessions/{$sessionId}/messages/send-text", [
                'chatId' => "{$chatId}@c.us",
                'text' => $text,
            ]);

        if ($response->failed()) {
            throw new RuntimeException("OpenWA send failed: {$response->status()} {$response->body()}");
        }

        return $response->json() ?? [];
    }

    /**
     * Normalisasi nomor Indonesia ke format internasional (62...).
     * '0812xxx' → '62812xxx', '+62812xxx' → '62812xxx', '62812xxx' → tetap.
     * Mengembalikan null jika nomor tidak valid (panjang 10-15 digit).
     */
    public static function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }
}
