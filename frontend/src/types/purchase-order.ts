import type { ProductVariantSnapshot } from "@/types/product";

export type PurchaseOrderStatus = "draft" | "pending_approval" | "ordered" | "partially_received" | "received" | "cancelled";

export interface PurchaseOrderStatusHistoryEntry {
  id: number;
  from_status: PurchaseOrderStatus | null;
  to_status: PurchaseOrderStatus;
  note: string | null;
  created_by: string | null;
  created_at: string;
}

export interface PurchaseOrderPayment {
  id: number;
  amount: number;
  currency_code: string;
  method: string;
  reference: string | null;
  created_by: string | null;
  created_at: string;
}

export interface PurchaseOrderItem {
  id: number;
  product_id: number;
  product_name: string;
  sku: string;
  product_variant: ProductVariantSnapshot | null;
  quantity_ordered: number;
  quantity_received: number;
  quantity_remaining: number;
  unit_cost: number;
}

export interface PurchaseReceiptItem {
  product_name: string;
  sku: string;
  product_variant_sku: string | null;
  quantity_received: number;
}

export interface PurchaseReceipt {
  id: number;
  uuid: string;
  purchase_order_id?: number;
  receipt_number: string;
  note: string | null;
  received_by: string | null;
  items: PurchaseReceiptItem[];
  created_at: string;
}

export interface PurchaseOrder {
  id: number;
  uuid: string;
  po_number: string;
  status: PurchaseOrderStatus;
  currency_code: string;
  notes: string | null;
  warehouse: { id: number; name: string };
  supplier: { id: number; name: string };
  items: PurchaseOrderItem[];
  total_amount: number;
  receipts: PurchaseReceipt[];
  returns: { id: number; return_number: string; status: string; credit_amount: number | null }[];
  status_history: PurchaseOrderStatusHistoryEntry[];
  payments: PurchaseOrderPayment[];
  created_by: string | null;
  created_at: string;
}
