/**
 * Deliberately minimal — exists only to back the homepage builder's "Blog
 * Posts" block. NOT Phase 14 (the real Blog CMS); see
 * DEVELOPMENT_ROADMAP.md's Phase 13 scope note.
 */
export interface BlogPost {
  id: number;
  uuid: string;
  store_id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  featured_image_url: string | null;
  published_at: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}
