"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { Warehouse } from "@/types/warehouse";

export interface WarehouseFormValues {
  store_id: number;
  name: string;
  code: string;
  type: "main" | "branch" | "pickup_point" | "temporary";
  manager_name?: string | null;
  phone?: string | null;
  address_line?: string | null;
  status?: "active" | "inactive";
}

export function useWarehouses(storeId: number | null | undefined, page: number, search: string) {
  return useQuery({
    queryKey: ["warehouses", storeId, { page, search }],
    queryFn: () =>
      api.getWithMeta<Warehouse[]>(
        `/warehouses?store_id=${storeId}&page=${page}&per_page=20${search ? `&search=${encodeURIComponent(search)}` : ""}`,
      ),
    enabled: Boolean(storeId),
  });
}

export function useAllWarehouses(storeId: number | null | undefined) {
  return useQuery({
    queryKey: ["warehouses", "all", storeId],
    queryFn: () => api.getWithMeta<Warehouse[]>(`/warehouses?store_id=${storeId}&per_page=100`),
    enabled: Boolean(storeId),
  });
}

export function useWarehouse(id: number | null | undefined) {
  return useQuery({
    queryKey: ["warehouses", "detail", id],
    queryFn: () => api.get<Warehouse>(`/warehouses/${id}`),
    enabled: Boolean(id),
  });
}

export function useCreateWarehouse() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: WarehouseFormValues) => api.post<Warehouse>("/warehouses", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["warehouses"] });
      toast.success("Warehouse created.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not create warehouse.");
    },
  });
}

export function useUpdateWarehouse(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: WarehouseFormValues) => api.put<Warehouse>(`/warehouses/${id}`, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["warehouses"] });
      toast.success("Warehouse updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update warehouse.");
    },
  });
}

export function useDeleteWarehouse() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/warehouses/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["warehouses"] });
      toast.success("Warehouse deleted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete warehouse.");
    },
  });
}
