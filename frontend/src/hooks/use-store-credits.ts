"use client";

import { useQuery } from "@tanstack/react-query";

import { api } from "@/lib/api";
import type { CustomerStoreCredit } from "@/types/store-credit";

export function useCustomerStoreCredits(customerId: number | null | undefined) {
  return useQuery({
    queryKey: ["customer-store-credits", customerId],
    queryFn: () => api.getWithMeta<CustomerStoreCredit[]>(`/customers/${customerId}/store-credits?per_page=50`),
    enabled: Boolean(customerId),
  });
}
