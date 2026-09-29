"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { StockAdjustmentSession, StockLevel, StockMovement, StockTransfer } from "@/types/inventory";

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

export function useStockTransfers(
  storeId: number | null | undefined,
  page: number,
  warehouseId?: number | null,
  status?: string | null,
) {
  return useQuery({
    queryKey: ["stock-transfers", storeId, page, warehouseId, status],
    queryFn: () =>
      api.getWithMeta<StockTransfer[]>(
        `/stock-transfers?store_id=${storeId}&page=${page}&per_page=20` +
          (warehouseId ? `&warehouse_id=${warehouseId}` : "") +
          (status ? `&status=${status}` : ""),
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
  product_variant_id?: number | null;
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
      // A variant's stock summary is served on the product detail response,
      // not stock-levels, so an adjustment made from the product's Variants
      // tab needs this too.
      queryClient.invalidateQueries({ queryKey: ["products"] });
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
  items: { product_id: number; product_variant_id?: number | null; quantity: number }[];
}

export function useCreateStockTransfer() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: StockTransferPayload) => api.post<StockTransfer>("/stock-transfers", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["stock-transfers"] });
      toast.success("Stock transfer created — ship it once it's ready to go.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not create stock transfer.");
    },
  });
}

function invalidateAfterStockTransferTransition(queryClient: ReturnType<typeof useQueryClient>) {
  queryClient.invalidateQueries({ queryKey: ["stock-transfers"] });
  queryClient.invalidateQueries({ queryKey: ["stock-levels"] });
  queryClient.invalidateQueries({ queryKey: ["stock-movements"] });
  queryClient.invalidateQueries({ queryKey: ["products"] });
}

export function useShipStockTransfer() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.post<StockTransfer>(`/stock-transfers/${id}/ship`),
    onSuccess: () => {
      invalidateAfterStockTransferTransition(queryClient);
      toast.success("Stock transfer marked in transit.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not ship stock transfer.");
    },
  });
}

export function useReceiveStockTransfer() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.post<StockTransfer>(`/stock-transfers/${id}/receive`),
    onSuccess: () => {
      invalidateAfterStockTransferTransition(queryClient);
      toast.success("Stock transfer received.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not receive stock transfer.");
    },
  });
}

export function useCancelStockTransfer() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, note }: { id: number; note?: string }) =>
      api.post<StockTransfer>(`/stock-transfers/${id}/cancel`, { note }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["stock-transfers"] });
      toast.success("Stock transfer cancelled.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not cancel stock transfer.");
    },
  });
}

export function useStockAdjustmentSessions(
  storeId: number | null | undefined,
  page: number,
  warehouseId?: number | null,
) {
  return useQuery({
    queryKey: ["stock-adjustment-sessions", storeId, page, warehouseId],
    queryFn: () =>
      api.getWithMeta<StockAdjustmentSession[]>(
        `/stock-adjustment-sessions?store_id=${storeId}&page=${page}&per_page=20` +
          (warehouseId ? `&warehouse_id=${warehouseId}` : ""),
      ),
    enabled: Boolean(storeId),
  });
}

export function useStockAdjustmentSession(id: number | null | undefined) {
  return useQuery({
    queryKey: ["stock-adjustment-sessions", "detail", id],
    queryFn: () => api.get<StockAdjustmentSession>(`/stock-adjustment-sessions/${id}`),
    enabled: Boolean(id),
  });
}

export interface StockAdjustmentSessionPayload {
  store_id: number;
  warehouse_id: number;
  reference?: string | null;
  note?: string | null;
  items: {
    product_id: number;
    product_variant_id?: number | null;
    direction: "increase" | "decrease";
    quantity: number;
    reason?: string | null;
  }[];
}

export function useCreateStockAdjustmentSession() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: StockAdjustmentSessionPayload) =>
      api.post<StockAdjustmentSession>("/stock-adjustment-sessions", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["stock-adjustment-sessions"] });
      queryClient.invalidateQueries({ queryKey: ["stock-levels"] });
      queryClient.invalidateQueries({ queryKey: ["stock-movements"] });
      queryClient.invalidateQueries({ queryKey: ["products"] });
      toast.success("Stocktake session recorded.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not record stocktake session.");
    },
  });
}
