"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { StockLevel, StockMovement, StockTransfer } from "@/types/inventory";

export interface StockLevelFilters {
  page: number;
  search: string;
  lowStockOnly: boolean;
}

export function useStockLevels(
  storeId: number | null | undefined,
  warehouseId: number | null | undefined,
  filters: StockLevelFilters,
) {
  return useQuery({
    queryKey: ["stock-levels", storeId, warehouseId, filters],
    queryFn: () =>
      api.getWithMeta<StockLevel[]>(
        `/stock-levels?store_id=${storeId}&warehouse_id=${warehouseId}&page=${filters.page}&per_page=20` +
          (filters.search ? `&search=${encodeURIComponent(filters.search)}` : "") +
          (filters.lowStockOnly ? "&low_stock=1" : ""),
      ),
    enabled: Boolean(storeId) && Boolean(warehouseId),
  });
}

export interface StockMovementFilters {
  page: number;
  productId?: number | null;
  warehouseId?: number | null;
  type?: string | null;
}

export function useStockMovements(storeId: number | null | undefined, filters: StockMovementFilters) {
  return useQuery({
    queryKey: ["stock-movements", storeId, filters],
    queryFn: () =>
      api.getWithMeta<StockMovement[]>(
        `/stock-movements?store_id=${storeId}&page=${filters.page}&per_page=20` +
          (filters.productId ? `&product_id=${filters.productId}` : "") +
          (filters.warehouseId ? `&warehouse_id=${filters.warehouseId}` : "") +
          (filters.type ? `&type=${filters.type}` : ""),
      ),
    enabled: Boolean(storeId),
  });
}

export function useStockTransfers(storeId: number | null | undefined, page: number, warehouseId?: number | null) {
  return useQuery({
    queryKey: ["stock-transfers", storeId, page, warehouseId],
    queryFn: () =>
      api.getWithMeta<StockTransfer[]>(
        `/stock-transfers?store_id=${storeId}&page=${page}&per_page=20` +
          (warehouseId ? `&warehouse_id=${warehouseId}` : ""),
      ),
    enabled: Boolean(storeId),
  });
}

export function useStockTransfer(id: number | null | undefined) {
  return useQuery({
    queryKey: ["stock-transfers", "detail", id],
    queryFn: () => api.get<StockTransfer>(`/stock-transfers/${id}`),
    enabled: Boolean(id),
  });
}

export interface StockAdjustmentPayload {
  product_id: number;
  warehouse_id: number;
  direction: "increase" | "decrease";
  quantity: number;
  reason?: string | null;
}

export function useCreateStockAdjustment() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: StockAdjustmentPayload) => api.post<StockMovement>("/stock-adjustments", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["stock-levels"] });
      queryClient.invalidateQueries({ queryKey: ["stock-movements"] });
      toast.success("Stock adjusted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not adjust stock.");
    },
  });
}

export interface StockTransferPayload {
  store_id: number;
  from_warehouse_id: number;
  to_warehouse_id: number;
  note?: string | null;
  items: { product_id: number; quantity: number }[];
}

export function useCreateStockTransfer() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: StockTransferPayload) => api.post<StockTransfer>("/stock-transfers", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["stock-levels"] });
      queryClient.invalidateQueries({ queryKey: ["stock-movements"] });
      queryClient.invalidateQueries({ queryKey: ["stock-transfers"] });
      toast.success("Stock transfer completed.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not complete stock transfer.");
    },
  });
}
