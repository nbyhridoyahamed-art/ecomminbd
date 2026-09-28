<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the Customer who placed an order — admin- or storefront-sourced, either way. */
class OrderPlacedNotification extends Notification
{
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
            ->subject("Order Confirmation - {$this->order->order_number}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Thank you! Your order {$this->order->order_number} has been placed successfully.")
            ->line('We will contact you shortly to confirm delivery.');
    }

    public function toSms(object $notifiable): string
    {
        return "Thank you {$notifiable->name}! Your order {$this->order->order_number} has been placed. We'll contact you soon to confirm delivery.";
    }
}
