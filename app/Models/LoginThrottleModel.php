<?php
namespace App\Models;

use CodeIgniter\Model;

class LoginThrottleModel extends Model
{
    protected $table         = 'login_throttle';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['fingerprint','attempts','blocked_until','last_attempt_at'];
    protected $useTimestamps = false;

    public function getByFp(string $fp): ?array
    {
        return $this->where('fingerprint', $fp)->first();
    }
}