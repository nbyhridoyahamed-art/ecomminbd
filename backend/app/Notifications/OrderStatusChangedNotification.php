<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the Customer on process/ship/deliver/cancel. Reads $order->status
 * fresh at send time rather than taking a status parameter — by the time
 * this fires (after the transaction that changed it), the order's own
 * status is already the new one.
 */
class OrderStatusChangedNotification extends Notification
{
    private const STATUS_TEXT = [
        'processing' => 'is now being processed',
        'shipped' => 'has been shipped',
        'delivered' => 'has been delivered',
        'cancelled' => 'has been cancelled',
    ];

    public function __construct(private readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return array_filter([
            $notifiable->email ? 'mail' : null,
            SmsChannel::class,
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order Update - {$this->order->order_number}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your order {$this->order->order_number} {$this->statusText()}.");
    }

    public function toSms(object $notifiable): string
    {
        return "Order {$this->order->order_number} {$this->statusText()}.";
    }

    private function statusText(): string
    {
        return self::STATUS_TEXT[$this->order->status] ?? "is now {$this->order->status}";
    }
}
