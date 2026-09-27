export interface Warehouse {
  id: number;
  uuid: string;
  store_id: number;
  name: string;
  code: string;
  type: "main" | "branch" | "pickup_point" | "temporary";
  manager_name: string | null;
  phone: string | null;
  address_line: string | null;
  status: "active" | "inactive";
  created_at: string;
  updated_at: string;
}
