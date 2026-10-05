<?php
namespace App\Filters;

use App\Libraries\LoginThrottle;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class LoginDeviceGuard implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $remain = LoginThrottle::blockedFor($request);
        if ($remain > 0) {
            $mins = max(1, (int)ceil($remain/60));
            $resp = service('response');
            $resp->setStatusCode(423, 'Locked');
            $resp->setHeader('Retry-After', (string)$remain);

            $msg = "Too many failed logins. This device is blocked for $mins minute(s). Please try again later.";

            if ($request->isAJAX() || stripos((string)$request->getHeaderLine('Accept'), 'json') !== false) {
                return $resp->setJSON(['status'=>423,'message'=>$msg,'retry_after_seconds'=>$remain]);
            }
            return $resp->setBody("<h3>Device temporarily blocked</h3><p>$msg</p>");
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}