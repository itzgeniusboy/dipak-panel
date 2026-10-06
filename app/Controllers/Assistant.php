<?php

namespace App\Controllers;

use App\Libraries\Assistant\Actions;
use App\Libraries\Assistant\CommandRouter;
use App\Models\AssistantKeysModel;
use App\Models\UserModel;

/**
 * Circuit, the in-panel assistant.
 *
 * index/create/revoke manage the API keys (per user). command() is what the
 * voice layer calls: it routes a sentence to an action, runs it as the signed
 * in user, and returns a plan the on-screen mascot performs step by step.
 */
class Assistant extends BaseController
{
    protected $userModel;
    protected $user;
    protected $userId;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->user      = $this->userModel->getUser();
        $this->userId    = (int) session()->get('userid');
    }

    public function index()
    {
        $model = new AssistantKeysModel();
        $model->ensureSchema();

        return view('Assistant/index', [
            'title' => 'Circuit assistant',
            'user'  => $this->user,
            'keys'  => $model->forUser($this->userId),
            'newKey' => session()->getFlashdata('newKey'),
            'newKeyLabel' => session()->getFlashdata('newKeyLabel'),
            'actions' => Actions::list(),
            'pages' => Actions::PAGES,
        ]);
    }

    public function create()
    {
        if (! $this->request->is('post')) {
            return redirect()->to('assistant');
        }

        $label = (string) ($this->request->getPost('label') ?? '');
        $model = new AssistantKeysModel();
        $made  = $model->generateKey($this->userId, $label ?: 'Assistant key');

        return redirect()->to('assistant')->with('newKey', $made['plain'])->with('newKeyLabel', $made['label']);
    }

    public function revoke($id = null)
    {
        $model = new AssistantKeysModel();
        $model->ensureSchema();
        $row = $model->find((int) $id);

        if (! $row || (int) $row->user_id !== $this->userId) {
            return redirect()->to('assistant')->with('msgDanger', 'That key is not yours.');
        }

        $model->update((int) $id, ['revoked_at' => date('Y-m-d H:i:s')]);

        return redirect()->to('assistant')->with('msgSuccess', 'Key revoked.');
    }

    public function command()
    {
        if (! $this->request->is('post')) {
            return $this->response->setStatusCode(405)->setJSON(['ok' => false, 'message' => 'POST only.']);
        }

        $body = $this->request->getJSON(true) ?: [];
        $text = trim((string) ($body['text'] ?? ''));

        if ($text === '') {
            return $this->json(['ok' => false, 'speak' => 'I did not hear anything.', 'plan' => []]);
        }

        $route = CommandRouter::route($text);

        if ($route['action'] === '_reply') {
            return $this->json([
                'ok'    => true,
                'speak' => $route['reply'],
                'plan'  => [['type' => 'say', 'text' => $route['reply']]],
                'toast' => ['kind' => 'info', 'msg' => $route['reply']],
            ]);
        }

        $result = Actions::run($route['action'], $route['params'], $this->user, $this->userId);

        $result = Actions::run($route['action'], $route['params'], $this->user, $this->userId);

        $target = $result['client']['target'] ?? null;
        $url    = $result['client']['url'] ?? null;
        $msg    = $result['message'];

        if ($result['ok'] && $route['action'] === 'navigate' && $url) {
            $plan = [['type' => 'goto', 'url' => $url, 'target' => $target, 'speak' => 'Opening ' . ($result['data']['page'] ?? 'the page') . '.']];
        } elseif ($result['ok'] && $route['action'] === 'key.generate') {
            $plan = [
                ['type' => 'goto', 'url' => site_url('keys'), 'target' => '[data-circuit="nav:keys"]', 'speak' => 'Generating — opening Keys.'],
                ['type' => 'say', 'text' => $msg],
            ];
        } elseif ($result['ok'] && str_starts_with($route['action'], 'assistant.key.')) {
            $plan = [['type' => 'goto', 'url' => site_url('assistant'), 'target' => '[data-circuit="nav:assistant"]', 'speak' => 'Opening the assistant page.']];
            if (! empty($result['data']['key'])) {
                $plan[] = ['type' => 'say', 'text' => 'Your new key is: ' . $result['data']['key']];
            } else {
                $plan[] = ['type' => 'say', 'text' => $msg];
            }
        } else {
            $plan = [['type' => 'say', 'text' => $msg]];
            if ($target) {
                $plan[] = ['type' => 'tap', 'target' => $target];
            }
        }

        return $this->json([
            'ok'      => (bool) $result['ok'],
            'action'  => $route['action'],
            'speak'   => $result['message'],
            'data'    => $result['data'],
            'plan'    => $plan,
            'toast'   => [
                'kind' => $result['ok'] ? 'good' : ($result['code'] === 402 ? 'warn' : 'bad'),
                'msg'  => $result['message'],
            ],
        ]);
    }

    private function json(array $payload)
    {
        // CSRF regenerates after every verified POST; hand the new token back so
        // the next spoken command still authenticates.
        $payload['csrf'] = service('security')->getHash();

        return $this->response->setJSON($payload);
    }
}