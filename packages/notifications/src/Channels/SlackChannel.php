<?php

namespace Kyqo\Notifications\Channels;

use Kyqo\Notifications\Notification;
use Kyqo\Notifications\Messages\SlackMessage;

/**
 * Sends notifications to Slack via an Incoming Webhook URL.
 *
 * The notifiable must implement routeNotificationForSlack() or have a
 * $slack_webhook_url attribute.
 *
 * Configure the webhook URL in config/services.php:
 *   'slack' => ['webhook_url' => env('SLACK_WEBHOOK_URL')]
 */
class SlackChannel
{
    public function __construct(private ?string $defaultWebhook = null) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toSlack')) {
            throw new \LogicException(get_class($notification) . ' must implement toSlack() to use the Slack channel.');
        }

        /** @var SlackMessage $slackMessage */
        $slackMessage = $notification->toSlack($notifiable);
        $webhookUrl   = $this->resolveWebhook($notifiable);

        if (empty($webhookUrl)) {
            throw new \RuntimeException('No Slack webhook URL configured for ' . get_class($notifiable));
        }

        $payload = json_encode($slackMessage->toPayload());

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);

        @file_get_contents($webhookUrl, false, $context);
    }

    private function resolveWebhook(object $notifiable): string
    {
        if (method_exists($notifiable, 'routeNotificationForSlack')) {
            return $notifiable->routeNotificationForSlack();
        }
        return $notifiable->slack_webhook_url ?? $this->defaultWebhook ?? '';
    }
}
