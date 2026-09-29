export type ReturnStatus = "requested" | "approved" | "rejected" | "received" | "refunded";

export interface ReturnStatusHistoryEntry {
  from_status: ReturnStatus | null;
  to_status: ReturnStatus;
  note: string | null;
  created_by: string | null;
  created_at: string;
}

export type RefundMethod = "original_payment" | "store_credit";

export interface ReturnItem {
  id: number;
  order_item_id: number;
  product_name: string;
  sku: string;
  product_variant_sku: string | null;
  quantity: number;
  unit_price: number;
  line_total: number;
  restock: boolean;
  /** What the customer wants instead — set at request time, acted on (stock moved via a replacement order) at receive() time. */
  exchange_product_id: number | null;
  exchange_product_name: string | null;
  exchange_product_variant_sku: string | null;
}

export interface OrderReturn {
  id: number;
  uuid: string;
  return_number: string;
  status: ReturnStatus;
  reason: string | null;
  refund_amount: number | null;
  refund_method: RefundMethod;
  refunded_at: string | null;
  note: string | null;
  order: {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    customer_name: string | null;
  };
  /** The zero-value order receive() created to ship this return's exchange item(s), if any. */
  replacement_order: { id: number; order_number: string } | null;
  items: ReturnItem[];
  status_history: ReturnStatusHistoryEntry[];
  created_by: string | null;
  created_at: string;
}
