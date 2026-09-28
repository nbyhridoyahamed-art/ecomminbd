export interface OrderPlacedNotificationData {
  type: "order.placed";
  order_id: number;
  order_uuid: string;
  order_number: string;
  customer_name: string;
}

export interface AppNotification {
  id: string;
  data: OrderPlacedNotificationData;
  read_at: string | null;
  created_at: string;
}
