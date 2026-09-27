"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { Supplier } from "@/types/supplier";

export interface SupplierFormValues {
  store_id: number;
  name: string;
  contact_name?: string | null;
  email?: string | null;
  phone?: string | null;
  address?: string | null;
  status?: "active" | "inactive";
}

export function useSuppliers(storeId: number | null | undefined, page: number, search: string) {
  return useQuery({
    queryKey: ["suppliers", storeId, { page, search }],
    queryFn: () =>
      api.getWithMeta<Supplier[]>(
        `/suppliers?store_id=${storeId}&page=${page}&per_page=20${search ? `&search=${encodeURIComponent(search)}` : ""}`,
      ),
    enabled: Boolean(storeId),
  });
}

export function useAllSuppliers(storeId: number | null | undefined) {
  return useQuery({
    queryKey: ["suppliers", "all", storeId],
    queryFn: () => api.getWithMeta<Supplier[]>(`/suppliers?store_id=${storeId}&per_page=100`),
    enabled: Boolean(storeId),
  });
}

export function useSupplier(id: number | null | undefined) {
  return useQuery({
    queryKey: ["suppliers", "detail", id],
    queryFn: () => api.get<Supplier>(`/suppliers/${id}`),
    enabled: Boolean(id),
  });
}

export function useCreateSupplier() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: SupplierFormValues) => api.post<Supplier>("/suppliers", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["suppliers"] });
      toast.success("Supplier created.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not create supplier.");
    },
  });
}

export function useUpdateSupplier(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: SupplierFormValues) => api.put<Supplier>(`/suppliers/${id}`, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["suppliers"] });
      toast.success("Supplier updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update supplier.");
    },
  });
}

export function useDeleteSupplier() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/suppliers/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["suppliers"] });
      toast.success("Supplier deleted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete supplier.");
    },
  });
}
