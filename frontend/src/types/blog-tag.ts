export interface BlogTag {
  id: number;
  store_id: number;
  name: string;
  slug: string;
  posts_count?: number;
  created_at: string;
  updated_at: string;
}
