<?php

namespace App\Libraries\Assistant;

use CodeIgniter\Config\Services;

/**
 * Optional natural-language front door for Circuit, backed by Google Gemini.
 *
 * The rule-based CommandRouter is the source of truth and the fallback: if no
 * API key is configured, or Gemini is slow/errors/returns something we cannot
 * trust, interpret() returns null and the caller falls back to the rules. The
 * model can only ever choose from the declared action registry — it never gets
 * to invent a capability, and the password/username reservation is re-checked
 * here (and again inside Actions::run).
 */
class Gemini
{
    public static function enabled(): bool
    {
        return self::apiKey() !== '';
    }

    /**
     * The key lives server-side only: set from the assistant page (stored in
     * the `assistant_config` table) or, as a fallback, from `.env`. It is never
     * sent to the browser.
     */
    public static function apiKey(): string
    {
        $key = self::setting('gemini.apiKey');
        if ($key === '') {
            $key = trim((string) env('gemini.apiKey', ''));
        }

        return trim($key);
    }

    public static function model(): string
    {
        $model = self::setting('gemini.model');
        if ($model === '') {
            $model = trim((string) env('gemini.model', ''));
        }

        return $model !== '' ? $model : 'gemini-2.0-flash';
    }

    private static function setting(string $name): string
    {
        try {
            return (string) (new \App\Models\AssistantConfigModel())->get($name, '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @return array{action:string,params:array,via:string,reply?:string}|null
     */
    public static function interpret(string $text, object $user, int $userId): ?array
    {
        if (! self::enabled() || trim($text) === '') {
            return null;
        }

        $payload = [
            'system_instruction' => ['parts' => [['text' => self::systemPrompt($user)]]],
            'contents'           => [['role' => 'user', 'parts' => [['text' => $text]]]],
            'generationConfig'   => [
                'temperature'      => 0.1,
                'maxOutputTokens'  => 300,
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $client   = Services::curlrequest(['timeout' => 12, 'http_errors' => false]);
            $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/'
                . rawurlencode(self::model()) . ':generateContent?key=' . rawurlencode(self::apiKey());

            $res    = $client->post($endpoint, ['json' => $payload]);
            $status = $res->getStatusCode();

            if ($status < 200 || $status >= 300) {
                log_message('warning', "Gemini HTTP {$status}");

                return null;
            }

            $decoded = json_decode((string) $res->getBody(), true);
            $textOut = (string) ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');

            return self::parse($textOut);
        } catch (\Throwable $e) {
            log_message('warning', 'Gemini call failed: ' . $e->getMessage());

            return null;
        }
    }

    private static function systemPrompt(object $user): string
    {
        $plans = KeyFactory::priceMap();
        $planLines = [];
        foreach ($plans as $hours => $price) {
            $planLines[] = "{$hours}h={$price}";
        }
        $plansTxt = implode(', ', $planLines);

        $pages = implode(', ', array_keys(Actions::PAGES));
        $forbidden = implode(', ', Actions::FORBIDDEN);

        return <<<TXT
You are "Circuit", the friendly voice assistant of a game license-key admin
panel. You chat naturally AND perform tasks. Reply with ONE JSON object only:
  {"action":"<name>","params":{...}}  to do something, or
  {"reply":"<short spoken sentence>"} to chat, answer, ask, or refuse.

Actions (use exactly these names; params optional unless noted):
- ping {no params}
- user.info / balance.show {no params}
- key.list {no params}
- key.generate {game:string default PUBG, duration:hours as integer, max_devices:integer}
- key.delete {user_key:string}
- key.delete_unused {no params}
- price.list {no params}
- assistant.key.generate {label:string}
- assistant.key.list {no params}
- assistant.key.revoke {id:integer}
- navigate {page:<one of: {$pages}>}

Rules:
- Small talk, greetings, thanks, questions about what you can do, or anything
  that is not a task → use {"reply":...}. Never stay silent; always answer.
- Speak in the user's language (English or Hinglish). Keep replies under 15 words.
- Convert durations to hours: 2 days = 48, 3 days = 72, 1 week = 168, 1 month = 720.
- Available durations are: {$plansTxt}. For key.generate pick the closest
  available duration; if the user gives none, omit "duration" so the panel
  picks the cheapest plan. Never invent a duration that is not listed.
- NEVER perform or help with: {$forbidden}. If asked, refuse in one short sentence.
- The user is "{$user->username}" (level {$user->level}).
- If a sentence mixes chat and a task, do the task and keep the spoken result short.
TXT;
    }

    /**
     * @return array{action:string,params:array,via:string,reply?:string}|null
     */
    private static function parse(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Tolerate a fenced block even though responseMimeType should prevent it.
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $raw = $m[0];
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return null;
        }

        if (isset($data['reply']) && is_string($data['reply'])) {
            $reply = trim($data['reply']);

            return $reply === '' ? null : ['action' => '_reply', 'params' => [], 'via' => 'gemini', 'reply' => $reply];
        }

        $name = strtolower(trim((string) ($data['action'] ?? '')));
        $registry = Actions::list();

        // Unknown or reserved: let the rules have a go, otherwise give up.
        if (! isset($registry[$name]) || in_array($name, Actions::FORBIDDEN, true)) {
            return null;
        }

        $rawParams = isset($data['params']) && is_array($data['params']) ? $data['params'] : [];
        $params = [];
        foreach (array_keys($registry[$name]['params']) as $key) {
            if (! array_key_exists($key, $rawParams)) {
                continue;
            }
            $value = $rawParams[$key];
            $params[$key] = in_array($key, ['duration', 'max_devices', 'id'], true)
                ? (int) $value
                : trim((string) $value);
        }

        return ['action' => $name, 'params' => $params, 'via' => 'gemini'];
    }
}