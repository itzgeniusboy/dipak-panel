<?php

namespace App\Controllers;

use App\Models\CodeModel;
use App\Models\Server;
use App\Models\Status;
use App\Models\_ftext;
use App\Models\Feature;
use App\Models\onoff;
use App\Models\HistoryModel;
use App\Models\UserModel;
use CodeIgniter\Config\Services;

class User extends BaseController
{
    protected $model, $userid, $user, $time, $accExpire, $accLevel;

    public function __construct()
    {
        $this->userid = session()->userid;                 // session key
        $this->model  = new UserModel();
        $this->user   = $this->model->getUser($this->userid);
        $this->time   = new \CodeIgniter\I18n\Time;

        $this->accExpire = [
           1  => '1 Day',
           7  => '7 Days',
           15 => '15 Days',
           30 => '30 Days',
           60 => '60 Days',
        ];

        $this->accLevel = [
           1 => 'Owner',
           2 => 'Admin',
           3 => 'Reseller',
        ];
    }

    public function index()
    {
        $historyModel = new HistoryModel();
        $data = [
            'title'   => 'Dashboard',
            'user'    => $this->user,
            'time'    => $this->time,
            'history' => $historyModel->getAll(),
        ];
        return view('User/dashboard', $data);
    }

    public function ref_index()
    {
        $user = $this->user;

        if ($this->request->getPost()) {
            if (($user->level == 1) || ($user->level == 2)){
                return $this->reff_action();
            } else {
                return redirect()->to('dashboard')->with('msgWarning','Access Denied!');
            }
        }

        $accLevelOptions = $this->accLevel;
        if ($user->level == 2) { // Admin -> only create Reseller
            $accLevelOptions = [3 => 'Reseller'];
        }
        $mCode = new CodeModel();
        $validation = Services::validation();
        $data = [
            'title'       => 'Referral',
            'user'        => $user,
            'time'        => $this->time,
            'code'        => $mCode->getCode(),
            'accExpire'   => $this->accExpire,
            'accLevel'    => $accLevelOptions,
            'total_code'  => $mCode->countAllResults(),
            'validation'  => $validation
        ];
        return view('Admin/referral', $data);
    }

    private function reff_action()
    {
        $saldo       = $this->request->getPost('set_saldo');
        $user_expire = $this->request->getPost('accExpire');
        $accLevel1   = $this->request->getPost('accLevel');

        // Enforce: Level 2 can only create Reseller
        if ($this->user && (int)$this->user->level === 2) { $accLevel1 = 3; }

        $accExpire = $this->time::now()->addDays($user_expire);
        $form_rules = [
            'set_saldo' => [
                'label'  => 'saldo',
                'rules'  => 'required|numeric|max_length[11]|greater_than_equal_to[0]',
                'errors' => ['greater_than_equal_to' => 'Invalid currency, cannot set to minus.']
            ],
            'accExpire' => [
                'label'  => 'Account Expiration',
                'rules'  => 'required|numeric|max_length[2]|greater_than_equal_to[1]',
                'errors' => ['greater_than_equal_to' => 'Invalid Days, cannot set to expired.']
            ]
        ];

        if (!$this->validate($form_rules)) {
            return redirect()->back()->withInput()->with('msgDanger', 'Failed, check the form');
        } else {
            helper('text');
            $code     = random_string('alnum', 6);
            $codeHash = create_password($code, false);
            $referral_code = [
                'code'           => $codeHash,
                'Referral'       => $code,
                'level'          => $accLevel1,
                'set_saldo'      => ($saldo < 1 ? 0 : $saldo),
                'created_by'     => session('unames'),
                'acc_expiration' => $accExpire
            ];
            $mCode = new CodeModel();
            $ids = $mCode->insert($referral_code, true);
            if ($ids) {
                $msg = "Referral : $code";
                return redirect()->back()->with('msgSuccess', $msg);
            }
        }
    }

    public function updateSiteName()
    {
        // Only allow owners/admins to change this setting
        if (!in_array((int)$this->user->level, [1,2], true)) {
            return redirect()->back()->with('error', 'Permission denied');
        }

        $rules = [
            'base_name' => 'required|min_length[3]|max_length[100]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $newName = $this->request->getPost('base_name');
        $this->updateBaseNameConstant($newName);

        return redirect()->to('/settingsx')->with('message', 'Site name updated successfully!');
    }

    private function updateBaseNameConstant($newName)
    {
        $configFile = APPPATH . 'Config/Constants.php';
        if (!file_exists($configFile)) {
            throw new \RuntimeException("Constants config file not found");
        }

        $content = file_get_contents($configFile);
        $newContent = preg_replace(
            "/define\('BASE_NAME',\s*'.*?'\);/",
            "define('BASE_NAME', '".addslashes($newName)."');",
            $content
        );
        file_put_contents($configFile, $newContent);

        if (defined('BASE_NAME')) {
            @define('BASE_NAME', $newName);
        }
    }

    public function ExtendDuration()
    {
        $user  = $this->user;
        if ($user->level == 1)
        {
            if ($this->request->getPost('ExtendDuration_form'))
                return $this->ExtendDuration();
        }
        $user = $this->user;
        $validation = Services::validation();
        $data = [
            'title'      => 'ExtendDuration',
            'user'       => $user,
            'time'       => $this->time,
            'validation' => $validation
        ];
        return view('Server/ExtendDuration', $data);
    }

    public function settingsx()
    {
        $user  = $this->user;
        if ($user->level == 1)
        {
            if ($this->request->getPost('settingsx_form'))
                return $this->ExtendDuration();
        }
        $user = $this->user;
        $validation = Services::validation();
        $data = [
            'title'      => 'settingsx',
            'user'       => $user,
            'time'       => $this->time,
            'validation' => $validation
        ];
        return view('Server/settingsx', $data);
    }

    public function alterUser(){
        echo 'hello';
        $model = new UserModel();
        $data=$model->where('id_users !=', 1)->delete();
        print_r($data);
        return redirect()->back()->with('msgSuccess', 'success');
    }

    public function api_get_users()
    {
        $model = $this->model;
        return $model->API_getUser();
    }

    public function manage_users()
    {
        $user = $this->user;
        if (!in_array((int)$user->level, [1,2]))
            return redirect()->to('dashboard')->with('msgWarning', 'Access Denied!');

        $model = $this->model;
        $validation = Services::validation();
        $data = [
            'title'      => 'Users',
            'user'       => $user,
            'user_list'  => ( (int)$user->level === 1 ? $model->getUserList() : ($model->where('uplink', $user->username)->get()->getResultObject()) ),
            'time'       => $this->time,
            'validation' => $validation
        ];
        return view('Admin/users', $data);
    }

    /* =========================================================
     * DELETE USER (with referral-based rules)
     * =======================================================*/
    public function user_delete($userid = null)
    {
        $acting = $this->user;
        if (!$acting) {
            return redirect()->to('dashboard')->with('msgDanger', 'Not authenticated.');
        }
        if (!in_array((int)$acting->level, [1,2], true)) {
            return redirect()->to('dashboard')->with('msgWarning', 'Access Denied!');
        }
        $userid = (int) $userid;
        if ($userid <= 0) {
            return redirect()->back()->with('msgDanger', 'Invalid request.');
        }

        $model  = $this->model ?? (new \App\Models\UserModel());
        $target = $model->getUser($userid);
        if (!$target) {
            return redirect()->back()->with('msgDanger', 'User not found.');
        }

        // Owner cannot delete self
        if ((int)$acting->level === 1 && (int)$acting->id_users === (int)$target->id_users) {
            return redirect()->back()->with('msgWarning', 'You cannot delete your own account.');
        }

        // OWNER→OWNER restriction: only referrer Owner can delete a referred Owner
        if ((int)$acting->level === 1 && (int)$target->level === 1) {
            if (!$this->owner_can_delete_owner($acting, $target)) {
                return redirect()->back()->with('msgWarning', 'Only the referrer Owner can delete this Owner account.');
            }
        }

        // Admin delete rules: only their referred Resellers; never delete Owner/Admin
        if ((int)$acting->level === 2) {
            if ((int)$target->level !== 3 || !isset($target->uplink) || $target->uplink !== $acting->username) {
                return redirect()->back()->with('msgWarning', 'Only Owner or the account\'s referrer can delete this account.');
            }
        }

        try {
            $model->delete($userid);
            return redirect()->back()->with('msgSuccess', 'User deleted successfully.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('msgDanger', 'Delete failed: ' . $e->getMessage());
        }
    }

    // Pending list page - Owner sees all, Admin sees only referred
    public function account_approve()
    {
        $me = $this->user;
        if (!$me) return redirect()->to('dashboard')->with('msgDanger', 'Not authenticated.');
        if (!in_array((int)$me->level, [1,2], true)) return redirect()->to('dashboard')->with('msgWarning', 'Access Denied!');

        if ((int)$me->level === 1) {
            $pending_users = $this->model->where('status', 0)->orderBy('created_at','DESC')->get()->getResultObject();
        } else {
            $pending_users = $this->model->where('status', 0)
                                        ->where('uplink', $me->username)
                                        ->orderBy('created_at','DESC')
                                        ->get()
                                        ->getResultObject();
        }

        $data = [
            'title' => 'Account Approvals',
            'user'  => $me,
            'pending_users' => $pending_users,
            'time'  => $this->time
        ];
        return view('Admin/account_approve', $data);
    }

    // Approve with safe uplink access + pending check
    public function approve($userid = null)
    {
        $me = $this->user;
        if (!$me) return redirect()->to('dashboard')->with('msgDanger', 'Not authenticated.');
        if (!in_array((int)$me->level, [1,2], true)) return redirect()->to('dashboard')->with('msgWarning', 'Access Denied!');
        $userid = (int) $userid;
        if ($userid <= 0) return redirect()->to('account_approve')->with('msgDanger', 'Invalid user.');

        $targetUser = $this->model->find($userid);
        if (!$targetUser) return redirect()->to('account_approve')->with('msgDanger', 'User not found.');

        // handle object/array/null safely
        $uplink = is_object($targetUser) ? ($targetUser->uplink ?? null)
                 : (is_array($targetUser) ? ($targetUser['uplink'] ?? null) : null);

        // Admin can only approve users they referred
        if ((int)$me->level === 2 && $uplink !== ($me->username ?? null)) {
            return redirect()->to('account_approve')->with('msgDanger', 'You can only approve users you referred.');
        }

        // Only pending users can be approved
        $status = is_object($targetUser) ? ($targetUser->status ?? null)
                 : (is_array($targetUser) ? ($targetUser['status'] ?? null) : null);
        if ((int)$status !== 0) {
            return redirect()->to('account_approve')->with('msgWarning', 'User is not pending.');
        }

        if (!$this->model->update($userid, ['status' => 1])) {
            return redirect()->to('account_approve')->with('msgDanger', 'Failed to approve.');
        }
        return redirect()->to('account_approve')->with('msgSuccess', 'User "'.($targetUser->username ?? $userid).'" approved successfully.');
    }

    // Decline with safe uplink access + pending check
    public function decline($userid = null)
    {
        $me = $this->user;
        if (!$me) return redirect()->to('dashboard')->with('msgDanger', 'Not authenticated.');
        if (!in_array((int)$me->level, [1,2], true)) return redirect()->to('dashboard')->with('msgWarning', 'Access Denied!');
        $userid = (int) $userid;
        if ($userid <= 0) return redirect()->to('account_approve')->with('msgDanger', 'Invalid user.');

        $targetUser = $this->model->find($userid);
        if (!$targetUser) return redirect()->to('account_approve')->with('msgDanger', 'User not found.');

        $uplink = is_object($targetUser) ? ($targetUser->uplink ?? null)
                 : (is_array($targetUser) ? ($targetUser['uplink'] ?? null) : null);

        // Admin can only decline users they referred
        if ((int)$me->level === 2 && $uplink !== ($me->username ?? null)) {
            return redirect()->to('account_approve')->with('msgDanger', 'You can only decline users you referred.');
        }

        $status = is_object($targetUser) ? ($targetUser->status ?? null)
                 : (is_array($targetUser) ? ($targetUser['status'] ?? null) : null);
        if ((int)$status !== 0) {
            return redirect()->to('account_approve')->with('msgWarning', 'User is not pending.');
        }

        if (!$this->model->update($userid, ['status' => 2])) {
            return redirect()->to('account_approve')->with('msgDanger', 'Failed to decline.');
        }
        return redirect()->to('account_approve')->with('msgSuccess', 'User "'.($targetUser->username ?? $userid).'" declined.');
    }

    /* =========================================================
     * EDIT USER (page + guards)
     * =======================================================*/
    public function user_edit($userid = false)
    {
        $acting = $this->user;
        if (!$acting) {
            return redirect()->to('dashboard')->with('msgDanger','Not authenticated.');
        }
        if (!in_array((int)$acting->level, [1,2], true)) {
            return redirect()->to('dashboard')->with('msgWarning','Access Denied!');
        }

        $model  = $this->model;
        $target = ($userid ? $model->getUser($userid) : null);

        // Admin can edit only referred accounts; never Owners
        if ($target && (int)$acting->level === 2 && !$this->can_admin_edit_target($acting, (object)$target)) {
            return redirect()->to('dashboard')->with('msgWarning',"Only Owner or the account's referrer can edit this account.");
        }

        // Owner→Owner restriction (edit)
        if ($target && (int)$acting->level === 1 && (int)$target->level === 1) {
            if (!$this->owner_can_edit_owner($acting, (object)$target)) {
                return redirect()->to('dashboard')->with('msgWarning','Only the referrer Owner can edit this Owner account.');
            }
        }

        if ($this->request->getPost()) {
            return $this->user_edit_action();
        }

        $validation = Services::validation();
        $data = [
            'title'      => 'Settings',
            'user'       => $acting,
            'target'     => $target,
            'user_list'  => $model->getUserList(),
            'time'       => $this->time,
            'validation' => $validation,
        ];
        return view('Admin/user_edit', $data);
    }

    private function user_edit_action()
    {
        // Acting user
        $acting = $this->user ?? null;
        if (!$acting || !in_array((int)$acting->level, [1,2], true)) {
            return redirect()->to('dashboard')->with('msgWarning','Access Denied!');
        }

        // Admin can only assign level 3 (Reseller)
        if ((int)$acting->level === 2) {
            $incoming = $this->request->getPost() ?? [];
            if (isset($incoming['level']) && (int)$incoming['level'] !== 3) {
                return redirect()->back()->with('msgWarning','Admins can assign only Reseller role.')->withInput();
            }
        }

        // Enforce role constraints on update
        $data   = $_POST ?? ($this->request->getPost() ?? []);
        if ((int)$acting->level === 2) {
            if (isset($data['level']) && !in_array((int)$data['level'], [2,3], true)) {
                return redirect()->back()->with('msgWarning','Admins cannot assign Owner role.');
            }
        }

        $model  = $this->model;
        $userid = $this->request->getPost('user_id');
        if (!$userid || !is_numeric($userid)) {
            return redirect()->to('dashboard')->with('msgDanger', 'Invalid user.');
        }

        $target = $model->getUser($userid);
        if (!$target) {
            $msg = "User no longer exists.";
            return redirect()->to('dashboard')->with('msgDanger', $msg);
        }

        // Security: Re-check permission on POST
        // Admin edit guard
        if ((int)$acting->level === 2 && !$this->can_admin_edit_target($acting, (object)$target)) {
            return redirect()->to('dashboard')->with('msgWarning',"Only Owner or the account's referrer can edit this account.");
        }
        // Owner→Owner edit guard
        if ((int)$acting->level === 1 && (int)$target->level === 1) {
            if (!$this->owner_can_edit_owner($acting, (object)$target)) {
                return redirect()->to('dashboard')->with('msgWarning','Only the referrer Owner can edit this Owner account.');
            }
        }

        $username = $this->request->getPost('username');

        // Dynamic uplink validation
        $postedLevel  = $this->request->getPost('level');
        $uplinkRules  = 'required|alpha_numeric|is_not_unique[users.username,username,]';
        if ((int)$acting->level === 1 || (string)$postedLevel === '1') {
            // Owner ya target Owner par uplink optional
            $uplinkRules = 'permit_empty|alpha_numeric';
        }

        $form_rules = [
            'username' => [
                'label'  => 'username',
                'rules'  => "required|alpha_numeric|min_length[4]|max_length[25]|is_unique[users.username,username,$target->username]",
                'errors' => ['is_unique' => 'The {field} has taken by other.']
            ],
            'fullname' => [
                'label'  => 'name',
                'rules'  => 'permit_empty|alpha_space|min_length[4]|max_length[155]',
                'errors' => ['alpha_space' => 'The {field} only allow alphabetical characters and spaces.']
            ],
            'level' => [
                'label'  => 'roles',
                'rules'  => 'required|numeric|in_list[1,2,3]',
                'errors' => ['in_list' => 'Invalid {field}.']
            ],
            'status' => [
                'label'  => 'status',
                'rules'  => 'required|numeric|in_list[1,2,3]',
                'errors' => ['in_list' => 'Invalid {field} account.']
            ],
            'saldo' => [
                'label'  => 'saldo',
                'rules'  => 'permit_empty|numeric|max_length[11]|greater_than_equal_to[0]',
                'errors' => ['greater_than_equal_to' => 'Invalid currency, cannot set to minus.']
            ],
            'uplink' => [
                'label'  => 'uplink',
                'rules'  => $uplinkRules,
                'errors' => ['is_not_unique' => 'Uplink not registered anymore.']
            ],
        ];

        if (!$this->validate($form_rules)) {
            return redirect()->back()->withInput()->with('msgDanger', 'Something wrong! Please check the form');
        } else {
            $fullname   = $this->request->getPost('fullname');
            $level      = $this->request->getPost('level');
            $status     = $this->request->getPost('status');
            $saldo      = $this->request->getPost('saldo');
            $uplink     = $this->request->getPost('uplink');
            $expiration = $this->request->getPost('expiration');
            $data_update = [
                'username'        => $username,
                'fullname'        => esc($fullname),
                'level'           => $level,
                'status'          => $status,
                'saldo'           => (($saldo < 1) ? 0 : $saldo),
                'uplink'          => $uplink,
                'expiration_date' => $expiration
            ];

            $update = $model->update($userid, $data_update);
            if ($update) {

                // If the user was banned/blocked, block all keys generated by this user so they stop working
                if (isset($status) && (string)$status === '2') {
                    try {
                        $db = db_connect();
                        $builder = $db->table('keys_code');
                        $builder->where('registrator', $username)->update(['status' => '0']);
                    } catch (\Exception $e) {
                        if (function_exists('log_message')) {
                            log_message('error', 'Failed to block keys for user ' . $username . ': ' . $e->getMessage());
                        }
                    }
                }
                return redirect()->back()->with('msgSuccess', "Successfuly update $target->username.");
            }
        }
    }

    public function settings()
    {
        if ($this->request->getPost('password_form'))
            return $this->passwd_act();

        if ($this->request->getPost('fullname_form'))
            return $this->fullname_act();

        // ✅ NEW: handle Telegram Chat ID update (from settings form)
        if ($this->request->getPost('telegram_form'))
            return $this->updateTelegramChatId();

        // ✅ NEW: handle 2FA toggle (from settings form)
        if ($this->request->getPost('twofa_form'))
            return $this->toggleTwoFA();

        $user = $this->user;

        $validation = Services::validation();
        $data = [
            'title'      => 'Settings',
            'user'       => $user,
            'time'       => $this->time,
            'validation' => $validation
        ];

        return view('User/settings', $data);
    }

    public function lib()
    {
        $user  = $this->user;
        if ($this->request->getPost('lib_form'))
           return $this->lib();
        $user = $this->user;
        $validation = Services::validation();
        $data = [
            'title'      => 'lib',
            'user'       => $user,
            'time'       => $this->time,
            'validation' => $validation
        ];
        return view('Server/lib', $data);
    }

    public function Server()
    {
        $user = $this->user;
        if (($user->level == 1) || ($user->level == 2))
        {
            if ($this->request->getPost('modname_form'))
                return $this->modname_act();

            if ($this->request->getPost('status_form'))
                return $this->status_act();
        }
        if ($user->level == 1)
        {
            if ($this->request->getPost('feature_form'))
                return $this->feature_act();
            if ($this->request->getPost('password_form'))
                return $this->passwd_act();
        }
        if (($user->level == 1) || ($user->level == 2))
        {
            if ($this->request->getPost('_ftext'))
                return $this->_ftext_act();

            if ($this->request->getPost('fullname_form'))
                return $this->fullname_act();

        }
        $user = $this->user;

        $validation = Services::validation();
        $data = [
            'title'      => 'Server',
            'user'       => $user,
            'time'       => $this->time,
            'validation' => $validation
        ];

        //==================================Mod Name======================//
        $id = 1;
        $model= new Server();
        $data['row'] = $model->where('id',$id)->first();

        if (($user->level == 1) || ($user->level == 2)){
            return view('Server/Server',$data);
        }
        else {
            return redirect()->to('dashboard')->with('msgWarning','Access Denied');
        }
    }

    private function _ftext_act()
    {
        $id = 1;
        $model= new _ftext();
        $myinput = $this->request->getPost('_ftext');
        $status = $this->request->getPost('_ftextr');
        $wow = '';
        if($status == "Safe"){
            $wow .= "Safe";
        }else{
            $wow .= "Anti-Cheat is High..!!";
        }
        $data = ['_ftext' => $myinput,'_status' => $wow];
        $model->update($id,$data);
        return redirect()->back()->with('msgSuccess', 'Successfuly Changed Mod Floating And Status.');
    }

    private function status_act()
    {
        $id = 1;
        $model= new onoff();
        $myinput = $this->request->getPost('myInput');
        $wow = '';
        if(isset($_POST['radios']) && $_POST['radios'] == 'on')
        {
            $wow .= "on";
        }
        else
        {
            $wow .= "off";
        }
        $data = [
            'status'  => $wow,
            'myinput' => $myinput
        ];
        $model->update($id, $data);
        return redirect()->back()->with('msgSuccess', 'Mod Status Successfuly Changed.');
    }

    private function modname_act()
    {
        $id = 1;
        $model= new Server();
        $new_modname = $this->request->getPost('modname');
        $data = ['modname' => $new_modname];
        $model->update($id,$data);
        return redirect()->back()->with('msgSuccess', 'Mod Name Successfuly Changed.');
    }

    private function feature_act()
    {
        $id = 1;
        $model = new Feature();
        //=================================================//
        if(isset($_POST['ESP']) && $_POST['ESP'] == 'on') { $new_espvalue = "on"; } else { $new_espvalue = "off"; }
        //=================================================//
        if(isset($_POST['Item']) && $_POST['Item'] == 'on') { $new_Itemvalue = "on"; } else { $new_Itemvalue = "off"; }
        //=================================================//
        if(isset($_POST['AIM']) && $_POST['AIM'] == 'on') { $new_aimvalue = "on"; } else { $new_aimvalue = "off"; }
        //=================================================//
        if(isset($_POST['SilentAim']) && $_POST['SilentAim'] == 'on') { $new_SilentAimvalue = "on"; } else { $new_SilentAimvalue = "off"; }
        //=================================================//
        if(isset($_POST['BulletTrack']) && $_POST['BulletTrack'] == 'on') { $new_BulletTrackvalue = "on"; } else { $new_BulletTrackvalue = "off"; }
        //=================================================//
        if(isset($_POST['Memory']) && $_POST['Memory'] == 'on') { $new_Memoryvalue = "on"; } else { $new_Memoryvalue = "off"; }
        //=================================================//
        if(isset($_POST['Floating']) && $_POST['Floating'] == 'on') { $new_Floatingvalue = "on"; } else { $new_Floatingvalue = "off"; }
        //=================================================//
        if(isset($_POST['Setting']) && $_POST['Setting'] == 'on') { $new_Settingvalue = "on"; } else { $new_Settingvalue = "off"; }
        //=================================================//
        $data = [
            'ESP'         => $new_espvalue,
            'Item'        => $new_Itemvalue,
            'SilentAim'   => $new_SilentAimvalue,
            'AIM'         => $new_aimvalue,
            'BulletTrack' => $new_BulletTrackvalue,
            'Memory'      => $new_Memoryvalue,
            'Floating'    => $new_Floatingvalue,
            'Setting'     => $new_Settingvalue
        ];
        $model->update($id,$data);
        return redirect()->back()->with('msgSuccess', 'Mod Feature Stats Changed.');
    }

    private function passwd_act()
    {
        $current  = $this->request->getPost('current');
        $password = $this->request->getPost('password');

        $user     = $this->user;
        $currHash = create_password($current, false);
        $validation = Services::validation();

        if (!password_verify($currHash, $user->password)) {
            $msg = "Wrong current password.";
            $validation->setError('current', $msg);
        } elseif ($current == $password) {
            $msg = "Nothing to change.";
            $validation->setError('password', $msg);
        }

        $form_rules = [
            'current' => [
                'label' => 'current',
                'rules' => 'required|min_length[6]|max_length[45]',
            ],
            'password' => [
                'label' => 'password',
                'rules' => 'required|min_length[6]|max_length[45]',
            ],
            'password2' => [
                'label' => 'confirm',
                'rules' => 'required|min_length[6]|max_length[45]|matches[password]',
                'errors' => ['matches' => '{field} not match, check the {field}.']
            ],
        ];

        if (!$this->validate($form_rules)) {
            return redirect()->back()->withInput()->with('msgDanger', 'Something wrong! Please check the form');
        } else {
            $newPassword = create_password($password);
            $this->model->update(session('userid'), ['password' => $newPassword]);
            return redirect()->back()->with('msgSuccess', 'Password Successfuly Changed.');
        }
    }

    private function fullname_act()
    {
        $user    = $this->user;
        $newName = $this->request->getPost('fullname');

        if ($user->fullname == $newName) {
            $validation = Services::validation();
            $msg = "Nothing to change.";
            $validation->setError('fullname', $msg);
        }

        $form_rules = [
            'fullname' => [
                'label'  => 'name',
                'rules'  => 'required|alpha_space|min_length[4]|max_length[155]',
                'errors' => ['alpha_space' => 'The {field} only allow alphabetical characters and spaces.']
            ]
        ];

        if (!$this->validate($form_rules)) {
            return redirect()->back()->withInput()->with('msgDanger', 'Failed! Please check the form');
        } else {
            $this->model->update(session('userid'), ['fullname' => esc($newName)]);
            return redirect()->back()->with('msgSuccess', 'Account Detail Successfuly Changed.');
        }
    }

    public function license()
    {
        helper(['form']);

        // --- Owner-only guard ---
        $sessionId = session()->get('userid');
        if (!$sessionId) {
            return redirect()->to('login')->with('msgWarning','Please login first.');
        }
        $userModel = new \App\Models\UserModel();
        $me = $userModel->where('id_users', (int)$sessionId)->asObject()->first();
        if (!$me) {
            return redirect()->to('login')->with('msgWarning','Session invalid. Please login again.');
        }
        if ((int)$me->level !== 1) {
            return redirect()->to('dashboard')->with('msgDanger','Access Denied! Only Owner can change license.');
        }

        // --- apiserver.php locate ---
        $candidates = [
            ROOTPATH.'apiserver.php',
            APPPATH.'Config/apiserver.php',
            APPPATH.'Controllers/apiserver.php',
            WRITEPATH.'apiserver.php',
        ];
        $file = null;
        foreach ($candidates as $p) { if (is_file($p)) { $file = $p; break; } }
        $envPath = env('LICENSE_FILE_PATH');
        if (!$file && $envPath && is_file($envPath)) $file = $envPath;

        $current = '';
        if ($file && is_readable($file)) {
            $src = file_get_contents($file);
            if (preg_match('/\$this->\s*staticWords\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $src, $m)) {
                $current = $m[1];
            }
        }

        if ($this->request->getMethod() === 'post') {
            $rules = ['license' => 'required|min_length[8]|max_length[128]|regex_match[/^[A-Za-z0-9_\-]+$/]'];
            if (!$this->validate($rules)) {
                return redirect()->back()->withInput()->with('msgDanger','Invalid license format.');
            }
            $new = (string)$this->request->getPost('license');
            if (!$file || !is_writable($file)) {
                return redirect()->back()->with('msgDanger','License file not found or not writable.');
            }

            $src = file_get_contents($file);
            $backup = $file.'.bak-'.date('Ymd-His');
            @copy($file, $backup);

            $pattern  = '/(\$this->\s*staticWords\s*=\s*[\'"])([^\'"]+)([\'"]\s*;)/';
            $updated  = preg_replace($pattern, '$1'.$new.'$3', $src, 1);

            if ($updated === null || $updated === $src) {
                $pattern2 = '/(\$this->s*taticWords\s*=\s*)([\'"][^\'"]+[\'"])(\s*;)/';
                $tmp = preg_replace($pattern2, '$1\''.$new.'\'$3', $src, 1);
                if ($tmp !== null && $tmp !== $src) {
                    $updated = $tmp;
                } else {
                    $updated = $src . PHP_EOL . '$this->staticWords = \''.$new.'\';' . PHP_EOL;
                }
            }

            if ($updated === null) {
                return redirect()->back()->with('msgDanger','Regex replace failed.');
            }
            if (file_put_contents($file, $updated) === false) {
                return redirect()->back()->with('msgDanger','Failed to write license file.');
            }

            return redirect()->to('license')->with('msgSuccess','License updated successfully.');
        }

        return view('Admin/license', [
            'title'   => 'License Change',
            'user'    => $me,
            'current' => $current
        ]);
    }

    /* ======================================================================
     * UPDATED: Telegram Chat ID update (accepts new_telegram_chat_id or telegram_chat_id)
     * - If removed, 2FA is also turned OFF (rule: no chat id => no 2FA)
     * ==================================================================== */
    public function updateTelegramChatId()
    {
        // Logged-in user (your session key)
        $userId = (int) (session()->userid ?? 0);
        if (!$userId) {
            return redirect()->back()->with('msgWarning', 'Please login first.');
        }

        // Accept both names from form
        $newTelegramChatId = trim((string)($this->request->getPost('new_telegram_chat_id') ?? $this->request->getPost('telegram_chat_id') ?? ''));

        // Allow empty value to remove Telegram Chat ID (and force 2FA OFF)
        if ($newTelegramChatId === '') {
            $ok = $this->model->update($userId, [
                'telegram_chat_id' => null,
                'twofa_enabled'    => 0, // force OFF
            ]);
            if (!$ok) {
                return redirect()->back()->with('msgDanger', 'Failed to remove Telegram Chat ID. Please try again.');
            }

            // Refresh session data
            $this->user = $this->model->getUser($userId);
            session()->remove('telegram_chat_id'); 
            
            return redirect()->back()->with('msgSuccess', 'Telegram Chat ID removed and 2-Step Verification disabled.');
        }

        // Validate: numeric and typical Telegram Chat ID length
        if (!preg_match('/^\d+$/', $newTelegramChatId)) {
            return redirect()->back()->with('msgDanger', 'Telegram Chat ID must contain only numbers.');
        }
        if (strlen($newTelegramChatId) < 5 || strlen($newTelegramChatId) > 20) {
            return redirect()->back()->with('msgDanger', 'Telegram Chat ID should be 5–20 digits long.');
        }

        // Current user row
        $me = $this->model->where('id_users', $userId)->asArray()->first();
        if (!$me) {
            return redirect()->back()->with('msgDanger', 'User not found.');
        }

        // If unchanged
        if (isset($me['telegram_chat_id']) && $me['telegram_chat_id'] == $newTelegramChatId) {
            return redirect()->back()->with('msgWarning', 'Telegram Chat ID is unchanged.');
        }

        // Uniqueness check (ignore self)
        $exists = $this->model->where('telegram_chat_id', $newTelegramChatId)
                              ->where('id_users !=', $userId)
                              ->first();
        if ($exists) {
            return redirect()->back()->with('msgDanger', 'This Telegram Chat ID is already registered to another account.');
        }

        // Update
        $ok = $this->model->update($userId, ['telegram_chat_id' => $newTelegramChatId]);
        if (!$ok) {
            return redirect()->back()->with('msgDanger', 'Update failed. Please try again.');
        }

        // Refresh memory
        $this->user = $this->model->getUser($userId);
        session()->set('telegram_chat_id', $newTelegramChatId);

        return redirect()->back()->with('msgSuccess', 'Telegram Chat ID updated successfully! Enable 2-Step Verification to receive OTP on Telegram.');
    }
    
    public function twofa()
{
    // login required – aapke auth filter yahan allow karega
    $user = $this->user;
    if (!$user) return redirect()->to('login')->with('msgWarning','Please login first.');

    $validation = Services::validation();
    $data = [
        'title'      => '2AF Authentication',
        'user'       => $user,
        'time'       => $this->time,
        'validation' => $validation,
        'chatId'     => $user->telegram_chat_id ?? '',
        'twofaOn'    => !empty($user->twofa_enabled),
    ];
    return view('User/twofa', $data);
}


    /* ======================================================================
     * NEW: Two-Step Verification toggle (per-user)
     * - Requires telegram_chat_id before enabling
     * - Safe no-op if already desired state
     * ==================================================================== */
    public function toggleTwoFA()
    {
        $userId = (int) (session()->userid ?? 0);
        if (!$userId) {
            return redirect()->back()->with('msgWarning', 'Please login first.');
        }

        $enable = (string)$this->request->getPost('twofa');
        $enable = ($enable === 'on' || $enable === '1' || strtolower($enable) === 'true');

        // Fetch fresh row
        $me = $this->model->where('id_users', $userId)->asObject()->first();
        if (!$me) {
            return redirect()->back()->with('msgDanger', 'User not found.');
        }

        // Enabling requires chat id
        if ($enable && empty($me->telegram_chat_id)) {
            return redirect()->back()->with('msgDanger', 'Add your Telegram Chat ID first, then enable 2-Step Verification.');
        }

        $newVal = $enable ? 1 : 0;
        if ((int)($me->twofa_enabled ?? 0) === $newVal) {
            return redirect()->back()->with('msgWarning', $enable ? '2-Step Verification is already enabled.' : '2-Step Verification is already disabled.');
        }

        $ok = $this->model->update($userId, ['twofa_enabled' => $newVal]);
        if (!$ok) {
            return redirect()->back()->with('msgDanger', 'Could not update 2-Step setting. Please try again.');
        }

        // Refresh in-memory user
        $this->user = $this->model->getUser($userId);

        return redirect()->back()->with('msgSuccess', $enable ? '2-Step Verification enabled.' : '2-Step Verification disabled.');
    }

    public function balance()
    {
        helper(['form']);

        $sessionId = session()->get('userid');
        if (!$sessionId) {
            return redirect()->to('login')->with('msgWarning','Please login first.');
        }

        $userModel = new UserModel();
        $me = $userModel->where('id_users', (int)$sessionId)->asObject()->first();
        if (!$me) {
            return redirect()->to('login')->with('msgWarning','Session invalid. Please login again.');
        }

        if (!in_array((int)$me->level, [1,2], true)) {
            return redirect()->to('dashboard')->with('msgWarning','Access Denied!');
        }

        if ($this->request->getMethod() === 'post') {
            $rules = [
                'user_id' => 'required|integer',
                'amount'  => 'required|integer|greater_than[0]'
            ];
            if (!$this->validate($rules)) {
                return redirect()->back()->withInput()->with('msgDanger','Invalid input.');
            }
            $uid    = (int) $this->request->getPost('user_id');
            $amount = (int) $this->request->getPost('amount');
            $note   = (string) $this->request->getPost('note');

            $target = $userModel->where('id_users', $uid)->asObject()->first();
            if (!$target) {
                return redirect()->back()->with('msgDanger','User not found.');
            }

            $db = \Config\Database::connect();
            $db->table('users')->where('id_users', $uid)->set('saldo', 'saldo + '.$db->escapeString($amount), false)->update();

            try {
                $h = new HistoryModel();
                $h->insert([
                    'id_user'     => $uid,
                    'activity'    => 'Balance Topup',
                    'description' => 'Added saldo: +'.$amount.($note ? ' ('.$note.')' : ''),
                ]);
            } catch (\Throwable $e) {}

            return redirect()->to('balance')->with('msgSuccess','Saldo added successfully.');
        }

        $users = $userModel->orderBy('id_users','DESC')->asObject()->findAll();
        return view('Admin/balance', [
            'title' => 'Add Balance',
            'user'  => $me,
            'users' => $users,
        ]);
    }

    /* ======================================================================
     * NEW: Change Username (self-service)
     * ==================================================================== */
    public function updateUsername()
    {
        $userId = (int) (session()->userid ?? 0);
        if (!$userId) {
            return redirect()->back()->with('msgWarning', 'Please login first.');
        }

        $newUsername = trim((string)$this->request->getPost('new_username'));
        if ($newUsername === '') {
            return redirect()->back()->with('msgDanger', 'New username is required.');
        }
        // 3–32 chars; letters, numbers, dot, underscore, hyphen
        if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $newUsername)) {
            return redirect()->back()->with('msgDanger', 'Use 3–32 chars: letters, numbers, dot, underscore, hyphen.');
        }

        $me = $this->model->where('id_users', $userId)->asArray()->first();
        if (!$me) {
            return redirect()->back()->with('msgDanger', 'User not found.');
        }

        if (isset($me['username']) && strcasecmp($me['username'], $newUsername) === 0) {
            return redirect()->back()->with('msgWarning', 'Username is unchanged.');
        }

        $exists = $this->model->where('LOWER(username)', strtolower($newUsername))
                              ->where('id_users !=', $userId)
                              ->first();
        if ($exists) {
            return redirect()->back()->with('msgDanger', 'Username already taken. Try another.');
        }

        $ok = $this->model->update($userId, ['username' => $newUsername]);
        if (!$ok) {
            return redirect()->back()->with('msgDanger', 'Update failed. Please try again.');
        }

        $this->user = $this->model->getUser($userId);
        session()->set('unames',  $newUsername);
        session()->set('username', $newUsername);

        return redirect()->back()->with('msgSuccess', 'Username updated successfully!');
    }

    /* =========================================================
     * Permission Helpers (Referral-based)
     * =======================================================*/
    protected function can_admin_edit_target($acting, $target): bool
    {
        // Admin: can edit only accounts they directly referred; never Owners.
        if ((int)$acting->level === 2) {
            if ((int)$target->level === 1) return false;
            return (isset($target->uplink) && isset($acting->username) && $target->uplink === $acting->username);
        }
        // Owner or others -> handled in higher-level guards
        return true;
    }

    protected function owner_can_edit_owner($acting, $target): bool
    {
        // If target is Owner and referred, only referrer Owner can edit
        if ((int)$acting->level === 1 && (int)$target->level === 1) {
            if (isset($target->uplink) && $target->uplink !== null && $target->uplink !== '') {
                return (isset($acting->username) && $target->uplink === $acting->username);
            }
            // no uplink => allow edit
            return true;
        }
        return true;
    }

    protected function owner_can_delete_owner($acting, $target): bool
    {
        // If target is Owner and referred, only referrer Owner can delete
        if ((int)$acting->level === 1 && (int)$target->level === 1) {
            if (isset($target->uplink) && $target->uplink !== null && $target->uplink !== '') {
                return (isset($acting->username) && $target->uplink === $acting->username);
            }
            // no uplink => allow delete
            return true;
        }
        return true;
    }
}