import type { Customer } from "@/types/customer";
import type { StorefrontAttributeValue } from "@/types/storefront";

export interface AccountAuthResult {
  customer: Customer;
  token: string;
}

export interface RegisterPayload {
  name: string;
  phone: string;
  email?: string;
  password: string;
  password_confirmation: string;
}

export interface LoginPayload {
  phone: string;
  password: string;
}

export interface ProfilePayload {
  name: string;
  email?: string;
}

export interface AddressPayload {
  label?: string | null;
  recipient_name: string;
  phone: string;
  address_line: string;
  bd_division_id?: number | null;
  bd_district_id?: number | null;
  bd_upazila_id?: number | null;
  is_default?: boolean;
}

export interface AccountOrderItem {
  product_name: string;
  product_slug: string;
  product_variant: { sku: string; attribute_values: StorefrontAttributeValue[] } | null;
  quantity: number;
  unit_price: number;
  line_total: number;
}

export interface AccountOrderStatusHistoryEntry {
  from_status: string | null;
  to_status: string;
  created_at: string;
}

export interface AccountOrder {
  uuid: string;
  order_number: string;
  status: "pending" | "processing" | "shipped" | "delivered" | "cancelled";
  payment_method: string;
  payment_status: string;
  source: "admin" | "storefront";
  currency_code: string;
  notes: string | null;
  shipping: {
    recipient_name: string;
    phone: string;
    address_line: string;
    division: string | null;
    district: string | null;
    upazila: string | null;
  };
  items: AccountOrderItem[];
  shipping_amount: number;
  discount_amount: number;
  subtotal_amount: number;
  total_amount: number;
  status_history: AccountOrderStatusHistoryEntry[];
  created_at: string;
}
