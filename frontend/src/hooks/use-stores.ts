"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { Currency } from "@/types/currency";
import type { Store } from "@/types/store";

export function useStore(id: number | null | undefined) {
  return useQuery({
    queryKey: ["stores", id],
    queryFn: () => api.get<Store>(`/stores/${id}`),
    enabled: Boolean(id),
  });
}

export function useCurrencies() {
  return useQuery({
    queryKey: ["currencies"],
    queryFn: () => api.get<Currency[]>("/currencies"),
  });
}

export interface StoreFormValues {
  organization_id: number;
  name: string;
  slug: string;
  domain?: string | null;
  default_currency_id?: number | null;
  default_timezone?: string;
  default_locale?: string;
  status?: "active" | "inactive";
}

export function useUpdateStore(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: StoreFormValues) => api.put<Store>(`/stores/${id}`, payload),
    onSuccess: (store) => {
      queryClient.setQueryData(["stores", id], store);
      toast.success("Store settings saved.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not save store settings.");
    },
  });
}
