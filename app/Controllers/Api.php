<?php

namespace App\Controllers;

use App\Libraries\Assistant\Actions;
use App\Libraries\Assistant\Context;
use App\Models\AssistantKeysModel;

/**
 * Bearer-authenticated JSON API for external assistants.
 *
 * The key (created under /assistant) decides the user; every action runs with
 * that user's permissions. Password and username changes are refused by the
 * action registry for every key.
 */
class Api extends BaseController
{
    public function ping()
    {
        $user = Context::user();

        return $this->response->setJSON([
            'ok'      => true,
            'status'  => 'ready',
            'user'    => $user ? $user->username : null,
            'key'     => Context::key() ? Context::key()->key_prefix . '…' : null,
            'time'    => date('c'),
        ]);
    }

    public function actions()
    {
        return $this->response->setJSON(['ok' => true, 'actions' => Actions::list()]);
    }

    public function run()
    {
        $body   = $this->request->getJSON(true) ?: $this->request->getPost();
        $action = trim((string) ($body['action'] ?? ''));
        $params = is_array($body['params'] ?? null) ? $body['params'] : [];

        if ($action === '') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'Missing action.']);
        }

        $result = Actions::run($action, $params, Context::user(), Context::userId());
        unset($result['code']);

        return $this->response->setStatusCode($result['ok'] ? 200 : 400)->setJSON($result);
    }

    public function keys()
    {
        $model = new AssistantKeysModel();
        $model->ensureSchema();

        if (strtolower($this->request->getMethod()) === 'post') {
            $body  = $this->request->getJSON(true) ?: $this->request->getPost();
            $made  = $model->generateKey(Context::userId(), (string) ($body['label'] ?? 'API key'));

            return $this->response->setStatusCode(201)->setJSON([
                'ok'    => true,
                'id'    => $made['id'],
                'key'   => $made['plain'],
                'label' => $made['label'],
                'note'  => 'This key is shown only once.',
            ]);
        }

        $rows = array_map(static function ($r) {
            return [
                'id'           => (int) $r->id,
                'label'        => $r->label,
                'prefix'       => $r->key_prefix,
                'last_used_at' => $r->last_used_at,
                'revoked'      => ! empty($r->revoked_at),
                'created_at'   => $r->created_at,
            ];
        }, $model->forUser(Context::userId()));

        return $this->response->setJSON(['ok' => true, 'keys' => $rows]);
    }
}