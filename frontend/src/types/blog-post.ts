import type { BlogCategory } from "@/types/blog-category";
import type { BlogTag } from "@/types/blog-tag";

export type BlogPostStatus = "draft" | "published";

export interface BlogPost {
  id: number;
  uuid: string;
  store_id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  body: string | null;
  featured_image_url: string | null;
  blog_category_id: number | null;
  category: BlogCategory | null;
  tags: BlogTag[];
  meta_title: string | null;
  meta_description: string | null;
  status: BlogPostStatus;
  published_at: string | null;
  author: string | null;
  reading_time_minutes: number;
  created_at: string;
  updated_at: string;
}

export interface BlogPostVersion {
  id: number;
  snapshot: {
    title: string;
    slug: string;
    excerpt: string | null;
    body: string | null;
    featured_image_url: string | null;
    meta_title: string | null;
    meta_description: string | null;
    status: BlogPostStatus;
  };
  created_by: string | null;
  created_at: string;
}
