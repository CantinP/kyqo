<?php

namespace Kyqo\Notifications;

use Kyqo\Notifications\Channels\DatabaseChannel;
use Kyqo\Notifications\Channels\MailChannel;
use Kyqo\Notifications\Channels\SlackChannel;

/**
 * Dispatches a notification through the requested channels.
 */
class NotificationSender
{
    public function __construct(
        private MailChannel     $mailChannel,
        private DatabaseChannel $dbChannel,
        private SlackChannel    $slackChannel
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $channels = $notification->via($notifiable);

        foreach ($channels as $channel) {
            try {
                match ($channel) {
                    'mail'     => $this->mailChannel->send($notifiable, $notification),
                    'database' => $this->dbChannel->send($notifiable, $notification),
                    'slack'    => $this->slackChannel->send($notifiable, $notification),
                    default    => null,
                };
            } catch (\Throwable $e) {
                error_log('[Kyqo\Notifications] Channel [' . $channel . '] failed: ' . $e->getMessage());
            }
        }
    }

    public function sendNow(object $notifiable, Notification $notification): void
    {
        $this->send($notifiable, $notification);
    }
}
