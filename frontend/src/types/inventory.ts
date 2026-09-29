import type { ProductVariantSnapshot } from "@/types/product";

export interface StockLevel {
  product_id: number;
  product_name: string;
  sku: string;
  quantity: number;
  quantity_reserved: number;
  quantity_available: number;
  track_stock: boolean;
  low_stock_threshold: number | null;
  is_low_stock: boolean;
}

export type StockMovementType =
  | "adjustment_increase"
  | "adjustment_decrease"
  | "transfer_in"
  | "transfer_out"
  | "purchase_receipt"
  | "sale"
  | "return";

export interface StockMovement {
  id: number;
  uuid: string;
  product: { id: number; name: string; sku: string };
  product_variant: ProductVariantSnapshot | null;
  warehouse: { id: number; name: string };
  type: StockMovementType;
  quantity: number;
  quantity_before: number;
  quantity_after: number;
  reason: string | null;
  reference_type: string | null;
  reference_id: number | null;
  created_by: string | null;
  created_at: string;
}

export interface StockTransferItem {
  product_id: number;
  product_name: string;
  sku: string;
  product_variant: ProductVariantSnapshot | null;
  quantity: number;
}

export type StockTransferStatus = "pending" | "in_transit" | "received" | "cancelled";

export interface StockTransferStatusHistoryEntry {
  id: number;
  from_status: StockTransferStatus | null;
  to_status: StockTransferStatus;
  note: string | null;
  created_by: string | null;
  created_at: string;
}

export interface StockTransfer {
  id: number;
  uuid: string;
  transfer_number: string;
  from_warehouse: { id: number; name: string };
  to_warehouse: { id: number; name: string };
  status: StockTransferStatus;
  note: string | null;
  status_history: StockTransferStatusHistoryEntry[];
  movements: StockMovement[];
  items: StockTransferItem[];
  created_by: string | null;
  created_at: string;
}

export interface StockAdjustmentSession {
  id: number;
  uuid: string;
  reference: string;
  warehouse: { id: number; name: string };
  note: string | null;
  movements: StockMovement[];
  created_by: string | null;
  created_at: string;
}
