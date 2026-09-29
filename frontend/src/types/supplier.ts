export type SupplierPaymentTerms = "due_on_receipt" | "net_15" | "net_30" | "net_60";

export interface Supplier {
  id: number;
  uuid: string;
  store_id: number;
  name: string;
  contact_name: string | null;
  email: string | null;
  phone: string | null;
  address: string | null;
  status: "active" | "inactive";
  payment_terms: SupplierPaymentTerms | null;
  purchase_orders_count?: number;
  created_at: string;
  updated_at: string;
}

export type SupplierLedgerEntryType = "receipt" | "payment" | "credit";

export interface SupplierLedgerEntry {
  date: string;
  type: SupplierLedgerEntryType;
  reference: string | null;
  description: string;
  debit_amount: number | null;
  credit_amount: number | null;
  running_balance: number;
}

export interface SupplierLedger {
  currency_code: string;
  balance_amount: number;
  entries: SupplierLedgerEntry[];
}
