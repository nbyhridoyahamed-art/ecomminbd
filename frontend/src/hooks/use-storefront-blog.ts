"use client";

import { useQuery } from "@tanstack/react-query";

import { api } from "@/lib/api";
import type {
  StorefrontBlogCategory,
  StorefrontBlogPostDetail,
  StorefrontBlogPostSummary,
  StorefrontBlogTag,
} from "@/types/storefront";

export function useStorefrontBlogPosts(page = 1, search?: string) {
  return useQuery({
    queryKey: ["storefront", "blog", page, search],
    queryFn: () => {
      const params = new URLSearchParams({ page: String(page), per_page: "10" });
      if (search) params.set("search", search);

      return api.getWithMeta<StorefrontBlogPostSummary[]>(`/storefront/blog?${params.toString()}`);
    },
  });
}

export function useStorefrontBlogPost(slug: string | null | undefined) {
  return useQuery({
    queryKey: ["storefront", "blog", "detail", slug],
    queryFn: () =>
      api.get<{ post: StorefrontBlogPostDetail; related_posts: StorefrontBlogPostSummary[] }>(
        `/storefront/blog/${slug}`,
      ),
    enabled: Boolean(slug),
  });
}

export function useStorefrontBlogCategory(slug: string | null | undefined, page = 1) {
  return useQuery({
    queryKey: ["storefront", "blog", "category", slug, page],
    queryFn: () =>
      api.getWithMeta<{ category: StorefrontBlogCategory; posts: StorefrontBlogPostSummary[] }>(
        `/storefront/blog/category/${slug}?page=${page}&per_page=10`,
      ),
    enabled: Boolean(slug),
  });
}

export function useStorefrontBlogTag(slug: string | null | undefined, page = 1) {
  return useQuery({
    queryKey: ["storefront", "blog", "tag", slug, page],
    queryFn: () =>
      api.getWithMeta<{ tag: StorefrontBlogTag; posts: StorefrontBlogPostSummary[] }>(
        `/storefront/blog/tag/${slug}?page=${page}&per_page=10`,
      ),
    enabled: Boolean(slug),
  });
}
