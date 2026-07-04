<?php

namespace Kyqo\Notifications\Channels;

use Kyqo\Database\DatabaseManager;
use Kyqo\Notifications\Notification;

/**
 * Persists notifications to the `notifications` table.
 *
 * Required schema:
 *   CREATE TABLE notifications (
 *       id           VARCHAR(32)  NOT NULL PRIMARY KEY,
 *       notifiable_type VARCHAR(255) NOT NULL,
 *       notifiable_id   BIGINT       NOT NULL,
 *       type         VARCHAR(255) NOT NULL,
 *       data         TEXT         NOT NULL,
 *       read_at      DATETIME         NULL,
 *       created_at   DATETIME     NOT NULL
 *   );
 */
class DatabaseChannel
{
    public function __construct(private DatabaseManager $db) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toDatabase')) {
            throw new \LogicException(get_class($notification) . ' must implement toDatabase() to use the database channel.');
        }

        $data = $notification->toDatabase($notifiable);

        $this->db->connection()->table('notifications')->insert([
            'id'               => $notification->id,
            'notifiable_type'  => get_class($notifiable),
            'notifiable_id'    => $notifiable->{$notifiable->getPrimaryKey() ?? 'id'} ?? 0,
            'type'             => get_class($notification),
            'data'             => json_encode($data),
            'read_at'          => null,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
    }
}
