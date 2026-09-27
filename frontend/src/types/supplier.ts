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
  purchase_orders_count?: number;
  created_at: string;
  updated_at: string;
}
