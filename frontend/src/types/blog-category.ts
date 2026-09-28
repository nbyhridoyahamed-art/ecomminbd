export interface BlogCategory {
  id: number;
  uuid: string;
  store_id: number;
  name: string;
  slug: string;
  description: string | null;
  posts_count?: number;
  created_at: string;
  updated_at: string;
}
