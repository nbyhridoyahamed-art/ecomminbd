"use client";

import { useMutation, useQuery } from "@tanstack/react-query";

import { api } from "@/lib/api";
import type { CheckoutPayload, StorefrontOrder } from "@/types/storefront";

export function useStorefrontCheckout() {
  return useMutation({
    mutationFn: (payload: CheckoutPayload) => api.post<StorefrontOrder>("/storefront/checkout", payload),
  });
}

export function useStorefrontOrder(uuid: string | null | undefined) {
  return useQuery({
    queryKey: ["storefront", "orders", uuid],
    queryFn: () => api.get<StorefrontOrder>(`/storefront/orders/${uuid}`),
    enabled: Boolean(uuid),
    retry: false,
  });
}
