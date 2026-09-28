"use client";

import { useMutation, useQuery } from "@tanstack/react-query";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { StorefrontHomepageBlock } from "@/types/storefront";
import { toast } from "sonner";

export function useStorefrontHomepage() {
  return useQuery({
    queryKey: ["storefront", "homepage-blocks"],
    queryFn: () => api.get<StorefrontHomepageBlock[]>("/storefront/homepage-blocks"),
  });
}

export function useNewsletterSubscribe() {
  return useMutation({
    mutationFn: (email: string) => api.post<null>("/storefront/newsletter/subscribe", { email }),
    onSuccess: () => toast.success("Subscribed! Thanks for joining."),
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not subscribe. Please try again."),
  });
}
