import type { Seo } from "@/types/seo";

export interface Page {
  id: number;
  uuid: string;
  store_id: number;
  title: string;
  slug: string;
  content: string | null;
  seo: Seo | null;
  status: "draft" | "published";
  created_by?: string | null;
  created_at: string;
  updated_at: string;
}
