<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Customer email for an order milestone. Queued after the surrounding transaction commits,
 * so a rolled-back change never sends mail. Members without an email address are skipped by the mail channel.
 * shortcut: mail uses the app default locale; store a per-member locale if multilingual mail matters.
 */
class OrderStatusMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public string $event)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = ['number' => $this->order->number];

        return (new MailMessage)
            ->subject(__("ui.mail_order_{$this->event}_subject", $replace))
            ->greeting(__('passwords.mail_greeting'))
            ->line(__("ui.mail_order_{$this->event}_line", $replace))
            ->line(__('ui.mail_order_total', ['total' => number_format($this->order->total)]))
            ->action(__('ui.mail_order_view'), route('orders.show', $this->order))
            ->salutation(__('passwords.mail_salutation', ['name' => config('app.name')]));
    }
}
