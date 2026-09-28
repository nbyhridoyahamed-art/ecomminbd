"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { Redirect } from "@/types/redirect";

export interface RedirectFormValues {
  store_id: number;
  from_path: string;
  to_path: string;
  status_code?: number;
}

export function useRedirects(storeId: number | null | undefined) {
  return useQuery({
    queryKey: ["redirects", storeId],
    queryFn: () => api.get<Redirect[]>(`/redirects?store_id=${storeId}`),
    enabled: Boolean(storeId),
  });
}

export function useRedirect(id: number | null | undefined) {
  return useQuery({
    queryKey: ["redirects", "detail", id],
    queryFn: () => api.get<Redirect>(`/redirects/${id}`),
    enabled: Boolean(id),
  });
}

export function useCreateRedirect() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: RedirectFormValues) => api.post<Redirect>("/redirects", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["redirects"] });
      toast.success("Redirect created.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not create redirect.");
    },
  });
}

export function useUpdateRedirect(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: RedirectFormValues) => api.put<Redirect>(`/redirects/${id}`, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["redirects"] });
      toast.success("Redirect updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update redirect.");
    },
  });
}

export function useDeleteRedirect() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/redirects/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["redirects"] });
      toast.success("Redirect deleted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete redirect.");
    },
  });
}
