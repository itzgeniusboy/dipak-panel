<?php

namespace App\Libraries\Assistant;

/**
 * Turns a spoken sentence into an action + parameters.
 *
 * Deliberately small and rule-based: an assistant that promises "full control"
 * must be predictable, not clever. English and Hinglish both work because that
 * is how the panel's owner actually talks to it.
 */
class CommandRouter
{
    /**
     * @return array{action:string, params:array, reply?:string, confidence:int}
     */
    public static function route(string $text): array
    {
        $t = ' ' . mb_strtolower(trim($text)) . ' ';

        // Social, so the assistant never feels like a command line.
        if (preg_match('/\b(hi|hello|hey|namaste|hii|yo)\b/', $t) && mb_strlen(trim($text)) < 24) {
            return self::reply('Hi! Ask me to generate a key, list keys, or open any page.');
        }
        if (preg_match('/\b(thanks|thank you|shukriya|dhanyavad|thx)\b/', $t)) {
            return self::reply('Anytime.');
        }

        // Reserved: never let a phrase map to password/username work.
        if (preg_match('/\b(password|passwd|username|user name)\b/', $t)) {
            return self::reply('I keep away from passwords and usernames. Change those yourself.');
        }

        // Delete unused keys (must beat the generic "delete key" rule).
        if (preg_match('/\b(delete|remove|clear|hata|mita|saaf)\b.*\b(unused|expired|dead|purani|bekaar)\b/', $t)
            || preg_match('/\b(unused|expired)\b.*\b(keys?)\b/', $t)) {
            return ['action' => 'key.delete_unused', 'params' => [], 'confidence' => 80];
        }

        // Assistant / API keys (beat the generic "key" rules).
        if (preg_match('/\b(assistant|api|circuit)\b.*\bkeys?\b/', $t) || preg_match('/\bkeys?\b.*\b(assistant|api)\b/', $t)) {
            if (preg_match('/\b(list|show|dikha|batao|kitni|kitne)\b/', $t)) {
                return ['action' => 'assistant.key.list', 'params' => [], 'confidence' => 75];
            }
            if (preg_match('/\b(revoke|delete|remove|hata|band)\b/', $t)) {
                if (preg_match('/\b(\d+)\b/', $t, $m)) {
                    return ['action' => 'assistant.key.revoke', 'params' => ['id' => (int) $m[1]], 'confidence' => 80];
                }

                return self::reply('Tell me the key id to revoke.');
            }

            return ['action' => 'assistant.key.generate', 'params' => ['label' => 'Voice key'], 'confidence' => 70];
        }

        // Delete one specific key.
        if (preg_match('/\b(delete|remove|del|hata|mita)\b.*\bkeys?\b\s*([A-Za-z0-9]{4,})/is', $text, $m)
            || preg_match('/\bkeys?\b\s*([A-Za-z0-9]{4,})\s*\b(delete|remove|hata|mita|del)\b/is', $text, $m)) {
            return ['action' => 'key.delete', 'params' => ['user_key' => $m[2]], 'confidence' => 80];
        }

        // List keys.
        if (preg_match('/\b(list|show|dikha|dikhao|batao|kitni|kitne|view|check)\b/', $t)
            && preg_match('/\b(keys?|licen[cs]es?)\b/', $t)) {
            return ['action' => 'key.list', 'params' => [], 'confidence' => 80];
        }

        // Generate a key: a making verb plus something key-ish (a key, a
        // duration or a device count), so "banao 2 din ke liye 3 device" works.
        if (preg_match('/\b(generate|create|make|nayi|naya|bana|banao|banaao|banade|issue|chahiye|do|de|dedo)\b/', $t)
            && preg_match('/\b(keys?|licen[cs]es?|hours?|hrs?|ghante|ghanta|days?|din|dino|weeks?|hafte|hafta|months?|mahine|mahina|devices?|phones?|slots?)\b/', $t)
            && ! preg_match('/\b(delete|remove|hata|mita|list|show|dikha|batao)\b/', $t)) {
            return ['action' => 'key.generate', 'params' => self::keyParams($t), 'confidence' => 80];
        }

        // Navigate — explicit page words with an "open" verb. Runs before the
        // noun rules so "open price" opens the page, not the price list.
        if (preg_match('/\b(open|go to|goto|khol|kholo|jao|le chalo|launch|start|navigate)\b/', $t)) {
            if (preg_match('/\b(licen[cs]e|licen[cs]es)\b/', $t)) {
                return ['action' => 'navigate', 'params' => ['page' => 'license'], 'confidence' => 80];
            }
            // Spoken names that do not match a page slug word-for-word.
            $alias = [
                'online system' => 'server',
                'system'        => 'server',
                'online lib'    => 'lib',
                'two[- ]?factor' => 'twofa',
                '2fa'           => 'twofa',
                'approvals?'    => 'approvals',
                'manage users'  => 'users',
                'referrals?'    => 'referral',
                'extend duration' => 'extend',
            ];
            foreach ($alias as $words => $slug) {
                if (preg_match('/\b' . $words . '\b/', $t)) {
                    return ['action' => 'navigate', 'params' => ['page' => $slug], 'confidence' => 80];
                }
            }
            foreach (Actions::PAGES as $slug => $path) {
                if (preg_match('/\b' . preg_quote($slug, '/') . '\b/', $t)) {
                    return ['action' => 'navigate', 'params' => ['page' => $slug], 'confidence' => 80];
                }
            }
        }

        // Balance / identity.
        if (preg_match('/\b(balance|saldo|wallet|paise|credit)\b/', $t)) {
            return ['action' => 'balance.show', 'params' => [], 'confidence' => 80];
        }
        if (preg_match('/\b(who am i|profile|my account|mera account|meri info|account info)\b/', $t)) {
            return ['action' => 'user.info', 'params' => [], 'confidence' => 75];
        }

        // Prices.
        if (preg_match('/\b(price|prices|plan|plans|rate|rates|kimat)\b/', $t)) {
            return ['action' => 'price.list', 'params' => [], 'confidence' => 75];
        }

        // Rename panel (admin only; Actions enforces it).
        if (preg_match('/\b(rename|naam|name)\b.*\b(panel|site|website)\b/', $t)
            && preg_match('/\b(to|as|kar)\b\s+(.+)$/i', trim($text), $m)) {
            return ['action' => 'settings.site_name', 'params' => ['name' => trim($m[2])], 'confidence' => 60];
        }

        return self::reply("I didn't catch an action in that. Try: \"generate a key for 1 day\".");
    }

    /** @return array{game:string,duration:int,max_devices:int} */
    private static function keyParams(string $t): array
    {
        $duration = 0; // 0 = let the panel pick its cheapest plan

        if (preg_match('/\b(\d+)\s*(hour|hours|hr|hrs|h|ghante|ghanta|ghanto)\b/', $t, $m)) {
            $duration = (int) $m[1];
        } elseif (preg_match('/\b(\d+)\s*(day|days|din|dino)\b/', $t, $m)) {
            $duration = (int) $m[1] * 24;
        } elseif (preg_match('/\b(\d+)\s*(week|weeks|hafte|hafta|hafte)\b/', $t, $m)) {
            $duration = (int) $m[1] * 168;
        } elseif (preg_match('/\b(\d+)\s*(month|months|mahine|mahina)\b/', $t, $m)) {
            $duration = (int) $m[1] * 720;
        }

        $devices = 1;
        if (preg_match('/\b(\d+)\s*(device|devices|phone|phones|slot|slots)\b/', $t, $m)) {
            $devices = max(1, (int) $m[1]);
        }

        $game = 'PUBG';
        if (preg_match('/\b(pubg|bgmi|pubg mobile)\b/', $t)) {
            $game = 'PUBG';
        }

        return ['game' => $game, 'duration' => $duration, 'max_devices' => $devices];
    }

    private static function reply(string $text): array
    {
        return ['action' => '_reply', 'params' => [], 'reply' => $text, 'confidence' => 100];
    }
}