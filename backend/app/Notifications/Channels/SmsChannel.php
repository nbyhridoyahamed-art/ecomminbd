<?php

namespace App\Notifications\Channels;

use App\Contracts\SmsGateway;
use Illuminate\Notifications\Notification;

/**
 * A custom Laravel notification channel — referenced by its FQCN directly
 * from a Notification's via() (Laravel resolves a class-string channel
 * through the container automatically, no Notification::extend() needed).
 * Delegates the actual send to whatever SmsGateway is bound in
 * AppServiceProvider, so this channel itself never changes when the real
 * provider does.
 */
class SmsChannel
{
    public function __construct(private readonly SmsGateway $gateway) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $phone = $notifiable->phone ?? null;

        if (! $phone) {
            return;
        }

        $this->gateway->send($phone, $notification->toSms($notifiable));
    }
}
