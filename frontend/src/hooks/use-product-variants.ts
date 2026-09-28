"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { ProductVariant } from "@/types/product";

export interface VariantFormValues {
  sku: string;
  barcode?: string | null;
  price?: string | null;
  sale_price?: string | null;
  cost_price?: string | null;
  status?: "active" | "inactive";
}

function invalidateProduct(queryClient: ReturnType<typeof useQueryClient>, productId: number) {
  queryClient.invalidateQueries({ queryKey: ["products"] });
  queryClient.invalidateQueries({ queryKey: ["products", "detail", productId] });
}

export function useGenerateVariants(productId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (attributeValueIds: number[]) =>
      api.post<ProductVariant[]>(`/products/${productId}/variants/generate`, { attribute_value_ids: attributeValueIds }),
    onSuccess: () => {
      invalidateProduct(queryClient, productId);
      toast.success("Variants generated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not generate variants.");
    },
  });
}

export function useUpdateVariant(productId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ variantId, ...payload }: VariantFormValues & { variantId: number }) =>
      api.put<ProductVariant>(`/products/${productId}/variants/${variantId}`, payload),
    onSuccess: () => {
      invalidateProduct(queryClient, productId);
      toast.success("Variant updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update variant.");
    },
  });
}

export function useDeleteVariant(productId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (variantId: number) => api.delete<null>(`/products/${productId}/variants/${variantId}`),
    onSuccess: () => {
      invalidateProduct(queryClient, productId);
      toast.success("Variant deleted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete variant.");
    },
  });
}
