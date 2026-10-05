<?php
namespace App\Libraries;

class Telegram
{
    protected $token;

    public function __construct(?string $token = null)
    {
        // Prefer TELEGRAM_BOT_TOKEN from .env
        $this->token = $token ?: getenv('TELEGRAM_BOT_TOKEN');
    }

    public function isConfigured(): bool
    {
        return !empty($this->token);
    }

    /** Send a plain text message to a Telegram chat ID */
    public function sendMessage(string $chatId, string $text): bool
    {
        if (!$this->isConfigured()) return false;
        if (!$chatId) return false;

        $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
        $payload = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 10,
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            curl_close($ch);
            return false;
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }
}
