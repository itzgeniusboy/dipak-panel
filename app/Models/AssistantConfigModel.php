<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Tiny key/value store for assistant-wide settings, e.g. the optional Gemini
 * API key. Values live server-side only — the UI can set them but never reads a
 * secret back, so a saved key is never sent to a browser.
 */
class AssistantConfigModel extends Model
{
    protected $table          = 'assistant_config';
    protected $primaryKey     = 'config_key';
    protected $returnType     = 'object';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = ['config_key', 'config_value', 'updated_at'];

    public function ensureSchema(): void
    {
        if (db_connect()->tableExists($this->table)) {
            return;
        }

        $forge = \Config\Database::forge();
        $forge->addField([
            'config_key'   => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
            'config_value' => ['type' => 'TEXT', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('config_key', true);
        $forge->createTable($this->table, true);
    }

    public function get(string $key, string $default = ''): string
    {
        $this->ensureSchema();
        $row = $this->find($key);

        return $row && $row->config_value !== null ? (string) $row->config_value : $default;
    }

    public function put(string $key, string $value): void
    {
        $this->ensureSchema();
        $data = ['config_key' => $key, 'config_value' => $value, 'updated_at' => date('Y-m-d H:i:s')];

        if ($this->find($key)) {
            $this->update($key, $data);
        } else {
            $this->insert($data);
        }
    }
}