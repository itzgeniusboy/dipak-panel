<?php

namespace App\Controllers;

use App\Models\KeysModel;
use Config\Database;

class Connect extends BaseController
{
    protected $model, $game, $uKey, $sDev, $maintenance, $staticWords;

    public function __construct()
    {
        $this->initializeDatabase();
        $this->model = new KeysModel();
        $this->checkMaintenanceStatus();
        $this->staticWords = "Vm8Lk7Uj2JmsjCPVPVjrLa7zgfx3uz9E";
    }

    protected function initializeDatabase()
    {
        include('conn.php');
        $this->db = $conn; // Store the connection for reuse
    }

    protected function checkMaintenanceStatus()
    {
        $sql = "SELECT status FROM onoff WHERE id=1";
        $result = mysqli_query($this->db, $sql);
        $status = mysqli_fetch_assoc($result);
        $this->maintenance = ($status['status'] == 'on');
    }

    public function index()
    {
        if ($this->request->getPost()) {
            return $this->index_post();
        }
        
        return $this->showErrorPage();
    }

    protected function showErrorPage()
    {
        $html = "<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8'>
  <title>TOPTEN - Error 403 - Forbidden</title>
  <style>
    @import url('https://fonts.googleapis.com/css?family=Creepster|Nosifer|Roboto');
    .maincontainer {
      position: relative;
      width: 100%;
      max-width: 800px;
      margin: 0 auto;
      text-align: center;
    }
    .bat {
      position: absolute;
      animation: fly 15s linear infinite;
    }
    .foregroundimg {
      width: 100%;
      max-width: 800px;
    }
    .errorcode {
      font-family: 'Nosifer', cursive;
      color: #d10000;
      text-align: center;
      font-size: 5em;
      margin: 20px 0;
    }
    .errortext {
      font-family: 'Creepster', cursive;
      color: #d10000;
      text-align: center;
      font-size: 3em;
      margin: 20px 0;
    }
    @keyframes fly {
      0% { transform: translateX(-100px) rotate(0deg); opacity: 0; }
      10% { opacity: 1; }
      90% { opacity: 1; }
      100% { transform: translateX(1000px) rotate(360deg); opacity: 0; }
    }
  </style>
</head>
<body>
<div class='maincontainer'>
  <div class='bat'>
    <img class='wing leftwing' src='https://aimieclouse.com/Media/Portfolio/Error403Forbidden/bat-wing.png'>
    <img class='body' src='https://aimieclouse.com/Media/Portfolio/Error403Forbidden/bat-body.png' alt='bat'>
    <img class='wing rightwing' src='https://aimieclouse.com/Media/Portfolio/Error403Forbidden/bat-wing.png'>
  </div>
  <div class='bat'>
    <img class='wing leftwing' src='https://aimieclouse.com/Media/Portfolio/Error403Forbidden/bat-wing.png'>
    <img class='body' src='https://aimieclouse.com/Media/Portfolio/Error403Forbidden/bat-body.png' alt='bat'>
    <img class='wing rightwing' src='https://aimieclouse.com/Media/Portfolio/Error403Forbidden/bat-wing.png'>
  </div>
  <div class='bat'>
    <img class='wing leftwing' src='https://aimieclouse.com/Media/Portfolio/Error403Forbidden/bat-wing.png'>
    <img class='body' src='https://aimieclouse.com/Media/Portfolio/Error403Forbidden/bat-body.png' alt='bat'>
    <img class='wing rightwing' src='https://aimieclouse.com/Media/Portfolio/Error403Forbidden/bat-wing.png'>
  </div>
  <img class='foregroundimg' src='https://aimieclouse.com/Media/Portfolio/Error403Forbidden/HauntedHouseForeground.png' alt='haunted house'>
</div>
<h1 class='errorcode'>ERROR 403</h1>
<div class='errortext'>
  <svg xmlns='http://www.w3.org/2000/svg' width='1em' height='1em' viewBox='0 0 256 256'>
    <!-- SVG paths here -->
  </svg> Nikal Lode now!
</div>
</body>
</html>";

        return $html;
    }

    public function index_post()
    {
        $isMT = $this->maintenance;
        $game = $this->request->getPost('game');
        $uKey = $this->request->getPost('user_key');
        $sDev = $this->request->getPost('serial');

        $form_rules = [
            'game' => 'required|alpha_dash',
            // This endpoint is public (excepted from the auth + csrf filters),
            // so user_key is charset-checked as well: license keys are built
            // from alnum, spaces and `> - _ . :` (see Keys::generate_action /
            // Keys::generate_random_action), never from quotes, backslashes,
            // backticks, semicolons or control characters.
            'user_key' => [
                'label'  => 'user key',
                'rules'  => 'required|min_length[1]|max_length[36]|regex_match[/^[^\'"`\\\\;\x00-\x1F]+$/]',
                'errors' => [
                    'regex_match' => 'Invalid characters.',
                ],
            ],
            'serial' => 'required|alpha_dash'
        ];

        if (!$this->validate($form_rules)) {
            return $this->response->setJSON([
                'status' => false,
                'reason' => "Bad Parameter"
            ]);
        }

        if ($isMT) {
            $sql = "SELECT myinput FROM onoff WHERE id=1";
            $result = mysqli_query($this->db, $sql);
            $maintenanceData = mysqli_fetch_assoc($result);
            
            return $this->response->setJSON([
                'status' => true,
                'reason' => $maintenanceData['myinput']
            ]);
        }

        if (!$game || !$uKey || !$sDev) {
            return $this->response->setJSON([
                'status' => false,
                'reason' => 'INVALID PARAMETER'
            ]);
        }

        $time = new \CodeIgniter\I18n\Time;
        $findKey = $this->model->getKeysGame(['user_key' => $uKey, 'game' => $game]);

        if (!$findKey) {
            return $this->response->setJSON([
                'status' => false,
                'reason' => 'USER OR GAME NOT REGISTERED'
            ]);
        }

        if ($findKey->status != 1) {
            return $this->response->setJSON([
                'status' => false,
                'reason' => 'USER BLOCKED'
            ]);
        }

        $id_keys = $findKey->id_keys;
        $duration = $findKey->duration;
        $expired = $findKey->expired_date;
        $max_dev = $findKey->max_devices;
        $devices = $findKey->devices;

        if (!$expired) {
            $setExpired = $time::now()->addHours($duration);
            $this->model->update($id_keys, ['expired_date' => $setExpired]);
            $expired = $setExpired;
        } elseif (!$time::now()->isBefore($expired)) {
            return $this->response->setJSON([
                'status' => false,
                'reason' => 'EXPIRED KEY'
            ]);
        }

        $devicesAdd = $this->checkDevicesAdd($sDev, $devices, $max_dev);
        if ($devicesAdd === false) {
            return $this->response->setJSON([
                'status' => false,
                'reason' => 'MAX DEVICE REACHED'
            ]);
        }

        if (is_array($devicesAdd)) {
            $this->model->update($id_keys, $devicesAdd);
        }

        $featureData = $this->getFeatureData();
        $modData = $this->getModData();
        $textData = $this->getTextData();
        $expiryData = $this->getExpiryData($uKey);

        $real = "$game-$uKey-$sDev-$this->staticWords";
        $expiry = $expired ?: $time::now()->addDays($duration);

        return $this->response->setJSON([
            'status' => true,
            'data' => [
                'real' => $real,
                'token' => md5($real),
                'modname' => $modData['modname'],
                'mod_status' => $textData['_status'],
                'credit' => $textData['_ftext'],
                'ESP' => $featureData['ESP'],
                'Item' => $featureData['Item'],
                'AIM' => $featureData['AIM'],
                'SilentAim' => $featureData['SilentAim'],
                'BulletTrack' => $featureData['BulletTrack'],
                'Floating' => $featureData['Floating'],
                'Memory' => $featureData['Memory'],
                'Setting' => $featureData['Setting'],
                'expired_date' => $expiryData['expired_date'],
                'EXP' => $expiry,
                'exdate' => $expiry,
                'device'=> $max_dev,
                'rng' => $time->getTimestamp()
            ]
        ]);
    }

    protected function checkDevicesAdd($serial, $devices, $max_dev)
    {
        $lsDevice = explode(",", $devices);
        $cDevices = !empty($devices) ? count($lsDevice) : 0;
        $serialOn = in_array($serial, $lsDevice);

        if ($serialOn) {
            return true;
        }

        if ($cDevices < $max_dev) {
            $lsDevice[] = $serial;
            $setDevice = reduce_multiples(implode(",", $lsDevice), ",", true);
            return ['devices' => $setDevice];
        }

        return false;
    }

    protected function getFeatureData()
    {
        $sql = "SELECT * FROM Feature WHERE id=1";
        $result = mysqli_query($this->db, $sql);
        return mysqli_fetch_assoc($result);
    }

    protected function getModData()
    {
        $sql = "SELECT modname FROM modname WHERE id=1";
        $result = mysqli_query($this->db, $sql);
        return mysqli_fetch_assoc($result);
    }

    protected function getTextData()
    {
        $sql = "SELECT * FROM _ftext WHERE id=1";
        $result = mysqli_query($this->db, $sql);
        return mysqli_fetch_assoc($result);
    }

    protected function getExpiryData($uKey)
    {
        // Bound parameter - never interpolate $uKey into the SQL string.
        // Same prepared-statement idiom as App\Libraries\LoginThrottle.
        $db = Database::connect();

        return $db->query('SELECT expired_date FROM keys_code WHERE user_key = ?', [$uKey])->getRowArray();
    }
}