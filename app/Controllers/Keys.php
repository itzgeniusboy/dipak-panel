<?php

namespace App\Controllers;

use App\Models\HistoryModel;
use App\Models\KeysModel;
use App\Models\UserModel;
use App\Models\PriceModel;
use Config\Services;

class Keys extends BaseController
{
    protected $userModel, $model, $user, $userId;
    // NOTE: PHP 8.2 me dynamic props warning aa sakti hai; yadi ho to upar properties declare kar sakte hain.

    /**
     * Guard for the global key operations.
     *
     * Actions that hit every row of keys_code (emptyTable(), a blanket
     * `expired_date IS NULL` delete, an unscoped devices reset) used to be
     * reachable by any authenticated reseller. They now require the same
     * admin levels as the `admin` filter. Returns a redirect Response when
     * the caller is not allowed, or NULL when the caller may proceed.
     */
    private function requireAdminLevel()
    {
        $user = $this->user;

        if (!$user || !in_array((int) $user->level, [1, 2], true)) {
            return redirect()->to('dashboard')->with('msgWarning', "Access Denied!");
        }

        return null;
    }

    /**
     * Guard for actions that act on one identified key: admins (1/2) may act
     * on any key, everyone else only on keys they registered.
     * Returns a redirect Response when refused, or NULL when allowed.
     */
    private function requireKeyAccess($dKey)
    {
        if (!$dKey) {
            return redirect()->to('keys')->with('msgDanger', "The user key no longer exists.");
        }

        $user = $this->user;

        if (in_array((int) $user->level, [1, 2], true)) {
            return null;
        }

        if ($user->username && $dKey->registrator == $user->username) {
            return null;
        }

        return redirect()->to('keys')->with('msgDanger', "Restricted to this user key.");
    }

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->user      = $this->userModel->getUser();
        $this->model     = new KeysModel();
        $this->time      = new \CodeIgniter\I18n\Time;

        $this->userId = session()->get('userid');

        /* ------- Game ------- */
        $this->game_list = [
            'PUBG' => 'PUBG Mobile'
        ];

        // (optional) static order reference
        $this->durationKeys = [1,2,5,24,72,168,336,720,1440,2160,2880,8760];

        /* ------- Fallback default prices (hours => ₹) ------- */
        $this->price = [
            1 => 30,
            2 => 50,
            5 => 80,
            24 => 150,
            72 => 350,
            168 => 600,
            336 => 850,
            720 => 1200,
            1440 => 1800,
            2160 => 3600,
            2880 => 4800,
            8760 => 14400,
        ];

        /* ------- Load durations from DB (source of truth) ------- */
        try {
            $pm     = new PriceModel();
            $table  = $pm->getTable();
            $fields = $pm->getFieldNames($table);

            // fetch all or only active based on is_active presence
            if (in_array('is_active', $fields, true)) {
                // safer builder to avoid orderBy bug
                $builder = $pm->builder();
                $builder->where('is_active', 1);
                $builder->orderBy('duration ASC');
                $rows = $builder->get()->getResultArray();
            } else {
                $rows = $pm->findAll();
            }

            if (!empty($rows)) {
                $tmp = [];
                foreach ($rows as $r) {
                    $d = (int)($r['duration'] ?? 0);
                    $p = (int)($r['price'] ?? 0);
                    if ($d > 0 && $p >= 0) {
                        $tmp[$d] = $p;
                    }
                }
                if ($tmp) {
                    ksort($tmp, SORT_NUMERIC);
                    $this->price = $tmp; // replace fallback with DB
                }
            }
        } catch (\Throwable $e) {
            // fallback defaults remain
        }

        /* ------- Build labels for UI dropdowns ------- */
        $this->duration = $this->buildDurationLabels(); // duration => "label — ₹price/Device"
    }

    public function index()
    {
        $model = $this->model;
        $user  = $this->user;

        if ($user->level != 1) {
            $keys = $model->where('registrator', $user->username)->findAll();
        } else {
            $keys = $model->select('user_key')->findAll();
        }
        $data = [
            'title'   => 'Keys',
            'user'    => $user,
            'keylist' => $keys,
            'time'    => $this->time,
        ];
        return view('Keys/list', $data);
    }

    public function download_all_Keys(){
        $model = $this->model;
        $keys  = $model->select('user_key')->findAll();
        $data  = '';
        for($i=0; $i<count($keys); $i++){
            $data .= $keys[$i]['user_key']."\n";
        }
        /* write_file('Newkeys.txt', $data);*/
        $this->downloadFile('Newkeys.txt');
    }

    public function download_new_Keys(){
        $this->downloadFile('new.txt');
    }

    function downloadFile($yourFile){
        $file = @fopen($yourFile, "rb");

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=Allkeys.txt');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . @filesize($yourFile));
        while (!feof($file)) {
            print(@fread($file, 1024 * 8));
            @ob_flush();
            @flush();
        }
    }

    public function alterKeys(){
        if ($denied = $this->requireAdminLevel()) {
            return $denied;
        }
        $model = $this->model;
        $model->where('expired_date <', date('Y-m-d H:i:s'))->delete();
        return redirect()->back()->with('msgSuccess', 'success');
    }
    
    public function deleteBLOCKKeys() {
        $user = $this->user;
        $model = $this->model;
        $usernameBL = $user->username;
        
        $data = $model->where('status', '0')->where('registrator', $usernameBL)->delete();

        if ($data) {
            return redirect()->back()->with('msgSuccess', 'success');
        } else {
            return redirect()->back()->with('msgError', 'Failed to delete keys');
        }
    }

    public function deleteKeys(){
        if ($denied = $this->requireAdminLevel()) {
            return $denied;
        }
        $model = $this->model;
        $model->emptyTable('keys_code');

        return redirect()->back()->with('msgSuccess', 'success');
    }
     
    public function delete_all_keys() {
        if ($denied = $this->requireAdminLevel()) {
            return $denied;
        }

        $model = $this->model;

        $deleted = $model->emptyTable('keys_code');

        if ($deleted) {
            return redirect()->back()->with('msgSuccess', ' Your All keys have been successfully deleted.');
        } else {
            return redirect()->back()->with('msgDanger', 'Failed to delete All keys or no keys found.');
        }
    }
 
    public function resetAlldevice()
    {
        if ($denied = $this->requireAdminLevel()) {
            return $denied;
        }

        $model = $this->model;
        $ok    = $model->set('devices', NULL)->update();

        if ($ok) {
            return redirect()->to('keys')->with('msgSuccess', 'Device counters reset for all keys.');
        }

        return redirect()->to('keys')->with('msgDanger', 'Failed to reset device counters.');
    }
  
    public function resetAlkeys()
    {
        if ($denied = $this->requireAdminLevel()) {
            return $denied;
        }

        $model = $this->model;
        $ok    = $model->set('devices', NULL)->update();

        if ($ok) {
            return redirect()->to('keys')->with('msgSuccess', 'Device counters reset for all keys.');
        }

        return redirect()->to('keys')->with('msgDanger', 'Failed to reset device counters.');
    }

    // ---------- UPDATED: bulk reset + backward compatibility ----------
    public function resetAllKeys(){
        $model = $this->model;
        $user  = $this->user;

        // Triggers
        $keys     = $this->request->getGet('userkey'); // legacy single delete path
        $isAll    = (bool) $this->request->getGet('all');
        $doReset  = (bool) $this->request->getGet('reset');

        // NEW bulk-reset path: /keys/resetAll?all=1&reset=1
        if ($isAll && $doReset){
            // scope: admin => all, else only own
            $builder = $model->builder();
            if ($user->level != 1){
                $builder->where('registrator', $user->username);
            }

            // count first (fresh builder for CI4 safety)
            $countBuilder = $model->builder();
            if ($user->level != 1){
                $countBuilder->where('registrator', $user->username);
            }
            $total = (int) $countBuilder->countAllResults(false);

            $ok = $builder->set('devices', null, true)->update();

            return $this->response->setJSON([
                'ok'          => (bool) $ok,
                'status'      => $ok ? 'ok' : 'err',
                'total_reset' => $total,
                'message'     => $ok ? 'Device counts reset for all visible keys' : 'No rows updated'
            ]);
        }

        if ($keys){
            // Delete-by-value is unscoped: verify the key exists and that this
            // user may act on it before removing it (same ownership idiom as
            // edit_key / api_key_reset, so a reseller can still delete their
            // own key from the list view).
            $dKey = $model->getKeys($keys);
            if ($denied = $this->requireKeyAccess($dKey)) {
                return $denied;
            }

            $deleted = $model->where('user_key', $keys)->delete();

            if ($deleted) {
                return redirect()->back()->with('msgSuccess', 'success');
            }

            return redirect()->to('keys')->with('msgDanger', 'Failed to delete key.');
        }

        return $this->response->setJSON(['ok'=>false,'message'=>'No action performed']);
    }

    //delete wasted keys
    public function startDate(){
        if ($denied = $this->requireAdminLevel()) {
            return $denied;
        }
        $model = $this->model;
        $deleted = $model->where('expired_date', null)->delete();

        if ($deleted) {
            return redirect()->back()->with('msgSuccess', 'success');
        }

        return redirect()->back()->with('msgDanger', 'No unused keys found.');
    }

    public function api_get_keys()
    {
        $model = $this->model;
        return $model->API_getKeys();
    }

    public function deleteExpired(){
        if ($denied = $this->requireAdminLevel()) {
            return $denied;
        }
        echo date('Y-m-d H:i:s');
        $model = $this->model;
        $deleted = $model->where('expired_date <', date('Y-m-d H:i:s'))->delete();

        if ($deleted) {
            return redirect()->back()->with('msgSuccess', 'success');
        }

        return redirect()->back()->with('msgDanger', 'No expired keys found.');
    }

    //delete wasted keys
    public function deleteUnused(){
        if ($denied = $this->requireAdminLevel()) {
            return $denied;
        }
        echo date('Y-m-d H:i:s');
        $model = $this->model;
        $deleted = $model->where('expired_date', null)->delete();

        if ($deleted) {
            return redirect()->back()->with('msgSuccess', 'success');
        }

        return redirect()->back()->with('msgDanger', 'No unused keys found.');
    }

    public function api_key_reset()
    {
        sleep(1);
        $model = $this->model;
        $keys  = $this->request->getGet('userkey');
        $reset = $this->request->getGet('reset');
        $db_key = $model->getKeys($keys);

        $rules = [];
        if ($db_key) {
            $total = $db_key->devices ? explode(',', $db_key->devices) : [];
            $rules = ['devices_total' => count($total), 'devices_max' => (int) $db_key->max_devices];
            $user = $this->user;
            if ($db_key->devices and $reset) {
                if ($user->level == 1 or $db_key->registrator == $user->username) {
                    $model->set('devices', NULL)
                          ->where('user_key', $keys)
                          ->update();
                    $rules = ['reset' => true, 'devices_total' => 0, 'devices_max' => $db_key->max_devices];
                }
            } else {
            }
        }

        $data = [
            'registered' => $db_key ? true : false,
            'keys'       => $keys,
        ];

        $real_response = array_merge($data, $rules);
        return $this->response->setJSON($real_response);
    }

    public function edit_key($key = false)
    {
        if ($this->request->getPost()) return $this->edit_key_action();
        $msgDanger = "The user key no longer exists.";
        if ($key) {
            $dKey = $this->model->getKeys($key, 'id_keys');
            $user = $this->user;
            if ($dKey) {
                if ($user->level == 1 or $dKey->registrator == $user->username) {
                    $validation = Services::validation();
                    $data = [
                        'title'     => 'Key',
                        'user'      => $user,
                        'key'       => $dKey,
                        'game_list' => $this->game_list,
                        'time'      => $this->time,
                        'key_info'  => getDevice($dKey->devices),
                        'messages'  => setMessage('Please carefuly edit information'),
                        'validation'=> $validation,
                    ];
                    return view('Keys/key_edit', $data);
                } else {
                    $msgDanger = "Restricted to this user key.";
                }
            }
        }
        return redirect()->to('keys')->with('msgDanger', $msgDanger);
    }

    private function edit_key_action()
    {
        $keys = $this->request->getPost('id_keys');
        $user = $this->user;
        $dKey = $this->model->getKeys($keys, 'id_keys');
        $game = implode(",", array_keys($this->game_list));
        if (!$dKey) {
            $msgDanger = "The user key no longer exists~";
        } else {
            if ($user->level == 1 or $dKey->registrator == $user->username) {
                $form_reseller = [
                    'status' => [
                        'label' => 'status',
                        'rules' => 'required|integer|in_list[0,1]',
                        'erros' => [
                            'integer' => 'Invalid {field}.',
                            'in_list' => 'Choose between list.'
                        ]
                    ]
                ];
                $form_admin = [
                    'id_keys' => [
                        'label' => 'keys',
                        'rules' => 'required|is_not_unique[keys_code.id_keys]|numeric',
                        'errors' => [
                            'is_not_unique' => 'Invalid keys.'
                        ],
                    ],
                    'game' => [
                        'label' => 'Games',
                        'rules' => "required|alpha_numeric_space|in_list[$game]",
                        'errors' => [
                            'alpha_numeric_space' => 'Invalid characters.'
                        ],
                    ],
                    'user_key' => [
                        'label' => 'User keys',
                        'rules' => "required|is_unique[keys_code.user_key,user_key,$dKey->user_key]",
                        'errors' => [
                            'is_unique' => '{field} has been taken.'
                        ],
                    ],
                    'duration' => [
                        'label' => 'duration',
                        'rules' => 'required|numeric|greater_than_equal_to[1]',
                        'errors' => [
                            'greater_than_equal_to' => 'Minimum {field} is invalid.',
                            'numeric' => 'Invalid day {field}.'
                        ]
                    ],
                    'max_devices' => [
                        'label' => 'devices',
                        'rules' => 'required|numeric|greater_than_equal_to[1]',
                        'errors' => [
                            'greater_than_equal_to' => 'Minimum {field} is invalid.',
                            'numeric' => 'Invalid max of {field}.'
                        ]
                    ],
                    'registrator' => [
                        'label' => 'registrator',
                        'rules' => 'permit_empty|alpha_numeric_space|min_length[4]'
                    ],
                    'expired_date' => [
                        'label' => 'expired',
                        'rules' => 'permit_empty|valid_date[Y-m-d H:i:s]',
                        'errors' => [
                            'valid_date' => 'Invalid {field} date.',
                        ]
                    ],
                    'devices' => [
                        'label' => 'device list',
                        'rules' => 'permit_empty'
                    ]
                ];
                $form_owner = [
                    'id_keys' => [
                        'label' => 'keys',
                        'rules' => 'required|is_not_unique[keys_code.id_keys]|numeric',
                        'errors' => [
                            'is_not_unique' => 'Invalid keys.'
                        ],
                    ],
                    'game' => [
                        'label' => 'Games',
                        'rules' => "required|alpha_numeric_space|in_list[$game]",
                        'errors' => [
                            'alpha_numeric_space' => 'Invalid characters.'
                        ],
                    ],
                    'user_key' => [
                        'label' => 'User keys',
                        'rules' => "required|is_unique[keys_code.user_key,user_key,$dKey->user_key]",
                        'errors' => [
                            'is_unique' => '{field} has been taken.'
                        ],
                    ],
                    'duration' => [
                        'label' => 'duration',
                        'rules' => 'required|numeric|greater_than_equal_to[1]',
                        'errors' => [
                            'greater_than_equal_to' => 'Minimum {field} is invalid.',
                            'numeric' => 'Invalid day {field}.'
                        ]
                    ],
                    'max_devices' => [
                        'label' => 'devices',
                        'rules' => 'required|numeric|greater_than_equal_to[1]',
                        'errors' => [
                            'greater_than_equal_to' => 'Minimum {field} is invalid.',
                            'numeric' => 'Invalid max of {field}.'
                        ]
                    ],
                    'registrator' => [
                        'label' => 'registrator',
                        'rules' => 'permit_empty|alpha_numeric_space|min_length[4]'
                    ],
                    'expired_date' => [
                        'label' => 'expired',
                        'rules' => 'permit_empty|valid_date[Y-m-d H:i:s]',
                        'errors' => [
                            'valid_date' => 'Invalid {field} date.',
                        ]
                    ],
                    'devices' => [
                        'label' => 'device list',
                        'rules' => 'permit_empty'
                    ]
                ];
                if ($user->level == 1) {
                    // Owner full rules.
                    $form_rules = $form_owner;
                    $devices = $this->request->getPost('devices');
                    $max_devices = $this->request->getPost('max_devices');

                    $data_saves = [
                        'game'        => $this->request->getPost('game'),
                        'user_key'    => $this->request->getPost('user_key'),
                        'duration'    => $this->request->getPost('duration'),
                        'max_devices' => $max_devices,
                        'status'      => $this->request->getPost('status'),
                        'registrator' => $this->request->getPost('registrator'),
                        'expired_date'=> $this->request->getPost('expired_date') ?: NULL,
                        'devices'     => setDevice($devices, $max_devices),
                    ];
                } elseif ($user->level == 2) {
                    // Admin 75% rules.
                    $form_rules  = $form_admin;
                    $devices     = $this->request->getPost('devices');
                    $max_devices = $this->request->getPost('max_devices');
                    $data_saves  = [
                        'game'        => $this->request->getPost('game'),
                        'user_key'    => $this->request->getPost('user_key'),
                        'duration'    => $this->request->getPost('duration'),
                        'max_devices' => $max_devices,
                        'status'      => $this->request->getPost('status'),
                        'registrator' => $this->request->getPost('registrator'),
                        'expired_date'=> $this->request->getPost('expired_date') ?: NULL,
                        'devices'     => setDevice($devices, $max_devices),
                    ];
                } else {
                    $form_rules = $form_reseller;
                    $data_saves = ['status' => $this->request->getPost('status')];
                }
                if (!$this->validate($form_rules)) {
                    return redirect()->back()->withInput()->with('msgDanger', 'Failed! Please check the error');
                } else {
                    $this->model->update($dKey->id_keys, $data_saves);
                    return redirect()->back()->with('msgSuccess', 'User key successfuly updated!');
                }
            } else {
                $msgDanger = "Restricted to this user key~";
            }
        }
        return redirect()->to('keys')->with('msgDanger', $msgDanger);
    }

    public function generate()
    {
        if ($this->request->getPost())
            return $this->generate_action();

        $user       = $this->user;
        $validation = Services::validation();

        $message = setMessage("<i class='bi bi-wallet'></i> Total Saldo $$user->saldo");
        if ($user->saldo <= 0) {
            $message = setMessage("Please top up to your beloved admin.", 'warning');
        }

        $data = [
            'title'     => 'Generate',
            'user'      => $user,
            'time'      => $this->time,
            'game'      => $this->game_list,
            'duration'  => $this->duration,
            'price'     => json_encode($this->price),
            'messages'  => $message,
            'validation'=> $validation,
        ];
        return view('Keys/generate', $data);
    }

    private function generate_action()
    {
        helper('text'); // for random_string
        $user       = $this->user;
        $game       = $this->request->getPost('game');
        $maxd       = $this->request->getPost('max_devices');

        // Restrict reseller (level 3) to maximum 2 devices
        if (isset($user->level) && intval($user->level) === 3 && intval($maxd) > 2) {
            return redirect()->back()->withInput()->with('msgDanger', 'Reseller cannot create keys for more than 2 devices.');
        }

        $drtn       = $this->request->getPost('duration');
        $twst       = $this->request->getPost('custominput');
        $cuslicense = $this->request->getPost('cuslicense');
        $getPrice   = getPrice($this->price, $drtn, $maxd);

        $game_list = implode(",", array_keys($this->game_list));
        $form_rules = [
            'game' => [
                'label' => 'Games',
                'rules' => "required|alpha_numeric_space|in_list[$game_list]",
                'errors' => [
                    'alpha_numeric_space' => 'Invalid characters.'
                ],
            ],
            'duration' => [
                'label' => 'duration',
                'rules' => 'required|numeric|greater_than_equal_to[1]',
                'errors' => [
                    'greater_than_equal_to' => 'Minimum {field} is invalid.',
                    'numeric' => 'Invalid day {field}.'
                ]
            ],
            'max_devices' => [
                'label' => 'devices',
                'rules' => 'required|numeric|greater_than_equal_to[1]',
                'errors' => [
                    'greater_than_equal_to' => 'Minimum {field} is invalid.',
                    'numeric' => 'Invalid max of {field}.'
                ]
            ],
        ];

        $validation  = Services::validation();
        $reduceCheck = ($user->saldo - $getPrice);
        if ($reduceCheck < 0) {
            $validation->setError('duration', 'Insufficient balance');
            return redirect()->back()->withInput()->with('msgWarning', 'Please top up to your beloved admin.');
        } else {
            if (!$this->validate($form_rules)) {
                return redirect()->back()->withInput()->with('msgDanger', 'Failed! Please check the error');
            } else {
                $msg = "Successfuly Generated.";

                // SAME AS ORIGINAL: expiry set NAHI kar rahe (NOT ACTIVATED rahegi)
                $license = $drtn . 'H>' . $user->fullname . '-' . random_string('alnum', 5);

                if ($twst == "custom") {
                    if ($user->level == 1 || $user->level == 2 || $user->level == 3 ) {
                        if (strlen($cuslicense) > 3 && strlen($cuslicense) < 20){
                            $findKey = $this->model->getKeysGame(['user_key' => $cuslicense, 'game' => $game]);
                            if ($findKey) {
                                return redirect()->back()->with('msgDanger', 'Key already exists!!');
                            } else {
                                $license = $cuslicense;
                            }
                        } else {
                            return redirect()->back()->with('msgDanger', 'Custom Key is too Short/Long');
                        }
                    } else {
                        return redirect()->back()->with('msgDanger', 'You do not have permission to create custom keys.');
                    }
                }

                $data_response = [
                    'game'         => $game,
                    'user_key'     => $license,
                    'duration'     => $drtn,
                    // 'expired_date' => NULL,  // NOT ACTIVATED
                    'max_devices'  => $maxd,
                    'registrator'  => $user->username,
                    'admin_id'     => $this->userId
                ];

                $idKeys = $this->model->insert($data_response);

                $this->userModel->update(session('userid'), ['saldo' => $reduceCheck]);

                $history = new HistoryModel();
                $history->insert([
                    'keys_id' => $idKeys,
                    'user_do' => $user->username,
                    'info'    => "$game|" . substr($license, 0, 5) . "|$drtn|$maxd"
                ]);

                $other_response = ['fees' => $getPrice];

                session()->setFlashdata(array_merge($data_response, $other_response));

                return redirect()->back()->with('msgSuccess', $msg);
            }
        }
    }

    /* ============================================================
     * RANDOM-ONLY GENERATOR (no custom key), 15-char suffix
     * ========================================================== */
    public function generate_random()
    {
        if ($this->request->getMethod() === 'post') {
            return $this->generate_random_action();
        }

        $user       = $this->user;
        $validation = Services::validation();

        $data = [
            'title'      => 'Generate (Random)',
            'user'       => $user,
            'time'       => $this->time,
            'validation' => $validation,
            'game'       => $this->game_list,
            'duration'   => $this->duration,
            'price'      => json_encode($this->price),
        ];

        return view('Keys/generate_random', $data);
    }

    public function generate_random_action()
    {
        helper('text');

        $user = $this->user;

        $game = (string) $this->request->getPost('game');
        $drtn = (string) $this->request->getPost('duration');
        $maxd = (int)    $this->request->getPost('max_devices');

        if (isset($user->level) && intval($user->level) === 3 && intval($maxd) > 2) {
            return redirect()->back()->withInput()->with('msgDanger', 'Reseller cannot create keys for more than 2 devices.');
        }

        $game_list = implode(",", array_keys($this->game_list));
        $form_rules = [
            'game' => [
                'label' => 'Games',
                'rules' => "required|alpha_numeric_space|in_list[$game_list]",
                'errors' => [
                    'alpha_numeric_space' => 'Invalid characters.'
                ],
            ],
            'duration' => [
                'label' => 'duration',
                'rules' => 'required|numeric|greater_than_equal_to[1]',
                'errors' => [
                    'greater_than_equal_to' => 'Minimum {field} is invalid.',
                    'numeric' => 'Invalid day {field}.'
                ]
            ],
            'max_devices' => [
                'label' => 'devices',
                'rules' => 'required|numeric|greater_than_equal_to[1]',
                'errors' => [
                    'greater_than_equal_to' => 'Minimum {field} is invalid.',
                    'numeric' => 'Invalid max of {field}.'
                ]
            ],
        ];

        $validation = Services::validation();

        $getPrice    = getPrice($this->price, $drtn, $maxd);
        $reduceCheck = ($user->saldo - $getPrice);
        if ($reduceCheck < 0) {
            $validation->setError('duration', 'Insufficient balance');
            return redirect()->back()->withInput()->with('msgWarning', 'Please top up to your beloved admin.');
        }

        if (!$this->validate($form_rules)) {
            return redirect()->back()->withInput()->with('msgDanger', 'Failed! Please check the error');
        }

        // ONLY random key; NOT ACTIVATED (no expiry set here)
        $license = random_string('alnum', 15);

        $findKey = $this->model->getKeysGame(['user_key' => $license, 'game' => $game]);
        if ($findKey) {
            $license = random_string('alnum', 15);
        }

        $data_response = [
            'game'         => $game,
            'user_key'     => $license,
            'duration'     => $drtn,
            // 'expired_date' => NULL, // keep null
            'max_devices'  => $maxd,
            'registrator'  => $user->username,
            'admin_id'     => $this->userId
        ];
        $idKeys = $this->model->insert($data_response);

        $this->userModel->update(session('userid'), ['saldo' => $reduceCheck]);

        $history = new HistoryModel();
        $history->insert([
            'keys_id' => $idKeys,
            'user_do' => $user->username,
            'info'    => "$game|" . substr($license, 0, 5) . "|$drtn|$maxd"
        ]);

        session()->setFlashdata(array_merge($data_response, ['fees' => $getPrice]));

        return redirect()->back()->with('msgSuccess', 'Successfuly Generated.');
    }

    /**
     * Build $this->duration labels from current $this->price
     */
    protected function buildDurationLabels(): array
    {
        $order  = [1,2,5,24,72,168,336,720,1440,2160,2880,8760];
        $labels = [];

        // ensure all durations (DB) included in order
        $all = array_unique(array_merge($order, array_keys($this->price)));
        sort($all, SORT_NUMERIC);

        foreach ($all as $h) {
            if (!isset($this->price[$h])) continue;
            $amt = (int)$this->price[$h];

            if ($h < 24) {
                $txt = $h . ' Hours';
            } elseif ($h % 720 === 0) {
                $txt = ($h/720) . ' Months';
            } elseif ($h % 24 === 0) {
                $txt = ($h/24) . ' Days';
            } else {
                $txt = $h . ' Hours';
            }
            if ($h === 1) $txt = '1 Hours Trail';
            if ($h === 2) $txt = '2 Hours Trail';

            $labels[$h] = $txt . ' &mdash; ₹' . $amt . '/Device';
        }
        return $labels;
    }
}