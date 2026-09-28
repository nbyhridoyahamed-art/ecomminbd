"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";

import { accountApi } from "@/lib/account-api";
import { clearCustomerAuthToken, setCustomerAuthToken, useCustomerAuthToken } from "@/lib/customer-auth-token";
import type { AccountAuthResult, LoginPayload, RegisterPayload } from "@/types/account";
import type { Customer } from "@/types/customer";

export const CUSTOMER_AUTH_QUERY_KEY = ["account", "auth", "me"];

export function useCurrentCustomer() {
  const token = useCustomerAuthToken();

  return useQuery({
    queryKey: CUSTOMER_AUTH_QUERY_KEY,
    queryFn: () => accountApi.get<Customer>("/account/auth/me"),
    enabled: Boolean(token),
    retry: false,
  });
}

export function useCustomerRegister() {
  const queryClient = useQueryClient();
  const router = useRouter();

  return useMutation({
    mutationFn: (payload: RegisterPayload) => accountApi.post<AccountAuthResult>("/account/auth/register", payload),
    onSuccess: (data) => {
      setCustomerAuthToken(data.token);
      queryClient.setQueryData(CUSTOMER_AUTH_QUERY_KEY, data.customer);
      router.push("/account");
    },
  });
}

export function useCustomerLogin() {
  const queryClient = useQueryClient();
  const router = useRouter();

  return useMutation({
    mutationFn: (payload: LoginPayload) => accountApi.post<AccountAuthResult>("/account/auth/login", payload),
    onSuccess: (data) => {
      setCustomerAuthToken(data.token);
      queryClient.setQueryData(CUSTOMER_AUTH_QUERY_KEY, data.customer);
      router.push("/account");
    },
  });
}

export function useCustomerLogout() {
  const queryClient = useQueryClient();
  const router = useRouter();

  return useMutation({
    mutationFn: () => accountApi.post<null>("/account/auth/logout"),
    onSettled: () => {
      clearCustomerAuthToken();
      queryClient.removeQueries({ queryKey: CUSTOMER_AUTH_QUERY_KEY });
      router.push("/account/login");
    },
  });
}
