<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

/**
 * Sent to staff (database channel only — powers the admin topbar bell) for a
 * storefront-sourced order only. An admin-created order was just entered by
 * a staff member themselves, so notifying staff about it would be pure
 * noise; a storefront order is the one case staff wouldn't otherwise know
 * about until they refresh the Orders list.
 */
class NewOrderPlacedNotification extends Notification
{
    public function __construct(private readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'order.placed',
            'order_id' => $this->order->id,
            'order_uuid' => $this->order->uuid,
            'order_number' => $this->order->order_number,
            'customer_name' => $this->order->customer->name,
        ];
    }
}
