<?php

namespace App\Libraries\Assistant;

use App\Models\AssistantKeysModel;
use App\Models\KeysModel;

/**
 * The assistant's whole vocabulary.
 *
 * Every capability is declared here with the parameters it accepts. The web
 * (voice) path and the API path both call run(), so a key can do exactly what
 * the buttons can — minus the two things the owner reserved: changing the
 * account password and changing the username. Those are refused by name and
 * by parameter, whatever the scope says.
 */
class Actions
{
    /** Actions nobody — not even a `*` key — may run. */
    public const FORBIDDEN = [
        'account.password',
        'account.username',
        'user.password',
        'user.username',
        'password.change',
        'username.change',
    ];

    /** Navigation targets the assistant may open. */
    public const PAGES = [
        'dashboard' => 'dashboard',
        'keys'      => 'keys',
        'generate'  => 'keys/generate-random',
        'balance'   => 'balance',
        'license'   => 'license',
        'price'     => 'price',
        'settings'  => 'settings',
        'assistant' => 'assistant',
        'server'    => 'Server',
    ];

    /** @return array<string,array{label:string,write:bool,params:array<string,string>}> */
    public static function list(): array
    {
        return [
            'ping'              => ['label' => 'Check the assistant is awake', 'write' => false, 'params' => []],
            'user.info'         => ['label' => 'Who am I: name, level, balance', 'write' => false, 'params' => []],
            'balance.show'      => ['label' => 'Show my balance', 'write' => false, 'params' => []],
            'key.list'          => ['label' => 'List my keys', 'write' => false, 'params' => []],
            'key.generate'      => ['label' => 'Generate a key', 'write' => true, 'params' => ['game' => 'PUBG', 'duration' => 'hours', 'max_devices' => 'int']],
            'key.delete'        => ['label' => 'Delete one of my keys', 'write' => true, 'params' => ['user_key' => 'string']],
            'key.delete_unused' => ['label' => 'Delete my unused keys', 'write' => true, 'params' => []],
            'price.list'        => ['label' => 'Show price plans', 'write' => false, 'params' => []],
            'settings.site_name' => ['label' => 'Rename the panel (admin)', 'write' => true, 'params' => ['name' => 'string']],
            'assistant.key.generate' => ['label' => 'Create an assistant API key', 'write' => true, 'params' => ['label' => 'string']],
            'assistant.key.list'     => ['label' => 'List assistant API keys', 'write' => false, 'params' => []],
            'assistant.key.revoke'   => ['label' => 'Revoke an assistant API key', 'write' => true, 'params' => ['id' => 'int']],
            'navigate'          => ['label' => 'Open a panel page', 'write' => false, 'params' => ['page' => 'string']],
        ];
    }

    public static function isForbidden(string $name, array $params): bool
    {
        if (in_array(strtolower($name), self::FORBIDDEN, true)) {
            return true;
        }

        foreach (array_keys($params) as $key) {
            if (stripos((string) $key, 'password') !== false || stripos((string) $key, 'username') !== false) {
                return true;
            }
        }

        return false;
    }

    public static function run(string $name, array $params, object $user, int $userId): array
    {
        helper('nata'); // isUnlimitedBalance()

        $name = strtolower(trim($name));

        if (self::isForbidden($name, $params)) {
            return self::out(false, 403, 'I will not touch the password or the username — those are yours alone.');
        }

        if (! isset(self::list()[$name])) {
            return self::out(false, 404, "I don't know the action \"{$name}\".");
        }

        switch ($name) {
            case 'ping':
                return self::out(true, 200, 'Circuit is awake.', ['user' => $user->username]);

            case 'user.info':
            case 'balance.show':
                $unlimited = isUnlimitedBalance($user);
                $balance   = $unlimited ? 'unlimited' : (int) $user->saldo;

                return self::out(true, 200, sprintf('%s · level %s · balance %s', $user->username, $user->level, $balance), [
                    'username'  => $user->username,
                    'fullname'  => $user->fullname,
                    'level'     => (int) $user->level,
                    'saldo'     => $unlimited ? null : (int) $user->saldo,
                    'unlimited' => $unlimited,
                ]);

            case 'price.list':
                $map = KeyFactory::priceMap();
                $rows = [];
                foreach ($map as $hours => $price) {
                    $rows[] = ['duration' => $hours, 'price' => $price];
                }

                return self::out(true, 200, count($rows) . ' plans available.', $rows);

            case 'key.list':
                $keys = self::ownKeys($user);
                $lines = array_map(static fn ($k) => ['key' => $k->user_key, 'game' => $k->game, 'duration' => $k->duration, 'max_devices' => $k->max_devices], $keys);

                return self::out(true, 200, count($lines) . ' keys on your account.', $lines);

            case 'key.generate':
                $res = KeyFactory::generate($user, $userId, $params);

                return self::out($res['ok'], $res['code'], $res['message'], $res['key'] ? ['key' => $res['key']] : null);

            case 'key.delete':
                $target = trim((string) ($params['user_key'] ?? ''));
                if ($target === '') {
                    return self::out(false, 400, 'Which key should I delete?');
                }
                $model = new KeysModel();
                $row   = $model->getKeysGame(['user_key' => $target]);
                if (! $row) {
                    return self::out(false, 404, 'That key does not exist.');
                }
                if ((int) $user->level > 2 && $row->registrator !== $user->username) {
                    return self::out(false, 403, 'That key belongs to someone else.');
                }
                $model->delete($row->id_keys);

                return self::out(true, 200, "Deleted {$target}.");

            case 'key.delete_unused':
                $deleted = 0;
                foreach (self::ownKeys($user) as $k) {
                    if (empty($k->expired_date) && empty($k->devices)) {
                        (new KeysModel())->delete($k->id_keys);
                        $deleted++;
                    }
                }

                return self::out(true, 200, "Removed {$deleted} unused key(s).");

            case 'settings.site_name':
                if (! in_array((int) $user->level, [1, 2], true)) {
                    return self::out(false, 403, 'Only an admin can rename the panel.');
                }
                $value = trim((string) ($params['name'] ?? ''));
                if ($value === '' || mb_strlen($value) > 128) {
                    return self::out(false, 400, 'Give me a name under 128 characters.');
                }
                $db = db_connect();
                if ($db->tableExists('settings')) {
                    $exists = $db->table('settings')->countAllResults() > 0;
                    if ($exists) {
                        $db->table('settings')->update(['base_name' => $value]);
                    } else {
                        $db->table('settings')->insert(['base_name' => $value]);
                    }
                }

                return self::out(true, 200, "Panel renamed to {$value}.");

            case 'assistant.key.generate':
                $model = new AssistantKeysModel();
                $made  = $model->generateKey($userId, (string) ($params['label'] ?? 'Assistant key'));

                return self::out(true, 201, 'Assistant key created. Copy it now — it is shown once.', [
                    'id' => $made['id'], 'key' => $made['plain'], 'prefix' => $made['prefix'],
                ]);

            case 'assistant.key.list':
                $rows = (new AssistantKeysModel())->forUser($userId);
                $data = array_map(static fn ($r) => [
                    'id'         => (int) $r->id,
                    'label'      => $r->label,
                    'prefix'     => $r->key_prefix,
                    'last_used'  => $r->last_used_at,
                    'revoked'    => ! empty($r->revoked_at),
                ], $rows);

                return self::out(true, 200, count($data) . ' assistant keys.', $data);

            case 'assistant.key.revoke':
                $id = (int) ($params['id'] ?? 0);
                $model = new AssistantKeysModel();
                $row = $model->find($id);
                if (! $row || (int) $row->user_id !== $userId) {
                    return self::out(false, 404, 'No such assistant key.');
                }
                $model->update($id, ['revoked_at' => date('Y-m-d H:i:s')]);

                return self::out(true, 200, 'Assistant key revoked.');

            case 'navigate':
                $page = strtolower(trim((string) ($params['page'] ?? '')));
                if (! isset(self::PAGES[$page])) {
                    return self::out(false, 404, "There is no page called \"{$page}\".");
                }
                $url = site_url(self::PAGES[$page]);

                return self::out(true, 200, "Opening {$page}.", ['page' => $page, 'url' => $url], [
                    'type'   => 'goto',
                    'url'    => $url,
                    'page'   => $page,
                    'target' => self::targetFor($page),
                ]);
        }

        return self::out(false, 500, 'That action is not wired up yet.');
    }

    private static function ownKeys(object $user): array
    {
        $model   = new KeysModel();
        $builder = $model->builder();
        $builder->select('id_keys, user_key, game, duration, max_devices, devices, status, expired_date, registrator');
        if ((int) $user->level > 2) {
            $builder->where('registrator', $user->username);
        }
        $builder->orderBy('id_keys', 'DESC')->limit(50);

        return $builder->get()->getResultObject();
    }

    /** Best-effort UI hint so Circuit can walk to the matching nav item. */
    private static function targetFor(string $page): ?string
    {
        return match ($page) {
            'dashboard' => '[data-circuit="nav:dashboard"]',
            'keys'      => '[data-circuit="nav:keys"]',
            'generate'  => '[data-circuit="nav:generate"]',
            'balance'   => '[data-circuit="nav:balance"]',
            'license'   => '[data-circuit="nav:license"]',
            'price'     => '[data-circuit="nav:price"]',
            'settings'  => '[data-circuit="nav:settings"]',
            'assistant' => '[data-circuit="nav:assistant"]',
            default     => null,
        };
    }

    private static function out(bool $ok, int $code, string $message, $data = null, ?array $client = null): array
    {
        return ['ok' => $ok, 'code' => $code, 'message' => $message, 'data' => $data, 'client' => $client];
    }
}