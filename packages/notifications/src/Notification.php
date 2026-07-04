<?php

namespace Kyqo\Notifications;

/**
 * Base Notification class.
 *
 * Extend and implement via() + toMail() / toDatabase() / toSlack().
 *
 * Usage:
 *   class InvoicePaid extends Notification
 *   {
 *       public function __construct(private Invoice $invoice) {}
 *
 *       public function via(object $notifiable): array
 *       {
 *           return ['mail', 'database'];
 *       }
 *
 *       public function toMail(object $notifiable): MailMessage
 *       {
 *           return (new MailMessage)
 *               ->subject('Invoice Paid')
 *               ->line('Your invoice #' . $this->invoice->id . ' has been paid.');
 *       }
 *
 *       public function toDatabase(object $notifiable): array
 *       {
 *           return ['invoice_id' => $this->invoice->id, 'amount' => $this->invoice->total];
 *       }
 *   }
 *
 *   $user->notify(new InvoicePaid($invoice));
 */
abstract class Notification
{
    /** Unique ID assigned when dispatched. */
    public string $id;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(16));
    }

    /**
     * Return the channels this notification uses.
     * Supported: 'mail', 'database', 'slack'
     */
    abstract public function via(object $notifiable): array;
}
