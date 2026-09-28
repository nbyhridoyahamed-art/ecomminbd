import type { Seo } from "@/types/seo";

export interface BlogTag {
  id: number;
  store_id: number;
  name: string;
  slug: string;
  seo: Seo | null;
  posts_count?: number;
  created_at: string;
  updated_at: string;
}
