export type PurchaseReturnStatus = "requested" | "approved" | "rejected" | "shipped_back" | "credited";

export interface PurchaseReturnStatusHistoryEntry {
  from_status: PurchaseReturnStatus | null;
  to_status: PurchaseReturnStatus;
  note: string | null;
  created_by: string | null;
  created_at: string;
}

export interface PurchaseReturnItem {
  id: number;
  purchase_order_item_id: number;
  product_name: string;
  sku: string;
  product_variant_sku: string | null;
  quantity: number;
  unit_cost: number;
  line_total: number;
}

export interface PurchaseReturn {
  id: number;
  uuid: string;
  return_number: string;
  status: PurchaseReturnStatus;
  reason: string | null;
  credit_amount: number | null;
  credited_at: string | null;
  note: string | null;
  purchase_order: {
    id: number;
    po_number: string;
    status: string;
    supplier_name: string | null;
  };
  items: PurchaseReturnItem[];
  status_history: PurchaseReturnStatusHistoryEntry[];
  created_by: string | null;
  created_at: string;
}
