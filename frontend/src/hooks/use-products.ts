"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { Product, ProductImage } from "@/types/product";

export interface ProductFormValues {
  store_id: number;
  category_id?: number | null;
  brand_id?: number | null;
  name: string;
  slug: string;
  sku: string;
  barcode?: string | null;
  description?: string | null;
  short_description?: string | null;
  price: string;
  sale_price?: string | null;
  cost_price?: string | null;
  compare_at_price?: string | null;
  weight?: string | null;
  weight_unit?: string | null;
  track_stock?: boolean;
  low_stock_threshold?: number | null;
  status?: "draft" | "active" | "archived";
  featured?: boolean;
  seo_title?: string | null;
  seo_description?: string | null;
  focus_keyword?: string | null;
}

export interface ProductFilters {
  page: number;
  search?: string;
  categoryId?: number | null;
  brandId?: number | null;
  status?: string | null;
}

export function useProducts(storeId: number | null | undefined, filters: ProductFilters) {
  return useQuery({
    queryKey: ["products", storeId, filters],
    queryFn: () => {
      const params = new URLSearchParams({
        store_id: String(storeId),
        page: String(filters.page),
        per_page: "20",
      });
      if (filters.search) params.set("search", filters.search);
      if (filters.categoryId) params.set("category_id", String(filters.categoryId));
      if (filters.brandId) params.set("brand_id", String(filters.brandId));
      if (filters.status) params.set("status", filters.status);

      return api.getWithMeta<Product[]>(`/products?${params.toString()}`);
    },
    enabled: Boolean(storeId),
  });
}

export function useAllProducts(storeId: number | null | undefined) {
  return useQuery({
    queryKey: ["products", "all", storeId],
    queryFn: () => api.getWithMeta<Product[]>(`/products?store_id=${storeId}&per_page=100`),
    enabled: Boolean(storeId),
  });
}

export function useProduct(id: number | null | undefined) {
  return useQuery({
    queryKey: ["products", "detail", id],
    queryFn: () => api.get<Product>(`/products/${id}`),
    enabled: Boolean(id),
  });
}

export function useCreateProduct() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: ProductFormValues) => api.post<Product>("/products", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["products"] });
      toast.success("Product created.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not create product.");
    },
  });
}

export function useUpdateProduct(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: ProductFormValues) => api.put<Product>(`/products/${id}`, payload),
    onSuccess: (product) => {
      queryClient.invalidateQueries({ queryKey: ["products"] });
      queryClient.setQueryData(["products", "detail", id], product);
      toast.success("Product updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update product.");
    },
  });
}

export function useDeleteProduct() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/products/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["products"] });
      toast.success("Product deleted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete product.");
    },
  });
}

export function useUploadProductImage(productId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (file: File) => {
      const formData = new FormData();
      formData.append("image", file);
      return api.post<ProductImage>(`/products/${productId}/images`, formData);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["products", "detail", productId] });
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not upload image.");
    },
  });
}

export function useDeleteProductImage(productId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (imageId: number) => api.delete<null>(`/products/${productId}/images/${imageId}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["products", "detail", productId] });
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete image.");
    },
  });
}

export function useMarkPrimaryProductImage(productId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (imageId: number) => api.post<ProductImage>(`/products/${productId}/images/${imageId}/primary`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["products", "detail", productId] });
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update primary image.");
    },
  });
}
