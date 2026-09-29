"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { PurchaseOrder, PurchaseReceipt } from "@/types/purchase-order";

export interface PurchaseOrderItemInput {
  product_id: number;
  product_variant_id?: number | null;
  quantity_ordered: number;
  unit_cost: string;
}

export interface PurchaseOrderFormValues {
  store_id: number;
  warehouse_id: number;
  supplier_id: number;
  currency_code?: string | null;
  notes?: string | null;
  items: PurchaseOrderItemInput[];
}

export interface PurchaseOrderFilters {
  page: number;
  status?: string | null;
  supplierId?: number | null;
  warehouseId?: number | null;
}

export function usePurchaseOrders(storeId: number | null | undefined, filters: PurchaseOrderFilters) {
  return useQuery({
    queryKey: ["purchase-orders", storeId, filters],
    queryFn: () =>
      api.getWithMeta<PurchaseOrder[]>(
        `/purchase-orders?store_id=${storeId}&page=${filters.page}&per_page=20` +
          (filters.status ? `&status=${filters.status}` : "") +
          (filters.supplierId ? `&supplier_id=${filters.supplierId}` : "") +
          (filters.warehouseId ? `&warehouse_id=${filters.warehouseId}` : ""),
      ),
    enabled: Boolean(storeId),
  });
}

export function usePurchaseOrder(id: number | null | undefined) {
  return useQuery({
    queryKey: ["purchase-orders", "detail", id],
    queryFn: () => api.get<PurchaseOrder>(`/purchase-orders/${id}`),
    enabled: Boolean(id),
  });
}

export function useCreatePurchaseOrder() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: PurchaseOrderFormValues) => api.post<PurchaseOrder>("/purchase-orders", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["purchase-orders"] });
      toast.success("Purchase order created.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not create purchase order.");
    },
  });
}

export function useUpdatePurchaseOrder(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: PurchaseOrderFormValues) => api.put<PurchaseOrder>(`/purchase-orders/${id}`, payload),
    onSuccess: (order) => {
      queryClient.invalidateQueries({ queryKey: ["purchase-orders"] });
      queryClient.setQueryData(["purchase-orders", "detail", id], order);
      toast.success("Purchase order updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update purchase order.");
    },
  });
}

export function useDeletePurchaseOrder() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/purchase-orders/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["purchase-orders"] });
      toast.success("Purchase order deleted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete purchase order.");
    },
  });
}

export function useSubmitPurchaseOrderForApproval(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => api.post<PurchaseOrder>(`/purchase-orders/${id}/submit-for-approval`),
    onSuccess: (order) => {
      queryClient.invalidateQueries({ queryKey: ["purchase-orders"] });
      queryClient.setQueryData(["purchase-orders", "detail", id], order);
      toast.success("Purchase order submitted for approval.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not submit purchase order for approval.");
    },
  });
}

export function useApprovePurchaseOrder(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => api.post<PurchaseOrder>(`/purchase-orders/${id}/approve`),
    onSuccess: (order) => {
      queryClient.invalidateQueries({ queryKey: ["purchase-orders"] });
      queryClient.setQueryData(["purchase-orders", "detail", id], order);
      toast.success("Purchase order approved and placed.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not approve purchase order.");
    },
  });
}

export function useRejectPurchaseOrder(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (note?: string) => api.post<PurchaseOrder>(`/purchase-orders/${id}/reject`, { note }),
    onSuccess: (order) => {
      queryClient.invalidateQueries({ queryKey: ["purchase-orders"] });
      queryClient.setQueryData(["purchase-orders", "detail", id], order);
      toast.success("Purchase order rejected and reopened for editing.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not reject purchase order.");
    },
  });
}

export function useCancelPurchaseOrder(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => api.post<PurchaseOrder>(`/purchase-orders/${id}/cancel`),
    onSuccess: (order) => {
      queryClient.invalidateQueries({ queryKey: ["purchase-orders"] });
      queryClient.setQueryData(["purchase-orders", "detail", id], order);
      toast.success("Purchase order cancelled.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not cancel purchase order.");
    },
  });
}

export interface PurchaseReceiptPayload {
  note?: string | null;
  items: { purchase_order_item_id: number; quantity_received: number }[];
}

export function useRecordPurchaseReceipt(orderId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: PurchaseReceiptPayload) =>
      api.post<PurchaseReceipt>(`/purchase-orders/${orderId}/receipts`, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["purchase-orders", "detail", orderId] });
      queryClient.invalidateQueries({ queryKey: ["purchase-orders"] });
      queryClient.invalidateQueries({ queryKey: ["stock-levels"] });
      queryClient.invalidateQueries({ queryKey: ["stock-movements"] });
      toast.success("Receipt recorded.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not record receipt.");
    },
  });
}
