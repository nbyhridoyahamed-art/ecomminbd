"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { BundleComponent } from "@/types/product";

export interface ComponentFormValues {
  product_id: number;
  product_variant_id?: number | null;
  quantity: number;
}

function invalidateProduct(queryClient: ReturnType<typeof useQueryClient>, productId: number) {
  queryClient.invalidateQueries({ queryKey: ["products"] });
  queryClient.invalidateQueries({ queryKey: ["products", "detail", productId] });
}

export function useAddComponent(productId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: ComponentFormValues) => api.post<BundleComponent>(`/products/${productId}/components`, payload),
    onSuccess: () => {
      invalidateProduct(queryClient, productId);
      toast.success("Component added.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not add component.");
    },
  });
}

export function useUpdateComponent(productId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ componentId, quantity }: { componentId: number; quantity: number }) =>
      api.put<BundleComponent>(`/products/${productId}/components/${componentId}`, { quantity }),
    onSuccess: () => {
      invalidateProduct(queryClient, productId);
      toast.success("Component updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update component.");
    },
  });
}

export function useDeleteComponent(productId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (componentId: number) => api.delete<null>(`/products/${productId}/components/${componentId}`),
    onSuccess: () => {
      invalidateProduct(queryClient, productId);
      toast.success("Component removed.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not remove component.");
    },
  });
}
