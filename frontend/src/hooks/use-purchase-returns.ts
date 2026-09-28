"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { PurchaseReturn } from "@/types/purchase-return";

export interface PurchaseReturnRequestItemInput {
  purchase_order_item_id: number;
  quantity: number;
}

export interface PurchaseReturnRequestFormValues {
  reason?: string | null;
  items: PurchaseReturnRequestItemInput[];
}

export interface PurchaseReturnFilters {
  page: number;
  status?: string | null;
}

export function usePurchaseReturns(storeId: number | null | undefined, filters: PurchaseReturnFilters) {
  return useQuery({
    queryKey: ["purchase-returns", storeId, filters],
    queryFn: () =>
      api.getWithMeta<PurchaseReturn[]>(
        `/purchase-returns?store_id=${storeId}&page=${filters.page}&per_page=20` +
          (filters.status ? `&status=${filters.status}` : ""),
      ),
    enabled: Boolean(storeId),
  });
}

export function usePurchaseReturn(id: number | null | undefined) {
  return useQuery({
    queryKey: ["purchase-returns", "detail", id],
    queryFn: () => api.get<PurchaseReturn>(`/purchase-returns/${id}`),
    enabled: Boolean(id),
  });
}

function invalidateAfterPurchaseReturnChange(queryClient: ReturnType<typeof useQueryClient>, returnId?: number) {
  queryClient.invalidateQueries({ queryKey: ["purchase-returns"] });
  queryClient.invalidateQueries({ queryKey: ["purchase-orders"] });
  queryClient.invalidateQueries({ queryKey: ["stock-levels"] });
  queryClient.invalidateQueries({ queryKey: ["stock-movements"] });
  if (returnId) {
    queryClient.invalidateQueries({ queryKey: ["purchase-returns", "detail", returnId] });
  }
}

export function useCreatePurchaseReturn(purchaseOrderId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: PurchaseReturnRequestFormValues) =>
      api.post<PurchaseReturn>(`/purchase-orders/${purchaseOrderId}/returns`, payload),
    onSuccess: () => {
      invalidateAfterPurchaseReturnChange(queryClient);
      toast.success("Purchase return requested.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not request this return.");
    },
  });
}

function usePurchaseReturnAction(id: number, action: string, successMessage: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload?: object) => api.post<PurchaseReturn>(`/purchase-returns/${id}/${action}`, payload),
    onSuccess: (purchaseReturn) => {
      queryClient.setQueryData(["purchase-returns", "detail", id], purchaseReturn);
      invalidateAfterPurchaseReturnChange(queryClient, id);
      toast.success(successMessage);
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update this return.");
    },
  });
}

export function useApprovePurchaseReturn(id: number) {
  return usePurchaseReturnAction(id, "approve", "Purchase return approved.");
}

export function useRejectPurchaseReturn(id: number) {
  return usePurchaseReturnAction(id, "reject", "Purchase return rejected.");
}

export function useShipBackPurchaseReturn(id: number) {
  return usePurchaseReturnAction(id, "ship-back", "Purchase return shipped back.");
}

export function useCreditPurchaseReturn(id: number) {
  return usePurchaseReturnAction(id, "credit", "Purchase return credited.");
}
