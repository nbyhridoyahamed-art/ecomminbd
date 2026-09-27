export type OrderStatus = "pending" | "processing" | "shipped" | "delivered" | "cancelled";

export type PaymentMethod = "cod" | "bkash" | "nagad" | "rocket" | "card" | "bank_transfer";

export interface OrderItem {
  id: number;
  product_id: number;
  product_name: string;
  sku: string;
  quantity: number;
  unit_price: number;
  line_total: number;
}

export interface OrderStatusHistoryEntry {
  from_status: OrderStatus | null;
  to_status: OrderStatus;
  note: string | null;
  created_by: string | null;
  created_at: string;
}

export interface Order {
  id: number;
  uuid: string;
  order_number: string;
  status: OrderStatus;
  payment_method: PaymentMethod;
  payment_status: "unpaid" | "paid" | "refunded";
  currency_code: string;
  notes: string | null;
  customer: { id: number; name: string; phone: string };
  warehouse: { id: number; name: string };
  shipping: {
    customer_address_id: number | null;
    recipient_name: string;
    phone: string;
    address_line: string;
    division: string | null;
    district: string | null;
    upazila: string | null;
  };
  shipping_amount: number;
  discount_amount: number;
  items: OrderItem[];
  subtotal_amount: number;
  total_amount: number;
  status_history: OrderStatusHistoryEntry[];
  shipment: { id: number; tracking_number: string; status: string; courier_name: string | null } | null;
  returns: { id: number; return_number: string; status: string; refund_amount: number | null }[];
  created_by: string | null;
  created_at: string;
}
