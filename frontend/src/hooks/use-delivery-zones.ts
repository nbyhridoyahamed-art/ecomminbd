"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { DeliveryZone } from "@/types/delivery-zone";

export interface DeliveryZoneRateInput {
  min_order_subtotal: string;
  rate_amount: string;
}

export interface DeliveryZoneFormValues {
  store_id: number;
  name: string;
  bd_division_id?: number | null;
  bd_district_id?: number | null;
  status?: "active" | "inactive";
  rates: DeliveryZoneRateInput[];
}

export function useDeliveryZones(storeId: number | null | undefined, page: number) {
  return useQuery({
    queryKey: ["delivery-zones", storeId, { page }],
    queryFn: () => api.getWithMeta<DeliveryZone[]>(`/delivery-zones?store_id=${storeId}&page=${page}&per_page=20`),
    enabled: Boolean(storeId),
  });
}

export function useDeliveryZone(id: number | null | undefined) {
  return useQuery({
    queryKey: ["delivery-zones", "detail", id],
    queryFn: () => api.get<DeliveryZone>(`/delivery-zones/${id}`),
    enabled: Boolean(id),
  });
}

export function useCreateDeliveryZone() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: DeliveryZoneFormValues) => api.post<DeliveryZone>("/delivery-zones", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["delivery-zones"] });
      toast.success("Delivery zone created.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not create delivery zone.");
    },
  });
}

export function useUpdateDeliveryZone(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: DeliveryZoneFormValues) => api.put<DeliveryZone>(`/delivery-zones/${id}`, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["delivery-zones"] });
      toast.success("Delivery zone updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update delivery zone.");
    },
  });
}

export function useDeleteDeliveryZone() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/delivery-zones/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["delivery-zones"] });
      toast.success("Delivery zone deleted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete delivery zone.");
    },
  });
}

interface DeliveryQuote {
  shipping_amount: number | null;
  zone_name: string | null;
}

/**
 * Backs the admin order form's "Calculate" button — an explicit action
 * triggered on click, not a live-updating query, so it never fights a
 * staff member's own manual edit to the shipping charge field.
 */
export function useDeliveryQuote() {
  return useMutation({
    mutationFn: (params: { storeId: number; bdDivisionId?: number | null; bdDistrictId?: number | null; subtotal: number }) => {
      const query = new URLSearchParams({ store_id: String(params.storeId), subtotal: String(params.subtotal) });
      if (params.bdDivisionId) query.set("bd_division_id", String(params.bdDivisionId));
      if (params.bdDistrictId) query.set("bd_district_id", String(params.bdDistrictId));

      return api.get<DeliveryQuote>(`/delivery-zones/quote?${query.toString()}`);
    },
  });
}
