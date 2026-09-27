export type ReturnStatus = "requested" | "approved" | "rejected" | "received" | "refunded";

export interface ReturnStatusHistoryEntry {
  from_status: ReturnStatus | null;
  to_status: ReturnStatus;
  note: string | null;
  created_by: string | null;
  created_at: string;
}

export interface ReturnItem {
  id: number;
  order_item_id: number;
  product_name: string;
  sku: string;
  quantity: number;
  unit_price: number;
  line_total: number;
  restock: boolean;
}

export interface OrderReturn {
  id: number;
  uuid: string;
  return_number: string;
  status: ReturnStatus;
  reason: string | null;
  refund_amount: number | null;
  refunded_at: string | null;
  note: string | null;
  order: {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    customer_name: string | null;
  };
  items: ReturnItem[];
  status_history: ReturnStatusHistoryEntry[];
  created_by: string | null;
  created_at: string;
}
