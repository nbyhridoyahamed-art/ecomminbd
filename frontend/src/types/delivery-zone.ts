export interface DeliveryZoneRate {
  id: number;
  min_order_subtotal: number;
  rate_amount: number;
  currency_code: string;
}

export interface DeliveryZone {
  id: number;
  name: string;
  bd_division_id: number | null;
  bd_district_id: number | null;
  division_name: string | null;
  district_name: string | null;
  status: "active" | "inactive";
  rates: DeliveryZoneRate[];
  created_at: string;
}
