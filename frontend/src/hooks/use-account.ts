"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { accountApi } from "@/lib/account-api";
import { CUSTOMER_AUTH_QUERY_KEY } from "@/hooks/use-customer-auth";
import type { AccountOrder, AddressPayload, ProfilePayload } from "@/types/account";
import { ApiError } from "@/types/api";
import type { Customer, CustomerAddress } from "@/types/customer";

export function useAccountOrders(page = 1) {
  return useQuery({
    queryKey: ["account", "orders", page],
    queryFn: () => accountApi.getWithMeta<AccountOrder[]>(`/account/orders?page=${page}&per_page=10`),
  });
}

export function useAccountOrder(uuid: string | null | undefined) {
  return useQuery({
    queryKey: ["account", "orders", "detail", uuid],
    queryFn: () => accountApi.get<AccountOrder>(`/account/orders/${uuid}`),
    enabled: Boolean(uuid),
    retry: false,
  });
}

export function useAccountAddresses() {
  return useQuery({
    queryKey: ["account", "addresses"],
    queryFn: () => accountApi.get<CustomerAddress[]>("/account/addresses"),
  });
}

export function useCreateAccountAddress() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: AddressPayload) => accountApi.post<CustomerAddress>("/account/addresses", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["account", "addresses"] });
      toast.success("Address added.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not add address.");
    },
  });
}

export function useUpdateAccountAddress(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: AddressPayload) => accountApi.put<CustomerAddress>(`/account/addresses/${id}`, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["account", "addresses"] });
      toast.success("Address updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update address.");
    },
  });
}

export function useDeleteAccountAddress() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => accountApi.delete<null>(`/account/addresses/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["account", "addresses"] });
      toast.success("Address deleted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete address.");
    },
  });
}

export function useUpdateAccountProfile() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: ProfilePayload) => accountApi.put<Customer>("/account/profile", payload),
    onSuccess: (customer) => {
      queryClient.setQueryData(CUSTOMER_AUTH_QUERY_KEY, customer);
      toast.success("Profile updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update profile.");
    },
  });
}
