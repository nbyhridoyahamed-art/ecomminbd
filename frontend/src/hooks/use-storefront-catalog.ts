"use client";

import { useQuery } from "@tanstack/react-query";

import { api } from "@/lib/api";
import type {
  BdDistrict,
  BdLocation,
  BdUpazila,
  StorefrontBrand,
  StorefrontCategory,
  StorefrontProduct,
  StorefrontProductDetail,
  StorefrontStore,
} from "@/types/storefront";

export function useStorefrontStore() {
  return useQuery({
    queryKey: ["storefront", "store"],
    queryFn: () => api.get<StorefrontStore>("/storefront/store"),
    staleTime: Infinity,
  });
}

export interface StorefrontProductFilters {
  page?: number;
  search?: string;
  category?: string;
  brand?: string;
  featured?: boolean;
  sort?: "price_asc" | "price_desc" | "newest";
}

function buildProductFilterParams(filters: StorefrontProductFilters) {
  const params = new URLSearchParams();
  if (filters.search) params.set("search", filters.search);
  if (filters.category) params.set("category", filters.category);
  if (filters.brand) params.set("brand", filters.brand);
  if (filters.featured) params.set("featured", "1");
  if (filters.sort) params.set("sort", filters.sort);
  params.set("page", String(filters.page ?? 1));
  params.set("per_page", "20");
  return params;
}

export function useStorefrontProducts(filters: StorefrontProductFilters) {
  return useQuery({
    queryKey: ["storefront", "products", filters],
    queryFn: () =>
      api.getWithMeta<StorefrontProduct[]>(`/storefront/products?${buildProductFilterParams(filters).toString()}`),
  });
}

export function useStorefrontProduct(slug: string | null | undefined) {
  return useQuery({
    queryKey: ["storefront", "products", "detail", slug],
    queryFn: () => api.get<StorefrontProductDetail>(`/storefront/products/${slug}`),
    enabled: Boolean(slug),
  });
}

export function useStorefrontCategories() {
  return useQuery({
    queryKey: ["storefront", "categories"],
    queryFn: () => api.get<StorefrontCategory[]>("/storefront/categories"),
  });
}

export function useStorefrontCategory(slug: string | null | undefined, page = 1) {
  return useQuery({
    queryKey: ["storefront", "categories", slug, page],
    queryFn: () =>
      api.getWithMeta<{ category: StorefrontCategory; products: StorefrontProduct[] }>(
        `/storefront/categories/${slug}?page=${page}&per_page=20`,
      ),
    enabled: Boolean(slug),
  });
}

export function useStorefrontBrands() {
  return useQuery({
    queryKey: ["storefront", "brands"],
    queryFn: () => api.get<StorefrontBrand[]>("/storefront/brands"),
  });
}

export function useStorefrontBrand(slug: string | null | undefined, page = 1) {
  return useQuery({
    queryKey: ["storefront", "brands", slug, page],
    queryFn: () =>
      api.getWithMeta<{ brand: StorefrontBrand; products: StorefrontProduct[] }>(
        `/storefront/brands/${slug}?page=${page}&per_page=20`,
      ),
    enabled: Boolean(slug),
  });
}

export function useStorefrontDivisions() {
  return useQuery({
    queryKey: ["storefront", "locations", "divisions"],
    queryFn: () => api.get<BdLocation[]>("/storefront/locations/divisions"),
  });
}

export function useStorefrontDistricts(divisionId: number | null | undefined) {
  return useQuery({
    queryKey: ["storefront", "locations", "districts", divisionId],
    queryFn: () => api.get<BdDistrict[]>(`/storefront/locations/districts?division_id=${divisionId}`),
    enabled: Boolean(divisionId),
  });
}

export function useStorefrontUpazilas(districtId: number | null | undefined) {
  return useQuery({
    queryKey: ["storefront", "locations", "upazilas", districtId],
    queryFn: () => api.get<BdUpazila[]>(`/storefront/locations/upazilas?district_id=${districtId}`),
    enabled: Boolean(districtId),
  });
}
