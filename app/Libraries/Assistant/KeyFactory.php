<?php

namespace App\Libraries\Assistant;

use App\Models\HistoryModel;
use App\Models\KeysModel;
use App\Models\PriceModel;
use App\Models\UserModel;

/**
 * Server-side key generation for the assistant / API.
 *
 * This mirrors the rules the Keys controller enforces for the UI: the same
 * game list, the same DB-driven prices, the reseller 2-device ceiling, the
 * balance check, the saldo deduction and the history row. Keeping the rules
 * here lets the API and the voice command behave exactly like the buttons.
 */
class KeyFactory
{
    public static function gameList(): array
    {
        return ['PUBG' => 'PUBG Mobile'];
    }

    /** @return array<int,int> duration(hours) => price */
    public static function priceMap(): array
    {
        $fallback = [
            1 => 30, 2 => 50, 5 => 80, 24 => 150, 72 => 350, 168 => 600,
            336 => 850, 720 => 1200, 1440 => 1800, 2160 => 3600, 2880 => 4800, 8760 => 14400,
        ];

        try {
            $model  = new PriceModel();
            $fields = $model->getFieldNames($model->getTable());
            $builder = $model->builder();
            if (in_array('is_active', $fields, true)) {
                $builder->where('is_active', 1);
            }
            $rows = $builder->get()->getResultArray();

            $map = [];
            foreach ($rows as $row) {
                $d = (int) ($row['duration'] ?? 0);
                $p = (int) ($row['price'] ?? 0);
                if ($d > 0 && $p >= 0) {
                    $map[$d] = $p;
                }
            }
            if ($map) {
                ksort($map, SORT_NUMERIC);

                return $map;
            }
        } catch (\Throwable $e) {
            log_message('warning', 'KeyFactory price load failed: ' . $e->getMessage());
        }

        return $fallback;
    }

    /**
     * @return array{ok:bool, code:int, message:string, key:?string, fees:int}
     */
    public static function generate(object $user, int $userId, array $params): array
    {
        helper('text');
        helper('nata');

        $unlimited = isUnlimitedBalance($user);

        $games   = array_keys(self::gameList());
        $game    = (string) ($params['game'] ?? ($games[0] ?? 'PUBG'));
        $drtn    = (int) ($params['duration'] ?? 0);
        $maxd    = (int) ($params['max_devices'] ?? 1);

        if (! in_array($game, $games, true)) {
            return self::fail("Unknown game.");
        }
        if ($maxd < 1) {
            return self::fail("Devices must be at least 1.");
        }
        if ((int) $user->level === 3 && $maxd > 2) {
            return self::fail("A reseller can create keys for at most 2 devices.", 403);
        }

        $prices = self::priceMap();

        // No duration asked for: use the cheapest plan the panel actually sells,
        // so "generate a key" always does something sensible.
        if ($drtn <= 0) {
            $drtn = (int) array_key_first($prices);
        }
        if ($drtn < 1) {
            return self::fail("Duration must be at least 1 hour.");
        }

        // Spoken durations rarely match the panel's plans exactly ("2 days" =
        // 48h, but only 24h/72h are sold). Snap to the closest real plan, so a
        // voice order never fails on an off-menu duration.
        $asked = $drtn;
        if (! isset($prices[$drtn])) {
            $drtn = self::nearest($prices, $drtn);
            if ($drtn < 1) {
                return self::fail("No plans are configured yet.");
            }
        }

        $fees  = $prices[$drtn] * $maxd;
        if ($fees <= 0) {
            return self::fail("This plan is not priced.", 403);
        }
        // The owner (level 1) is unlimited: never blocked, never charged.
        if (! $unlimited && (int) $user->saldo - $fees < 0) {
            return self::fail("Not enough balance — this key costs {$fees}.", 402);
        }

        $keysModel = new KeysModel();
        $license   = random_string('alnum', 15);
        if ($keysModel->getKeysGame(['user_key' => $license, 'game' => $game])) {
            $license = random_string('alnum', 15);
        }

        $idKeys = $keysModel->insert([
            'game'        => $game,
            'user_key'    => $license,
            'duration'    => $drtn,
            'max_devices' => $maxd,
            'registrator' => $user->username,
        ]);

        $userModel = new UserModel();
        if (! $unlimited) {
            $userModel->update($userId, ['saldo' => (int) $user->saldo - $fees]);
        }

        (new HistoryModel())->insert([
            'keys_id' => $idKeys,
            'user_do' => $user->username,
            'info'    => "{$game}|" . substr($license, 0, 5) . "|{$drtn}|{$maxd}",
        ]);

        $chargeNote = $unlimited ? 'owner — unlimited, nothing charged' : "{$fees} charged";
        $swapNote   = ($asked > 0 && $asked !== $drtn) ? " (closest to the {$asked}h you asked for)" : '';

        return [
            'ok'      => true,
            'code'    => 201,
            'message' => "Key generated — {$license} ({$drtn}h, {$maxd} device" . ($maxd > 1 ? 's' : '') . "){$swapNote}, {$chargeNote}.",
            'key'     => $license,
            'fees'    => $unlimited ? 0 : $fees,
        ];
    }

    /** Closest sold duration; ties go to the longer plan. */
    private static function nearest(array $prices, int $want): int
    {
        $best = 0;
        $bestDiff = PHP_INT_MAX;
        foreach (array_keys($prices) as $h) {
            $diff = abs($h - $want);
            if ($diff < $bestDiff || ($diff === $bestDiff && $h > $best)) {
                $best = (int) $h;
                $bestDiff = $diff;
            }
        }

        return $best;
    }

    private static function fail(string $message, int $code = 400): array
    {
        return ['ok' => false, 'code' => $code, 'message' => $message, 'key' => null, 'fees' => 0];
    }
}