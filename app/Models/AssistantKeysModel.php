<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * API keys for the Circuit assistant.
 *
 * The full key is shown exactly once (on creation). Only its SHA-256 is
 * stored, plus a short public prefix used to find the row before the constant
 * time comparison. Keys are 160-bit random, so a fast hash is appropriate —
 * there is no low-entropy secret to brute force.
 */
class AssistantKeysModel extends Model
{
    protected $table          = 'assistant_keys';
    protected $primaryKey     = 'id';
    protected $returnType     = 'object';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = [
        'user_id', 'label', 'key_prefix', 'key_hash', 'scopes',
        'last_used_at', 'last_used_ip', 'expires_at', 'revoked_at', 'created_at',
    ];

    public const PREFIX_LEN = 12; // "ckt_" + 8 chars

    /**
     * Self-provisioning so a freshly loaded schema (tools/host.sh) and a live
     * database both gain the table without a separate migration step.
     */
    public function ensureSchema(): void
    {
        if (db_connect()->tableExists($this->table)) {
            return;
        }

        $forge = \Config\Database::forge();
        $forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'      => ['type' => 'INT', 'null' => false],
            'label'        => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
            'key_prefix'   => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => false],
            'key_hash'     => ['type' => 'CHAR', 'constraint' => 64, 'null' => false],
            'scopes'       => ['type' => 'TEXT', 'null' => true],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'last_used_ip' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'expires_at'   => ['type' => 'DATETIME', 'null' => true],
            'revoked_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->addKey('user_id');
        $forge->addKey('key_prefix');
        $forge->createTable($this->table, true);
    }

    /**
     * @return array{id:int, plain:string, prefix:string, label:string}
     */
    public function generateKey(int $userId, string $label, ?array $scopes = null): array
    {
        $this->ensureSchema();

        $plain  = 'ckt_' . bin2hex(random_bytes(20));
        $prefix = substr($plain, 0, self::PREFIX_LEN);

        $id = $this->insert([
            'user_id'    => $userId,
            'label'      => mb_substr(trim($label) !== '' ? trim($label) : 'Assistant key', 0, 64),
            'key_prefix' => $prefix,
            'key_hash'   => hash('sha256', $plain),
            'scopes'     => json_encode($scopes ?: ['*']),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['id' => (int) $id, 'plain' => $plain, 'prefix' => $prefix, 'label' => $label];
    }

    public function findByKey(string $plain): ?object
    {
        $this->ensureSchema();

        if (strlen($plain) < self::PREFIX_LEN || ! str_starts_with($plain, 'ckt_')) {
            return null;
        }

        $rows = $this->where('key_prefix', substr($plain, 0, self::PREFIX_LEN))->findAll();
        $hash = hash('sha256', $plain);

        foreach ($rows as $row) {
            if (hash_equals((string) $row->key_hash, $hash) && empty($row->revoked_at)) {
                return $row;
            }
        }

        return null;
    }

    public function touchUsage(int $id, string $ip): void
    {
        $this->update($id, [
            'last_used_at' => date('Y-m-d H:i:s'),
            'last_used_ip' => substr($ip, 0, 45),
        ]);
    }

    public function forUser(int $userId): array
    {
        $this->ensureSchema();

        return $this->where('user_id', $userId)->orderBy('id', 'DESC')->findAll();
    }

    public function scopesOf(object $row): array
    {
        $decoded = json_decode((string) ($row->scopes ?? ''), true);

        return is_array($decoded) && $decoded ? $decoded : ['*'];
    }
}