<?php
namespace App\Models;

use CodeIgniter\Model;

class PriceModel extends Model
{
    protected $table = 'price_config';
    protected $primaryKey = 'duration';
    protected $allowedFields = ['duration', 'price', 'is_active'];
    public $useTimestamps = false;
}
