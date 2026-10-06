<?php

namespace App\Filters;

use App\Libraries\Assistant\Context;
use App\Models\AssistantKeysModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

/**
 * Bearer / X-API-Key authentication for /api/*.
 *
 * A key is bound to one user, so the API can only ever act as that user. The
 * filter loads the user into Context for the controller, refuses revoked and
 * expired keys, and records last use.
 */
class ApiKeyFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $plain = $this->keyFrom($request);

        if ($plain === '') {
            return $this->deny('Missing API key. Send Authorization: Bearer <key> or X-API-Key.');
        }

        $keys = new AssistantKeysModel();
        $row  = $keys->findByKey($plain);

        if (! $row) {
            return $this->deny('Invalid API key.');
        }

        if (! empty($row->expires_at) && strtotime((string) $row->expires_at) < time()) {
            return $this->deny('This API key has expired.');
        }

        $user = (new UserModel())->find($row->user_id);
        if (! $user || (int) $user->status !== 1 || (int) ($user->is_active ?? 1) !== 1) {
            return $this->deny('The account behind this key is disabled.');
        }

        $keys->touchUsage((int) $row->id, $request->getIPAddress() ?: '');
        Context::set($user, $row);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }

    private function keyFrom(RequestInterface $request): string
    {
        $header = trim((string) $request->getHeaderLine('Authorization'));
        if ($header !== '' && stripos($header, 'bearer ') === 0) {
            return trim(substr($header, 7));
        }

        $alt = trim((string) $request->getHeaderLine('X-API-Key'));
        if ($alt !== '') {
            return $alt;
        }

        return trim((string) ($request->getPost('api_key') ?? ''));
    }

    private function deny(string $message)
    {
        return service('response')
            ->setStatusCode(401, 'Unauthorized')
            ->setHeader('WWW-Authenticate', 'Bearer')
            ->setJSON(['ok' => false, 'error' => 'unauthorized', 'message' => $message]);
    }
}