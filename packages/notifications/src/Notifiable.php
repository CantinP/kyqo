<?php

namespace Kyqo\Notifications;

/**
 * Notifiable trait — mix into any model to give it notification capabilities.
 *
 * Usage:
 *   class User extends Model
 *   {
 *       use Notifiable;
 *   }
 *
 *   $user->notify(new InvoicePaid($invoice));
 *   $user->notifications();           // all notifications from DB
 *   $user->unreadNotifications();     // unread only
 *   $user->markNotificationsAsRead(); // mark all as read
 */
trait Notifiable
{
    public function notify(Notification $notification): void
    {
        app(NotificationSender::class)->send($this, $notification);
    }

    public function notifyNow(Notification $notification): void
    {
        app(NotificationSender::class)->sendNow($this, $notification);
    }

    public function notifications(): array
    {
        return $this->queryNotifications()->get();
    }

    public function unreadNotifications(): array
    {
        return $this->queryNotifications()->whereNull('read_at')->get();
    }

    public function markNotificationsAsRead(): void
    {
        $this->queryNotifications()->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Route mail notifications to the right address.
     * Override if the email column is not called 'email'.
     */
    public function routeNotificationForMail(): string
    {
        return $this->email ?? $this->attributes['email'] ?? '';
    }

    private function queryNotifications(): \Kyqo\Database\QueryBuilder
    {
        $db = app('db')->connection();
        return $db->table('notifications')
            ->where('notifiable_type', '=', static::class)
            ->where('notifiable_id',   '=', $this->{$this->primaryKey ?? 'id'} ?? 0)
            ->orderBy('created_at', 'DESC');
    }
}
