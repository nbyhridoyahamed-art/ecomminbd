import type { Seo } from "@/types/seo";

export interface Brand {
  id: number;
  uuid: string;
  store_id: number;
  name: string;
  slug: string;
  description: string | null;
  logo_path: string | null;
  logo_url: string | null;
  status: "active" | "inactive";
  seo: Seo | null;
  products_count?: number;
  created_at: string;
  updated_at: string;
}
