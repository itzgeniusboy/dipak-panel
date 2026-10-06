<?php

namespace App\Controllers;

use App\Libraries\Assistant\Actions;
use App\Libraries\Assistant\CommandRouter;
use App\Libraries\Assistant\Gemini;
use App\Libraries\Assistant\PanelMap;
use App\Models\AssistantConfigModel;
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
        (new AssistantConfigModel())->ensureSchema();

        return view('Assistant/index', [
            'title' => 'Circuit assistant',
            'user'  => $this->user,
            'keys'  => $model->forUser($this->userId),
            'newKey' => session()->getFlashdata('newKey'),
            'newKeyLabel' => session()->getFlashdata('newKeyLabel'),
            'actions' => Actions::list(),
            'pages' => Actions::PAGES,
            'gemini' => Gemini::enabled(),
            'canAi' => in_array((int) $this->user->level, [1, 2], true),
            'aiModel' => Gemini::model(),
            'aiKeyHint' => Gemini::apiKey() !== '' ? str_repeat('•', 6) . substr(Gemini::apiKey(), -4) : '',
        ]);
    }

    public function ai()
    {
        if (! $this->request->is('post')) {
            return redirect()->to('assistant');
        }
        if (! in_array((int) $this->user->level, [1, 2], true)) {
            return redirect()->to('assistant')->with('msgDanger', 'Only an admin can change the AI settings.');
        }

        $model = new AssistantConfigModel();
        $model->ensureSchema();
        $action = (string) ($this->request->getPost('action') ?? 'save');

        if ($action === 'clear') {
            $model->put('gemini.apiKey', '');

            return redirect()->to('assistant')->with('msgSuccess', 'Gemini key removed — Circuit now uses the built-in rules.');
        }

        $key  = trim((string) ($this->request->getPost('gemini_key') ?? ''));
        $name = trim((string) ($this->request->getPost('gemini_model') ?? ''));

        if ($key !== '') {
            $model->put('gemini.apiKey', $key);
        }
        if ($name !== '') {
            $model->put('gemini.model', $name);
        }

        $what = $key !== '' ? 'Gemini key saved.' : 'AI settings saved.';

        return redirect()->to('assistant')->with('msgSuccess', $what);
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

        log_message('info', 'Circuit command from user {id}: {text}', ['id' => $this->userId, 'text' => $text]);

        if ($text === '') {
            return $this->json(['ok' => false, 'speak' => 'I did not hear anything.', 'plan' => []]);
        }

        $route = Gemini::interpret($text, $this->user, $this->userId) ?? CommandRouter::route($text);
        log_message('info', 'Circuit route: ' . $route['action'] . ' via ' . ($route['via'] ?? 'rules'));

        if ($route['action'] === '_reply') {
            return $this->json([
                'ok'    => true,
                'via'   => $route['via'] ?? 'rules',
                'speak' => $route['reply'],
                'plan'  => [['type' => 'say', 'text' => $route['reply']]],
                'toast' => ['kind' => 'info', 'msg' => $route['reply']],
            ]);
        }

        // Prefer driving the real UI: Circuit clicks the panel's own buttons
        // and fills its own forms, exactly like a person would.
        $uiPlan = PanelMap::plan($route['action'], $route['params'], $this->user);
        if ($uiPlan !== null) {
            return $this->json([
                'ok'     => true,
                'via'    => $route['via'] ?? 'rules',
                'action' => $route['action'],
                'speak'  => 'On it.',
                'plan'   => $uiPlan,
                'toast'  => ['kind' => 'info', 'msg' => 'Circuit is doing it on screen.'],
            ]);
        }

        log_message('info', 'Circuit has no UI recipe for "' . $route['action'] . '" — running it server-side.');
        $result = Actions::run($route['action'], $route['params'], $this->user, $this->userId);

        if (! $result['ok']) {
            log_message('error', 'Circuit action "' . $route['action'] . '" failed (' . ($result['code'] ?? 0) . '): ' . $result['message']);
        }

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
            'via'     => $route['via'] ?? 'rules',
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

    /**
     * The browser reports anything Circuit could not do (button not found,
     * navigation failed, a step threw) so the reason lands in the log instead
     * of vanishing in devtools. Fire-and-forget — always answers 204.
     */
    public function report()
    {
        if (! $this->request->is('post')) {
            return $this->response->setStatusCode(405);
        }

        $body = $this->request->getJSON(true) ?: [];
        $cut  = static fn ($v, $n = 300) => mb_substr((string) $v, 0, $n);

        $reason = $cut($body['reason'] ?? 'unknown', 80);
        $step   = is_array($body['step'] ?? null) ? $body['step'] : [];
        $detail = [
            'user'      => $this->userId,
            'reason'    => $reason,
            'action'    => $cut($body['action'] ?? ''),
            'url'       => $cut($body['url'] ?? ''),
            'stepIndex' => (int) ($body['stepIndex'] ?? 0),
            'target'    => $cut($step['target'] ?? $step['selector'] ?? $step['text'] ?? $body['target'] ?? $body['selector'] ?? ''),
            'step'      => $step,
        ];

        $level = $reason === 'goto_missing_url' ? 'warning' : 'error';
        log_message($level, 'Circuit could not ' . $reason . ' — ' . json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // Keep the whole failing plan too — it is what you actually need to debug.
        if (! empty($body['plan'])) {
            log_message('warning', 'Circuit failing plan: ' . $cut(json_encode($body['plan'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 1000));
        }
    }

    private function json(array $payload)
    {
        // CSRF regenerates after every verified POST; hand the new token back so
        // the next spoken command still authenticates.
        $payload['csrf'] = service('security')->getHash();

        return $this->response->setJSON($payload);
    }
}