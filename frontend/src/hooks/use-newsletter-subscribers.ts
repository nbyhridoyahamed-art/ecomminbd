"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { NewsletterSubscriber } from "@/types/newsletter-subscriber";

export function useNewsletterSubscribers(storeId: number | null | undefined) {
  return useQuery({
    queryKey: ["newsletter-subscribers", storeId],
    queryFn: () => api.get<NewsletterSubscriber[]>(`/newsletter-subscribers?store_id=${storeId}`),
    enabled: Boolean(storeId),
  });
}

export function useDeleteNewsletterSubscriber() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/newsletter-subscribers/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["newsletter-subscribers"] });
      toast.success("Subscriber removed.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not remove subscriber."),
  });
}
