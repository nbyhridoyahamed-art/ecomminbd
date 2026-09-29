"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { OrderReturn, RefundMethod } from "@/types/return";

export interface ReturnRequestItemInput {
  order_item_id: number;
  quantity: number;
  exchange_product_id?: number | null;
  exchange_product_variant_id?: number | null;
}

export interface ReturnRequestFormValues {
  reason?: string | null;
  items: ReturnRequestItemInput[];
}

export interface ReceiveReturnItemInput {
  return_item_id: number;
  restock: boolean;
}

export interface ReceiveReturnFormValues {
  items?: ReceiveReturnItemInput[];
  note?: string | null;
}

export interface RefundReturnFormValues {
  refund_amount?: string;
  refund_method?: RefundMethod;
  note?: string | null;
}

export interface ReturnFilters {
  page: number;
  status?: string | null;
}

export function useReturns(storeId: number | null | undefined, filters: ReturnFilters) {
  return useQuery({
    queryKey: ["returns", storeId, filters],
    queryFn: () =>
      api.getWithMeta<OrderReturn[]>(
        `/returns?store_id=${storeId}&page=${filters.page}&per_page=20` +
          (filters.status ? `&status=${filters.status}` : ""),
      ),
    enabled: Boolean(storeId),
  });
}

export function useReturn(id: number | null | undefined) {
  return useQuery({
    queryKey: ["returns", "detail", id],
    queryFn: () => api.get<OrderReturn>(`/returns/${id}`),
    enabled: Boolean(id),
  });
}

function invalidateAfterReturnChange(queryClient: ReturnType<typeof useQueryClient>, returnId?: number) {
  queryClient.invalidateQueries({ queryKey: ["returns"] });
  queryClient.invalidateQueries({ queryKey: ["orders"] });
  queryClient.invalidateQueries({ queryKey: ["stock-levels"] });
  queryClient.invalidateQueries({ queryKey: ["stock-movements"] });
  // A refund may issue store credit, and receive() may create a
  // replacement order — both change data a customer's own page shows.
  queryClient.invalidateQueries({ queryKey: ["customers"] });
  queryClient.invalidateQueries({ queryKey: ["customer-store-credits"] });
  if (returnId) {
    queryClient.invalidateQueries({ queryKey: ["returns", "detail", returnId] });
  }
}

export function useCreateReturn(orderId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: ReturnRequestFormValues) => api.post<OrderReturn>(`/orders/${orderId}/returns`, payload),
    onSuccess: () => {
      invalidateAfterReturnChange(queryClient);
      toast.success("Return requested.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not request this return.");
    },
  });
}

function useReturnAction(id: number, action: string, successMessage: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload?: object) => api.post<OrderReturn>(`/returns/${id}/${action}`, payload),
    onSuccess: (orderReturn) => {
      queryClient.setQueryData(["returns", "detail", id], orderReturn);
      invalidateAfterReturnChange(queryClient, id);
      toast.success(successMessage);
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update this return.");
    },
  });
}

export function useApproveReturn(id: number) {
  return useReturnAction(id, "approve", "Return approved.");
}

export function useRejectReturn(id: number) {
  return useReturnAction(id, "reject", "Return rejected.");
}

export function useReceiveReturn(id: number) {
  return useReturnAction(id, "receive", "Return received.");
}

export function useRefundReturn(id: number) {
  return useReturnAction(id, "refund", "Return refunded.");
}
