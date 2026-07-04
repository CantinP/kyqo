<?php

namespace Kyqo\Http\Session;

use Kyqo\Database\DatabaseManager;

/**
 * Session handler backed by a database table.
 *
 * Required table schema (MySQL / PostgreSQL):
 *
 *   CREATE TABLE sessions (
 *       id         VARCHAR(255) NOT NULL PRIMARY KEY,
 *       payload    TEXT         NOT NULL,
 *       last_activity INT       NOT NULL
 *   );
 */
class DatabaseSessionHandler implements \SessionHandlerInterface
{
    private string $table;

    public function __construct(
        private DatabaseManager $db,
        string $table = 'sessions'
    ) {
        $this->table = $table;
    }

    public function open(string $savePath, string $sessionName): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $row = $this->db->connection()->table($this->table)
            ->where('id', '=', $id)
            ->first();

        return $row ? base64_decode($row->payload ?? $row['payload']) : '';
    }

    public function write(string $id, string $data): bool
    {
        $payload = base64_encode($data);
        $now     = time();

        $exists = $this->db->connection()->table($this->table)
            ->where('id', '=', $id)
            ->first();

        if ($exists) {
            $this->db->connection()->table($this->table)
                ->where('id', '=', $id)
                ->update(['payload' => $payload, 'last_activity' => $now]);
        } else {
            $this->db->connection()->table($this->table)
                ->insert(['id' => $id, 'payload' => $payload, 'last_activity' => $now]);
        }

        return true;
    }

    public function destroy(string $id): bool
    {
        $this->db->connection()->table($this->table)
            ->where('id', '=', $id)
            ->delete();
        return true;
    }

    public function gc(int $maxlifetime): int|false
    {
        $cutoff = time() - $maxlifetime;
        $this->db->connection()->table($this->table)
            ->where('last_activity', '<', $cutoff)
            ->delete();
        return true;
    }
}
