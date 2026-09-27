"use client";

import { useQuery } from "@tanstack/react-query";

import { api } from "@/lib/api";
import type { OrderStatusBreakdown, SalesTrendPoint } from "@/types/dashboard";

export function useSalesTrend(storeId: number | null | undefined, days = 14) {
  return useQuery({
    queryKey: ["dashboard", "sales-trend", storeId, days],
    queryFn: () => api.get<SalesTrendPoint[]>(`/dashboard/sales-trend?store_id=${storeId}&days=${days}`),
    enabled: Boolean(storeId),
  });
}

export function useOrderStatusBreakdown(storeId: number | null | undefined) {
  return useQuery({
    queryKey: ["dashboard", "order-status-breakdown", storeId],
    queryFn: () => api.get<OrderStatusBreakdown>(`/dashboard/order-status-breakdown?store_id=${storeId}`),
    enabled: Boolean(storeId),
  });
}
