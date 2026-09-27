export interface CustomerAddress {
  id: number;
  customer_id: number;
  label: string | null;
  recipient_name: string;
  phone: string;
  address_line: string;
  bd_division_id: number | null;
  bd_district_id: number | null;
  bd_upazila_id: number | null;
  division?: { id: number; name_en: string; name_bn: string } | null;
  district?: { id: number; name_en: string; name_bn: string } | null;
  upazila?: { id: number; name_en: string; name_bn: string } | null;
  is_default: boolean;
}

export interface Customer {
  id: number;
  uuid: string;
  store_id: number;
  name: string;
  email: string | null;
  phone: string;
  status: "active" | "inactive";
  orders_count?: number;
  addresses?: CustomerAddress[];
  created_at: string;
  updated_at: string;
}
