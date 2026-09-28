"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { ProductAttribute, ProductAttributeValue } from "@/types/attribute";

export interface AttributeFormValues {
  store_id: number;
  name: string;
  slug: string;
}

export interface AttributeValueFormValues {
  value: string;
  slug: string;
  sort_order?: number;
}

export function useAttributes(storeId: number | null | undefined) {
  return useQuery({
    queryKey: ["attributes", storeId],
    queryFn: () => api.get<ProductAttribute[]>(`/product-attributes?store_id=${storeId}`),
    enabled: Boolean(storeId),
  });
}

export function useAttribute(id: number | null | undefined) {
  return useQuery({
    queryKey: ["attributes", "detail", id],
    queryFn: () => api.get<ProductAttribute>(`/product-attributes/${id}`),
    enabled: Boolean(id),
  });
}

function invalidateAttributes(queryClient: ReturnType<typeof useQueryClient>, id?: number) {
  queryClient.invalidateQueries({ queryKey: ["attributes"] });
  if (id) {
    queryClient.invalidateQueries({ queryKey: ["attributes", "detail", id] });
  }
}

export function useCreateAttribute() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: AttributeFormValues) => api.post<ProductAttribute>("/product-attributes", payload),
    onSuccess: () => {
      invalidateAttributes(queryClient);
      toast.success("Attribute created.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not create attribute.");
    },
  });
}

export function useUpdateAttribute(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: AttributeFormValues) => api.put<ProductAttribute>(`/product-attributes/${id}`, payload),
    onSuccess: () => {
      invalidateAttributes(queryClient, id);
      toast.success("Attribute updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update attribute.");
    },
  });
}

export function useDeleteAttribute() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/product-attributes/${id}`),
    onSuccess: () => {
      invalidateAttributes(queryClient);
      toast.success("Attribute deleted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete attribute.");
    },
  });
}

export function useCreateAttributeValue(attributeId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: AttributeValueFormValues) =>
      api.post<ProductAttributeValue>(`/product-attributes/${attributeId}/values`, payload),
    onSuccess: () => {
      invalidateAttributes(queryClient, attributeId);
      toast.success("Value added.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not add value.");
    },
  });
}

export function useUpdateAttributeValue(attributeId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ valueId, ...payload }: AttributeValueFormValues & { valueId: number }) =>
      api.put<ProductAttributeValue>(`/product-attributes/${attributeId}/values/${valueId}`, payload),
    onSuccess: () => {
      invalidateAttributes(queryClient, attributeId);
      toast.success("Value updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update value.");
    },
  });
}

export function useDeleteAttributeValue(attributeId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (valueId: number) => api.delete<null>(`/product-attributes/${attributeId}/values/${valueId}`),
    onSuccess: () => {
      invalidateAttributes(queryClient, attributeId);
      toast.success("Value deleted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete value.");
    },
  });
}
