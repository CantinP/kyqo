<?php

namespace Kyqo\Notifications\Channels;

use Kyqo\Mail\MailManager;
use Kyqo\Mail\Message;
use Kyqo\Notifications\Notification;
use Kyqo\Notifications\Messages\MailMessage;

class MailChannel
{
    public function __construct(private MailManager $mailer) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toMail')) {
            throw new \LogicException(get_class($notification) . ' must implement toMail() to use the mail channel.');
        }

        /** @var MailMessage $mailMessage */
        $mailMessage = $notification->toMail($notifiable);

        $toAddress = $this->getRecipientEmail($notifiable);

        $this->mailer->send(function (Message $msg) use ($mailMessage, $toAddress) {
            $msg->to($toAddress);

            if ($mailMessage->subject) {
                $msg->subject($mailMessage->subject);
            }

            if ($mailMessage->from) {
                $msg->from($mailMessage->from, $mailMessage->fromName ?? '');
            }

            $msg->html($mailMessage->toHtml());
        });
    }

    private function getRecipientEmail(object $notifiable): string
    {
        if (method_exists($notifiable, 'routeNotificationForMail')) {
            return $notifiable->routeNotificationForMail();
        }
        return $notifiable->email ?? $notifiable->attributes['email'] ?? '';
    }
}
