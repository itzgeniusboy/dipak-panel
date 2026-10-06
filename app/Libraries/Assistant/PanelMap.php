<?php

namespace App\Libraries\Assistant;

/**
 * The panel's on-screen map.
 *
 * Circuit does not reach behind the UI — it walks the same pages a person does.
 * This class turns a spoken action into a list of UI steps (goto / fill / tap /
 * text / wait / read) that the on-screen mascot performs by pressing the real
 * buttons and typing into the real fields. When no hand-driven route is known
 * it returns null and the caller falls back to the server-side action.
 */
class PanelMap
{
    /** slug => [path, nav marker] */
    private const PAGES = [
        'dashboard' => ['dashboard', 'nav:dashboard'],
        'keys'      => ['keys', 'nav:keys'],
        'generate'  => ['keys/generate', 'nav:generate'],
        'balance'   => ['balance', 'nav:balance'],
        'license'   => ['license', 'nav:license'],
        'price'     => ['price', 'nav:price'],
        'settings'  => ['settings', 'nav:settings'],
        'assistant' => ['assistant', 'nav:assistant'],
        'server'    => ['Server', ''],
        'lib'       => ['lib', ''],
        'extend'    => ['ExtendDuration', ''],
        'twofa'     => ['twofa', ''],
        'approvals' => ['account_approve', ''],
        'users'     => ['admin/manage-users', ''],
        'referral'  => ['admin/create-referral', ''],
    ];

    /**
     * @return array<int,array<string,mixed>>|null
     */
    public static function plan(string $action, array $params, object $user): ?array
    {
        switch (strtolower(trim($action))) {
            case 'navigate':
                $page = strtolower(trim((string) ($params['page'] ?? '')));
                if (! isset(self::PAGES[$page])) {
                    return null;
                }

                return [self::goto($page, 'Opening ' . $page . '.')];

            case 'key.list':
                return [
                    self::goto('keys', 'Opening your keys.'),
                    self::say('Here are your keys.'),
                ];

            case 'price.list':
                return [
                    self::goto('price', 'Opening price plans.'),
                    self::say('These are the plans on sale.'),
                ];

            case 'balance.show':
            case 'user.info':
                return [self::goto('dashboard', 'Opening your dashboard.')];

            case 'key.generate':
                return self::generateKey($params);

            case 'key.delete':
                $key = trim((string) ($params['user_key'] ?? ''));
                if ($key === '') {
                    return null;
                }

                return [
                    self::goto('keys', 'Opening your keys.'),
                    [
                        'type'  => 'inRow',
                        'find'  => '[data-copy="' . addslashes($key) . '"]',
                        'click' => 'button.btn--danger, button[data-confirm]',
                        'row'   => 'tr',
                    ],
                    self::say('Deleted ' . $key . '.'),
                ];

            case 'key.delete_unused':
                return [
                    self::goto('keys', 'Opening your keys.'),
                    ['type' => 'text', 'text' => 'Del unused', 'speak' => 'Clearing unused keys.'],
                    ['type' => 'wait', 'ms' => 900],
                    self::say('Unused keys cleared.'),
                ];

            case 'settings.site_name':
                $name = trim((string) ($params['name'] ?? ''));
                if ($name === '' || ! in_array((int) $user->level, [1, 2], true)) {
                    return null;
                }

                return [
                    self::goto('settings', 'Opening settings.'),
                    ['type' => 'fill', 'selector' => '#base_name', 'value' => $name],
                    ['type' => 'text', 'text' => 'Update site name', 'speak' => 'Saving the new name.'],
                    ['type' => 'wait', 'ms' => 900],
                    self::say('Panel renamed to ' . $name . '.'),
                ];
        }

        return null;
    }

    /**
     * Open the generator, type the order into the real fields and press the
     * button. The panel then shows the new key in a dialog, which Circuit reads
     * out. Duration is snapped to a plan the panel actually sells.
     */
    private static function generateKey(array $params): array
    {
        $asked   = (int) ($params['duration'] ?? 0);
        $hours   = KeyFactory::nearestPlan($asked);
        $devices = max(1, (int) ($params['max_devices'] ?? 1));
        $game    = (string) ($params['game'] ?? 'PUBG');

        $note = [];
        if ($asked > 0 && $asked !== $hours) {
            $note[] = self::say('Closest plan is ' . self::hoursLabel($hours) . '.');
        }

        return array_merge($note, [
            self::goto('generate', 'Opening the key generator.'),
            ['type' => 'fill', 'selector' => '#game', 'value' => $game],
            ['type' => 'fill', 'selector' => '#duration', 'value' => (string) $hours],
            ['type' => 'fill', 'selector' => '#max_devices', 'value' => (string) $devices],
            ['type' => 'tap', 'selector' => 'form button[type="submit"]', 'speak' => 'Generating your key.'],
            ['type' => 'wait', 'selector' => '#madeKey', 'ms' => 9000],
            ['type' => 'read', 'selector' => '#madeKey', 'prefix' => 'Your key is '],
        ]);
    }

    private static function hoursLabel(int $hours): string
    {
        if ($hours <= 0) {
            return 'the default';
        }
        if ($hours < 24) {
            return $hours . ' hour' . ($hours === 1 ? '' : 's');
        }
        if ($hours % 720 === 0) {
            $m = (int) ($hours / 720);

            return $m . ' month' . ($m === 1 ? '' : 's');
        }
        if ($hours % 24 === 0) {
            $d = (int) ($hours / 24);

            return $d . ' day' . ($d === 1 ? '' : 's');
        }

        return $hours . ' hours';
    }

    private static function goto(string $page, ?string $speak = null): array
    {
        [$path, $nav] = self::PAGES[$page];

        return [
            'type'   => 'goto',
            'url'    => site_url($path),
            'target' => $nav !== '' ? '[data-circuit="' . $nav . '"]' : null,
            'speak'  => $speak,
        ];
    }

    private static function say(string $text): array
    {
        return ['type' => 'say', 'text' => $text];
    }
}