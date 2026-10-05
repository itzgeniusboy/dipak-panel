<?php

namespace Config;

use App\Filters\AuthFilter;
use App\Filters\AdminFilter;
use App\Filters\RateLimitFilter;   // ✅ rate limit filter
use App\Filters\LoginDeviceGuard;  // ✅ NEW: device-block guard for login
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;

class Filters extends BaseConfig
{
    /** @var array */
    public $aliases = [
        'csrf'       => CSRF::class,
        'toolbar'    => DebugToolbar::class,
        'honeypot'   => Honeypot::class,
        'auth'       => AuthFilter::class,
        'admin'      => AdminFilter::class,
        'rateLimit'  => RateLimitFilter::class,  // ✅ keep
        'loginGuard' => LoginDeviceGuard::class, // ✅ add
    ];

    /** @var array */
    public $globals = [
        'before' => [
            // ✅ Global rate limit (static assets excluded)
            'rateLimit' => [
                'except' => [
                    'assets/*',
                    'public/*',
                    'uploads/*',
                    'favicon.ico',
                ],
            ],

            // ✅ CSRF: connect exempt (as before)
            'csrf' => [
                'except' => [
                    'connect',
                    // 'connect/*', // uncomment if needed
                ],
            ],

            // ✅ Auth filter: allow guest pages (NOW includes verify-otp and forgot password)
            'auth' => [
                'except' => [
                    '/',
                    'login',
                    'register',
                    'connect',
                    'device-reset',
                    'device-reset/*',
                    'verify-otp',       // ⬅️ IMPORTANT
                    'verify-otp/*',     // ⬅️ IMPORTANT (safety for variants)
                    'forgot-password',  // ⬅️ NEW: Forgot password page
                    'forgot-password/*',// ⬅️ NEW: Forgot password variants
                    'reset-password',   // ⬅️ NEW: Reset password page
                    'reset-password/*', // ⬅️ NEW: Reset password variants
                    'price',            // (optional guest page you already route)
                ],
            ],
            // 'honeypot',
        ],
        'after'  => [
            // 'toolbar',
            // 'honeypot',
        ],
    ];

    /** @var array */
    public $methods = [];

    /** @var array */
    public $filters = [
        // ✅ Device-block guard runs only on login routes
        'loginGuard' => ['before' => [
            'login', 'login/*',
            // 'auth/login', 'auth/login/*', // add if you use these routes
        ]],
    ];
}