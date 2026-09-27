export interface Store {
  id: number;
  uuid: string;
  organization_id: number;
  name: string;
  slug: string;
  domain: string | null;
  currency: { id: number; code: string; symbol: string } | null;
  default_timezone: string;
  default_locale: string;
  status: "active" | "inactive";
  created_at: string;
  updated_at: string;
}
