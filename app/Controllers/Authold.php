<?php

namespace App\Controllers;

use App\Models\CodeModel;
use App\Models\UserModel;
use CodeIgniter\Config\Services;
use App\Libraries\LoginThrottle; // ✅ Throttle library
use App\Libraries\Telegram;      // ✅ Telegram OTP sender

class Auth extends BaseController
{
    protected $user;
    protected $deviceResetLimit = 2; // ✅ yaha number change karke reset limit badha/ghata sakte ho

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    function getUserIP()
    {
        $clientIp  = @$_SERVER['HTTP_CLIENT_IP'];
        $forwardIp = @$_SERVER['HTTP_X_FORWARDED_FOR'];
        $remoteIp  = $_SERVER['REMOTE_ADDR'];
        if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
            $ipaddress = $clientIp;
        } elseif (filter_var($forwardIp, FILTER_VALIDATE_IP)) {
            $ipaddress = $forwardIp;
        } else {
            $ipaddress = $remoteIp;
        }
        return $ipaddress;
    }

    public function index()
    {
        $a = $this->userModel->getUser(session('userid'));
        dd($a, session());
    }

    public function login()
    {
        if (session()->has('userid'))
            return redirect()->to('dashboard')->with('msgSuccess', 'Login Successful!');

        if ($this->request->getPost())
            return $this->login_action();

        $data = [
            'title' => 'Login',
            'validation' => Services::validation(),
        ];
        return view('Auth/login', $data);
    }

    public function register()
    {
        if (session()->has('userid'))
            return redirect()->to('dashboard')->with('msgSuccess', 'Login Successful!');

        if ($this->request->getPost())
            return $this->register_action();

        $data = [
            'title' => 'Register',
            'validation' => Services::validation(),
        ];
        return view('Auth/register', $data);
    }

    /**
     * NEW: OTP Verify screen (GET shows form, POST verifies)
     * Make sure you have app/Views/Auth/otp.php present.
     */
    public function verify_otp()
    {
        $pending = session('pending_2fa');

        // No pending 2FA => back to login
        if (!$pending || empty($pending['user_id'])) {
            return redirect()->route('login')->with('msgDanger', 'Session expired. Please login again.');
        }

        // POST => verify code
        if ($this->request->getMethod() === 'post') {
            $otpInput = trim((string)$this->request->getPost('otp'));

            // Expired?
            if (time() > (int)$pending['expires']) {
                session()->remove('pending_2fa');
                return redirect()->route('login')->with('msgDanger', 'OTP expired. Please login again.');
            }

            // Wrong code?
            if (!password_verify($otpInput, (string)$pending['otp_hash'])) {
                return redirect()->to('verify-otp')->with('msgDanger', 'Invalid OTP. Please try again.');
            }

            // OTP OK → finalize login
            $cekUser = $this->userModel->getUser($pending['user_id']);
            if (!$cekUser) {
                session()->remove('pending_2fa');
                return redirect()->route('login')->with('msgDanger', 'User not found.');
            }

            // Clear throttle only on real success
            \App\Libraries\LoginThrottle::clearOnSuccess($this->request);

            $time = new \CodeIgniter\I18n\Time;
            $data = [
                'userid'      => $cekUser->id_users,
                'unames'      => $cekUser->username,
                'time_login'  => (!empty($pending['stay_log']) ? $time::now()->addHours(24) : $time::now()->addMinutes(30)),
                'time_since'  => $time::now(),
            ];
            session()->set($data);
            session()->remove('pending_2fa');

            // Optional: same expiry message as normal flow
            $expmsg = 'Login Successful!';
            include('conn.php');
            if (isset($cekUser->username) && isset($conn)) {
                $u = mysqli_real_escape_string($conn, $cekUser->username);
                $sql = "SELECT `expiration_date` FROM `users` WHERE `username` = '$u'";
                if ($q = mysqli_query($conn, $sql)) {
                    if ($row = mysqli_fetch_assoc($q)) {
                        if (!empty($row['expiration_date'])) {
                            $expmsg = "Account Expires on : " . $row['expiration_date'];
                        }
                    }
                }
            }
            @include('UserMail.php');

            return redirect()->to('dashboard')->with('msgSuccess', $expmsg);
        }

        // GET => render OTP form
        return view('Auth/otp', ['title' => 'Verify OTP']);
    }

    /**
     * UPDATED: Login with Telegram OTP 2FA (per-user toggle)
     */
    private function login_action()
    {
        $usernam   = $this->request->getPost('username');
        $password  = $this->request->getPost('password');
        $stay_log  = $this->request->getPost('stay_log');
        $device_id = $this->request->getPost('device_id'); // keep

        // ✅ DB-backed pre-check
        $blockedRemain = \App\Libraries\LoginThrottle::blockedFor($this->request);
        if ($blockedRemain > 0) {
            $mins = max(1, (int)ceil($blockedRemain/60));
            $this->response->setStatusCode(423, 'Locked');
            $this->response->setHeader('Retry-After', (string)$blockedRemain);
            return redirect()->route('login')->withInput()->with('msgDanger',
                "Too many failed logins. This device is blocked for $mins minute(s). Please try again later.");
        }

        $form_rules = [
            'username' => [
                'label' => 'username',
                'rules' => 'required|alpha_numeric|min_length[4]|max_length[25]|is_not_unique[users.username]',
                'errors' => ['is_not_unique' => 'The {field} is not registered.']
            ],
            'password' => [
                'label' => 'password',
                'rules' => 'required|min_length[6]|max_length[45]',
            ],
            'stay_log' => ['rules' => 'permit_empty|max_length[3]']
        ];

        if (!$this->validate($form_rules)) {
            return redirect()->route('login')->withInput()->with('msgDanger', '<strong>Failed</strong> Please check the form.');
        }

        $validation = Services::validation();
        $cekUser = $this->userModel->getUser($usernam, 'username');

        if ($cekUser) {
            $hashPassword = create_password($password, false);

            if (password_verify($hashPassword, $cekUser->password)) {
                // ✅ Account checks (unchanged)
                include('conn.php');
                $sql = "SELECT `expiration_date`, `status` FROM `users` WHERE `username` = '".$usernam."'";
                $query = mysqli_query($conn, $sql);
                $userData = mysqli_fetch_assoc($query);
                $current_time = date('Y-m-d H:i:s');

                if (!$userData) {
                    return redirect()->route('login')->withInput()->with('msgDanger', 'Account not found.');
                }
                if ((int)$userData['status'] === 0) {
                    return redirect()->route('login')->withInput()->with('msgWarning', 'Registration received. Wait for your account approval by Owner.');
                }
                if ((int)$userData['status'] === 2) {
                    return redirect()->route('login')->withInput()->with('msgDanger', 'Your account was declined by Owner.');
                }
                if ((int)$userData['status'] !== 1) {
                    return redirect()->route('login')->withInput()->with('msgDanger', 'Your account is not active.');
                }
                if ($userData['expiration_date'] <= $current_time) {
                    return redirect()->route('login')->withInput()->with('msgDanger', '<strong>Expired</strong> Please Renew Your Account to Login.');
                }

                // ✅ Single device check (unchanged)
                if (in_array($cekUser->level, [2,3])) {
                    if (empty($device_id)) {
                        return redirect()->route('login')->withInput()->with('msgDanger', 'Device ID required.');
                    }
                    if (!empty($cekUser->device_id) && $cekUser->device_id !== $device_id) {
                        return redirect()->route('login')->withInput()->with('msgDanger', 'Wrong device. This account is already logged in from another device.');
                    }
                    if (empty($cekUser->device_id)) {
                        $this->userModel->update($cekUser->id_users, ['device_id' => $device_id]);
                    }
                }

                // ✅ TWO-STEP VERIFICATION (Telegram)
                // Only when user has enabled 2FA and Chat ID is present
                if ((int)($cekUser->twofa_enabled ?? 0) === 1 && !empty($cekUser->telegram_chat_id)) {
                    try {
                        $otp = (string)random_int(100000, 999999);
                    } catch (\Throwable $e) {
                        // Fallback if random_int fails (rare)
                        $otp = str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    }
                    $otpHash   = password_hash($otp, PASSWORD_DEFAULT);
                    $expiresAt = time() + 300; // 5 minutes

                    // Save pending 2FA info in session; do NOT login yet
                    session()->set('pending_2fa', [
                        'user_id'  => $cekUser->id_users,
                        'otp_hash' => $otpHash,
                        'expires'  => $expiresAt,
                        'stay_log' => (bool)$stay_log,
                    ]);

                    // Send OTP through Telegram
                     $tg = new Telegram();
                     $sent = $tg->sendMessage(
                     (string)$cekUser->telegram_chat_id,
                     "🔐 Your Login OTP Code\n\n" .
                     "Username: <b>{$cekUser->username}</b>\n" .
                     "OTP Code: <b>{$otp}</b>\n" .
                     "This code will expire in 5 minutes.\n" .
                     "If you didn't request this, please ignore this message."
);

                    if (!$sent) {
                        session()->remove('pending_2fa');
                        return redirect()->route('login')->withInput()->with('msgDanger', 'Could not send OTP to Telegram. Please try again.');
                    }

                    // Show OTP page
                    return redirect()->to('verify-otp')->with('msgInfo', 'Enter the OTP sent to your Telegram.');
                }

                // ✅ SUCCESS (no 2FA needed): clear SQL throttle + set session
                \App\Libraries\LoginThrottle::clearOnSuccess($this->request);

                $time = new \CodeIgniter\I18n\Time;
                $data = [
                    'userid'      => $cekUser->id_users,
                    'unames'      => $cekUser->username,
                    'time_login'  => $stay_log ? $time::now()->addHours(24) : $time::now()->addMinutes(30),
                    'time_since'  => $time::now(),
                ];
                session()->set($data);

                $expmsg = "Account Expires on : " . $userData['expiration_date'];
                include('UserMail.php');
                return redirect()->to('dashboard')->with('msgSuccess', $expmsg);

            } else {
                // ❌ WRONG password (SQL throttle)
                \App\Libraries\LoginThrottle::recordFailure($this->request);

                // Blocked now?
                $remain = \App\Libraries\LoginThrottle::blockedFor($this->request);
                if ($remain > 0) {
                    $mins = max(1, (int)ceil($remain/60));
                    $this->response->setStatusCode(423, 'Locked');
                    $this->response->setHeader('Retry-After', (string)$remain);
                    return redirect()->route('login')->withInput()->with('msgDanger',
                        "Too many failed logins. This device is blocked for $mins minute(s). Please try again later.");
                }

                // Attempts left
                $left = \App\Libraries\LoginThrottle::attemptsLeft($this->request);
                $validation->setError('password', 'Wrong password, please try again.');
                $msg = $left > 0
                    ? "<strong>Failed</strong> Invalid username or password. Attempts left: $left."
                    : "<strong>Failed</strong> Please check the form.";
                return redirect()->route('login')->withInput()->with('msgDanger', $msg);
            }
        }

        // user not found / generic
        return redirect()->route('login')->withInput()->with('msgDanger', '<strong>Failed</strong> Please check the form.');
    }

    public function register_action()
    {
        $email    = $this->request->getPost('email');
        $userna   = $this->request->getPost('username');
        $fullname = $this->request->getPost('fullname');
        $password = $this->request->getPost('password');
        $referral = $this->request->getPost('referral');

        $form_rules = [
            'email' => [
                'label' => 'email',
                'rules' => 'required|min_length[13]|max_length[40]|valid_email'
            ],
            'username' => [
                'label' => 'username',
                'rules' => 'required|alpha_numeric|min_length[4]|max_length[25]|is_unique[users.username]',
                'errors' => [
                    'is_unique' => 'The {field} has been taken.'
                ]
            ],
            'fullname' => [
                'label' => 'fullname',
                'rules' => 'required|min_length[4]|max_length[100]',
                'errors' => [
                    'is_unique' => 'The {field} has been taken.'
                ]
            ],
            'password' => [
                'label' => 'password',
                'rules' => 'required|min_length[6]|max_length[45]',
            ],
            'password2' => [
                'label' => 'password',
                'rules' => 'required|min_length[6]|max_length[45]|matches[password]',
                'errors' => [
                    'matches' => '{field} not match, check the {field}.'
                ]
            ],
            'referral' => [
                'label' => 'referral',
                'rules' => 'required|min_length[6]|alpha_numeric',
            ]
        ];

        if (!$this->validate($form_rules)) {
            return redirect()->route('register')->withInput()->with('msgDanger', '<strong>Failed</strong> Please check the form.');
        } else {
            $mCode = new CodeModel();
            $rCheck = $mCode->checkCode($referral);
            $validation = Services::validation();
            if (!$rCheck) {
                $validation->setError('referral', 'Wrong referral, please try again.');
                return redirect()->route('register')->withInput()->with('msgDanger', '<strong>Failed</strong> Please check the form.');
            } else {
                if ($rCheck->used_by) {
                    $validation->setError('referral', "Wrong referral, code has been used · $rCheck->used_by.");
                    return redirect()->route('register')->withInput()->with('msgDanger', '<strong>Failed</strong> Please check the form.');
                } else {
                    $hashPassword = create_password($password);
                    $ipaddress = $this->getUserIP();

                    // Get referral details
                    $referralDetails = $mCode->where('Referral', $referral)->first();
                    if (!$referralDetails) {
                        $validation->setError('referral', 'Invalid referral code.');
                        return redirect()->route('register')->withInput()->with('msgDanger', '<strong>Failed</strong> Please check the form.');
                    }

                    $data_register = [
                        'email' => $email,
                        'username' => $userna,
                        'fullname' => $fullname,
                        'level' => $referralDetails['level'] ?? 3, // Default to Reseller
                        'password' => $hashPassword,
                        'saldo' => $rCheck->set_saldo ?: 0,
                        'uplink' => $rCheck->created_by, // This is important - stores who referred this user
                        'user_ip' => $ipaddress,
                        'status' => 0, // Pending approval
                        'expiration_date' => $referralDetails['acc_expiration'] ?? date('Y-m-d H:i:s', strtotime('+30 days'))
                    ];

                    $ids = $this->userModel->insert($data_register, true);
                    if ($ids) {
                        $mCode->useReferral($referral);
                        return redirect()->to('login')->with('msgSuccess', 'Registration submitted. Wait for your account approval.');
                    }
                }
            }
        }
        return redirect()->route('register')->withInput()->with('msgDanger', '<strong>Failed</strong> Please check the form.');
    }

    public function logout()
    {
        if (session()->has('userid')) {
            // ✅ Sirf session clear hoga, device_id clear nahi hoga
            $unset = ['userid', 'unames', 'time_login', 'time_since'];
            session()->remove($unset);
            session()->setFlashdata('msgSuccess', 'Logout successfully.');
        }
        return redirect()->to('login');
    }

    // ✅ Modified reset logic with 24h + 2 times limit
    public function devicereset()
    {
        $data = ['title' => 'Reset Device', 'validation' => Services::validation()];

        if ($this->request->getMethod() === 'post') {
            $username = $this->request->getPost('username');
            $password = $this->request->getPost('password');

            $user = $this->userModel->getUser($username, 'username');
            if (!$user) {
                return redirect()->back()->withInput()->with('msgDanger', 'User not found.');
            }

            $hashPassword = create_password($password, false);
            if (!password_verify($hashPassword, $user->password)) {
                return redirect()->back()->withInput()->with('msgDanger', 'Invalid password.');
            }

            // ✅ Reset control
            $count = (int) ($user->device_reset_count ?? 0);
            $lastReset = $user->last_reset_at ?? null;
            $now = date('Y-m-d H:i:s');
            $allowReset = false;

            if ($lastReset === null) {
                $allowReset = true;
                $newCount = 1;
            } else {
                $hours = (time() - strtotime($lastReset)) / 3600;
                if ($hours >= 24) {
                    $allowReset = true;
                    $newCount = 1;
                } elseif ($count < $this->deviceResetLimit) {
                    $allowReset = true;
                    $newCount = $count + 1;
                }
            }

            if (!$allowReset) {
                return redirect()->back()->withInput()->with('msgDanger',
                    "You have reached the {$this->deviceResetLimit} reset limit in 24 hours. Try again later.");
            }

            // ✅ Reset device_id and update counters
            $this->userModel->update($user->id_users, [
                'device_id' => null,
                'device_reset_count' => $newCount,
                'last_reset_at' => $now
            ]);

            return redirect()->to('login')->with('msgSuccess', 'Device reset successfully. You can login again.');
        }

        return view('Auth/device-reset', $data);
    }
}