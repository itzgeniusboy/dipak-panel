<?php
namespace App\Libraries;

use App\Models\LoginThrottleModel;
use CodeIgniter\HTTP\RequestInterface;
use Config\Database;

class LoginThrottle
{
    // defaults (env se override)
    private const DEFAULT_MAX_ATTEMPTS  = 5;
    private const DEFAULT_BLOCK_SECONDS = 900; // 15m

    /** Stable device fingerprint (cookie + IP/UA fallback) */
    public static function fingerprint(RequestInterface $request): string
    {
        $cookieName = 'devid';
        $devid = $request->getCookie($cookieName);
        if (!$devid) {
            $raw = ($request->getIPAddress() ?: '0.0.0.0') . '|' . $request->getUserAgent();
            $devid = 'd-'.sha1($raw);
            service('response')->setCookie($cookieName, $devid, 60*60*24*365, '', '/', '', false, true);
        }
        return substr(sha1($devid), 0, 32);
    }

    /** Seconds remaining if blocked, else 0 */
    public static function blockedFor(RequestInterface $request): int
    {
        $fp   = self::fingerprint($request);
        $now  = time();
        $row  = (new LoginThrottleModel())->getByFp($fp);
        if ($row && (int)$row['blocked_until'] > $now) {
            return (int)$row['blocked_until'] - $now;
        }
        return 0;
    }

    /** Attempts left before block triggers */
    public static function attemptsLeft(RequestInterface $request): int
    {
        $max = (int) env('login.device.maxAttempts', self::DEFAULT_MAX_ATTEMPTS);
        $fp  = self::fingerprint($request);
        $row = (new LoginThrottleModel())->getByFp($fp);
        $attempts = $row ? (int)$row['attempts'] : 0;
        $left = $max - $attempts;
        return $left > 0 ? $left : 0;
    }

    /** Call on WRONG credentials */
    public static function recordFailure(RequestInterface $request): void
    {
        $max   = (int) env('login.device.maxAttempts',  self::DEFAULT_MAX_ATTEMPTS);
        $block = (int) env('login.device.blockSeconds', self::DEFAULT_BLOCK_SECONDS);

        $db = Database::connect();
        $fp = self::fingerprint($request);
        $now = time();

        $db->transStart();

        // Row lock for this fingerprint
        $row = $db->query('SELECT * FROM login_throttle WHERE fingerprint = ? FOR UPDATE', [$fp])->getRowArray();

        if (!$row) {
            // new row, attempt #1
            $db->table('login_throttle')->insert([
                'fingerprint'    => $fp,
                'attempts'       => 1,
                'blocked_until'  => 0,
                'last_attempt_at'=> $now,
            ]);
        } else {
            // if still blocked, do nothing but keep timestamps updated
            if ((int)$row['blocked_until'] > $now) {
                $db->table('login_throttle')
                   ->where('fingerprint', $fp)
                   ->update(['last_attempt_at' => $now]);
            } else {
                // increment attempts
                $attempts = (int)$row['attempts'] + 1;
                $blockedUntil = 0;

                // reach threshold -> block and reset attempts to 0
                if ($attempts >= $max) {
                    $blockedUntil = $now + $block;
                    $attempts = 0;
                }

                $db->table('login_throttle')
                   ->where('fingerprint', $fp)
                   ->update([
                       'attempts'       => $attempts,
                       'blocked_until'  => $blockedUntil,
                       'last_attempt_at'=> $now,
                   ]);
            }
        }

        $db->transComplete();
    }

    /** Call on SUCCESSFUL login */
    public static function clearOnSuccess(RequestInterface $request): void
    {
        $fp  = self::fingerprint($request);
        $db  = Database::connect();
        $db->table('login_throttle')
           ->where('fingerprint', $fp)
           ->update([
               'attempts'      => 0,
               'blocked_until' => 0,
               'last_attempt_at'=> time(),
           ]);
    }
}