<?php

namespace App\Notifications;

use App\Models\OrderReturn;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the order's Customer on approve/reject/receive/refund. Same fresh-status-read reasoning as OrderStatusChangedNotification. */
class ReturnStatusChangedNotification extends Notification
{
    private const STATUS_TEXT = [
        'approved' => 'has been approved',
        'rejected' => 'has been rejected',
        'received' => 'has been received at our warehouse',
        'refunded' => 'has been refunded',
    ];

    public function __construct(private readonly OrderReturn $orderReturn) {}

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
            ->subject("Return Update - {$this->orderReturn->return_number}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your return {$this->orderReturn->return_number} for order {$this->orderReturn->order->order_number} {$this->statusText()}.");
    }

    public function toSms(object $notifiable): string
    {
        return "Return {$this->orderReturn->return_number} {$this->statusText()}.";
    }

    private function statusText(): string
    {
        return self::STATUS_TEXT[$this->orderReturn->status] ?? "is now {$this->orderReturn->status}";
    }
}
