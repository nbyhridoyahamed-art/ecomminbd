"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { CodSettlement } from "@/types/cod-settlement";

export interface CodSettlementFormValues {
  store_id: number;
  courier_id: number;
  shipment_ids: number[];
  amount_received: string;
  note?: string | null;
}

export function useCodSettlements(storeId: number | null | undefined, page: number, courierId: number | null) {
  return useQuery({
    queryKey: ["cod-settlements", storeId, { page, courierId }],
    queryFn: () =>
      api.getWithMeta<CodSettlement[]>(
        `/cod-settlements?store_id=${storeId}&page=${page}&per_page=20${courierId ? `&courier_id=${courierId}` : ""}`,
      ),
    enabled: Boolean(storeId),
  });
}

export function useCodSettlement(id: number | null | undefined) {
  return useQuery({
    queryKey: ["cod-settlements", "detail", id],
    queryFn: () => api.get<CodSettlement>(`/cod-settlements/${id}`),
    enabled: Boolean(id),
  });
}

export function useCreateCodSettlement() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: CodSettlementFormValues) => api.post<CodSettlement>("/cod-settlements", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["cod-settlements"] });
      queryClient.invalidateQueries({ queryKey: ["shipments"] });
      toast.success("COD settlement recorded.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not record this settlement.");
    },
  });
}
