import type { MetadataRoute } from "next";

import { storefrontApi } from "@/lib/storefront-api";
import type { PaginationMeta } from "@/types/api";
import type { StorefrontBrand, StorefrontCategory, StorefrontPage, StorefrontProduct } from "@/types/storefront";

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000";

// The catalog changes far more often than a deploy — regenerate per-request
// rather than caching a stale sitemap from build time (see Next's
// "caching-without-cache-components" guide's `dynamic` route config).
export const dynamic = "force-dynamic";

// Bounds worst-case pagination looping well above what this app's catalog
// needs today, without looping forever if a backend response is malformed.
const MAX_PAGES = 25;

interface SitemapBlogPost {
  slug: string;
  published_at: string | null;
  category: { slug: string } | null;
  tags: { slug: string }[];
}

async function fetchAllPages<T>(path: string, perPage: number): Promise<T[]> {
  const items: T[] = [];
  let page = 1;

  while (page <= MAX_PAGES) {
    const separator = path.includes("?") ? "&" : "?";
    const { data, meta } = await storefrontApi.getWithMeta<T[]>(`${path}${separator}page=${page}&per_page=${perPage}`);
    items.push(...data);

    const pagination = meta as PaginationMeta | undefined;
    if (!pagination || page >= pagination.last_page) break;
    page++;
  }

  return items;
}

function flattenCategories(categories: StorefrontCategory[]): StorefrontCategory[] {
  return categories.flatMap((category) => [category, ...flattenCategories(category.children ?? [])]);
}

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const [products, categoryTree, brands, pages, posts] = await Promise.all([
    fetchAllPages<StorefrontProduct>("/storefront/products", 60).catch(() => []),
    storefrontApi.get<StorefrontCategory[]>("/storefront/categories").catch(() => []),
    storefrontApi.get<StorefrontBrand[]>("/storefront/brands").catch(() => []),
    storefrontApi.get<StorefrontPage[]>("/storefront/pages").catch(() => []),
    fetchAllPages<SitemapBlogPost>("/storefront/blog", 60).catch(() => []),
  ]);

  const categories = flattenCategories(categoryTree);
  const blogCategorySlugs = new Set(
    posts.map((post) => post.category?.slug).filter((slug): slug is string => Boolean(slug)),
  );
  const blogTagSlugs = new Set(posts.flatMap((post) => post.tags.map((tag) => tag.slug)));

  return [
    { url: SITE_URL, changeFrequency: "daily", priority: 1 },
    { url: `${SITE_URL}/products`, changeFrequency: "daily", priority: 0.8 },
    { url: `${SITE_URL}/brands`, changeFrequency: "weekly", priority: 0.5 },
    { url: `${SITE_URL}/blog`, changeFrequency: "daily", priority: 0.6 },
    ...products.map((product) => ({
      url: `${SITE_URL}/products/${product.slug}`,
      changeFrequency: "weekly" as const,
      priority: 0.7,
    })),
    ...categories.map((category) => ({
      url: `${SITE_URL}/category/${category.slug}`,
      changeFrequency: "weekly" as const,
      priority: 0.6,
    })),
    ...brands.map((brand) => ({
      url: `${SITE_URL}/brand/${brand.slug}`,
      changeFrequency: "weekly" as const,
      priority: 0.5,
    })),
    ...pages.map((page) => ({
      url: `${SITE_URL}/pages/${page.slug}`,
      changeFrequency: "monthly" as const,
      priority: 0.4,
    })),
    ...posts.map((post) => ({
      url: `${SITE_URL}/blog/${post.slug}`,
      lastModified: post.published_at ?? undefined,
      changeFrequency: "monthly" as const,
      priority: 0.5,
    })),
    ...[...blogCategorySlugs].map((slug) => ({
      url: `${SITE_URL}/blog/category/${slug}`,
      changeFrequency: "weekly" as const,
      priority: 0.4,
    })),
    ...[...blogTagSlugs].map((slug) => ({
      url: `${SITE_URL}/blog/tag/${slug}`,
      changeFrequency: "weekly" as const,
      priority: 0.3,
    })),
  ];
}
