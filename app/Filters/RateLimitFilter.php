<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class RateLimitFilter implements FilterInterface
{
    private array $windows = [
        'general' => ['limit' => 300, 'window' => 60],
        'post'    => ['limit' => 120, 'window' => 60],
        'auth'    => ['limit' => 10,  'window' => 300],
        'api'     => ['limit' => 60,  'window' => 60],
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        $ip     = $request->getIPAddress() ?: '0.0.0.0';
        $path   = trim($request->getUri()->getPath(), '/');
        $method = strtolower($request->getMethod());

        $scope  = $this->detectScope($path, $method);

        $limit  = (int) env('rate.limit.' . $scope . '.limit',  $this->windows[$scope]['limit']);
        $window = (int) env('rate.limit.' . $scope . '.window', $this->windows[$scope]['window']);

        $now    = time();
        $bucket = (int) floor($now / $window);

        // ✅ SAFE cache key: no reserved characters
        $key = sprintf('rl_%s_%d_%s', $scope, $bucket, md5($ip));

        $cache = cache();

        // TTL remaining in this window
        $remainingInWindow = $window - ($now % $window);
        if ($remainingInWindow <= 0) {
            $remainingInWindow = $window;
        }

        // Fixed-window counter with TTL until window end
        $count = (int) ($cache->get($key) ?? 0);

        if ($count >= $limit) {
            return $this->tooMany($request, $limit, 0, $remainingInWindow, $now + $remainingInWindow);
        }

        $count++;
        $cache->save($key, $count, $remainingInWindow);

        service('response')
            ->setHeader('X-RateLimit-Limit', (string) $limit)
            ->setHeader('X-RateLimit-Remaining', (string) max(0, $limit - $count))
            ->setHeader('X-RateLimit-Reset', (string) ($now + $remainingInWindow));

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }

    private function detectScope(string $path, string $method): string
    {
        if (preg_match('#^(login|register|otp|password|auth)#i', $path)) {
            return 'auth';
        }
        if (preg_match('#^(api|apiserver)#i', $path)) {
            return 'api';
        }
        if ($method === 'post') {
            return 'post';
        }
        return 'general';
    }

    private function tooMany(RequestInterface $request, int $limit, int $remaining, int $retryAfter, int $resetAt)
    {
        $response = service('response');
        $response->setStatusCode(429, 'Too Many Requests');
        $response->setHeader('Retry-After', (string) $retryAfter);
        $response->setHeader('X-RateLimit-Limit', (string) $limit);
        $response->setHeader('X-RateLimit-Remaining', (string) $remaining);
        $response->setHeader('X-RateLimit-Reset', (string) $resetAt);

        $acceptsJson = (strpos((string) $request->getHeaderLine('Accept'), 'json') !== false) || $request->isAJAX();

        if ($acceptsJson) {
            return $response->setJSON([
                'status'  => 429,
                'message' => 'Too many requests. Please try again later.',
                'retry_after_seconds' => $retryAfter,
            ]);
        }

        return $response->setBody('<h3>Too Many Requests</h3><p>Please try again later.</p>');
    }
}