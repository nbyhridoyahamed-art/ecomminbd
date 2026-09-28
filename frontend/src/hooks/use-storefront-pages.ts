"use client";

import { useQuery } from "@tanstack/react-query";

import { api } from "@/lib/api";
import type { StorefrontPage } from "@/types/storefront";

export function useStorefrontPages() {
  return useQuery({
    queryKey: ["storefront", "pages"],
    queryFn: () => api.get<StorefrontPage[]>("/storefront/pages"),
  });
}

export function useStorefrontPage(slug: string) {
  return useQuery({
    queryKey: ["storefront", "pages", slug],
    queryFn: () => api.get<StorefrontPage>(`/storefront/pages/${slug}`),
  });
}
