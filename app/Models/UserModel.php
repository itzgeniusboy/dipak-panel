<?php

namespace App\Models;

use CodeIgniter\Model;
use Hermawan\DataTables\DataTable;

class UserModel extends Model
{
    /* ====== MAIN TABLE ====== */
    protected $table      = 'users';
    protected $primaryKey = 'id_users';

    /**
     * IMPORTANT: find()/first()/findAll() ab OBJECT return karenge,
     * taki `$targetUser->uplink` jaise access me error na aaye.
     */
    protected $returnType = 'object';

    protected $allowedFields = [
    'username','fullname','saldo','level','status','uplink',
    'password','user_ip','expiration_date','device_id',
    'device_reset_count','last_reset_at',
    // NEW ↓
    'telegram_chat_id','twofa_enabled'
];


    protected $useTimestamps = true;
    // Agar aapke columns ka naam different hai to yaha set kar sakte hain:
    // protected $createdField  = 'created_at';
    // protected $updatedField  = 'updated_at';

    /* ====== (optional) SECOND TABLE META ====== */
    protected $table_m       = 'modname';
    protected $primaryKey_m  = 'id';
    protected $allowedFields_m = ['modname'];

    /* ================== METHODS ================== */

    public function getUser($userid = false, $where = 'default')
    {
        $userid = $userid ?: session()->userid;
        $where  = ($where === 'default' ? 'id_users' : $where);

        $row = $this->where($where, $userid)
            ->get()
            ->getFirstRow();

        return $row ?: null;
    }

    public function getUserList($select = '*')
    {
        return $this->select($select)
            ->get()
            ->getResultObject();
    }

    public function API_getUser()
    {
        $connect = db_connect();
        $builder = $connect->table('users');

        $user = $this->getUser();
        if ($user && (int)$user->level !== 1) {
            $builder->where('uplink', $user->username);
        }

        $builder->select('CONCAT(users.id_users) as id, username, fullname, saldo, level, status, uplink, device_id, device_reset_count, last_reset_at');

        return DataTable::of($builder)
            ->setSearchableColumns(['username', 'fullname', 'saldo', 'uplink'])
            ->format('fullname', static function ($value) {
                return $value ? esc(word_limiter($value, 1, '')) : '';
            })
            ->format('level', static function ($value) {
                return getLevel($value);
            })
            ->toJson(true);
    }

    public function checkAuthFilter()
    {
        $time    = new \CodeIgniter\I18n\Time();
        $session = session();
        $time_ex = $session->time_login;

        if ($time::now()->isBefore($time_ex)) {
            $userCek = $this->getUser($session->userid);

            if (!$userCek) {
                $msg = 'User not found!';
            } elseif ((int)$userCek->level > 3) {
                $msg = 'Level account invalid!';
            } elseif ((int)$userCek->status !== 1) {
                $msg = 'Status account changed!';
            } else {
                return $userCek;
            }
        } else {
            $msg = 'Session account expired!';
        }

        return $this->AuthSessionLogout($msg);
    }

    public function AuthSessionLogout($msg = 'Session terminate')
    {
        $list = ['userid', 'unames', 'time_login', 'time_since'];
        session()->remove($list);
        return redirect()->to('login')->with('msgDanger', $msg);
    }
}