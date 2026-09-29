"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { accountApi } from "@/lib/account-api";
import { api } from "@/lib/api";
import { useCustomerAuthToken } from "@/lib/customer-auth-token";
import { ApiError } from "@/types/api";
import type { Review, ReviewSubmissionPayload } from "@/types/review";

export interface ReviewFilters {
  page: number;
  status?: string | null;
  product_id?: number | null;
  rating?: number | null;
}

function buildReviewParams(storeId: number, filters: ReviewFilters) {
  const params = new URLSearchParams();
  params.set("store_id", String(storeId));
  params.set("page", String(filters.page));
  params.set("per_page", "20");
  if (filters.status) params.set("status", filters.status);
  if (filters.product_id) params.set("product_id", String(filters.product_id));
  if (filters.rating) params.set("rating", String(filters.rating));
  return params;
}

/** Admin moderation queue. */
export function useReviews(storeId: number | null | undefined, filters: ReviewFilters) {
  return useQuery({
    queryKey: ["reviews", storeId, filters],
    queryFn: () => api.getWithMeta<Review[]>(`/reviews?${buildReviewParams(storeId!, filters).toString()}`),
    enabled: Boolean(storeId),
  });
}

function invalidateReviews(queryClient: ReturnType<typeof useQueryClient>) {
  queryClient.invalidateQueries({ queryKey: ["reviews"] });
}

export function useApproveReview() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.post<Review>(`/reviews/${id}/approve`),
    onSuccess: () => {
      invalidateReviews(queryClient);
      toast.success("Review approved.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not approve review.");
    },
  });
}

export function useRejectReview() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.post<Review>(`/reviews/${id}/reject`),
    onSuccess: () => {
      invalidateReviews(queryClient);
      toast.success("Review rejected.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not reject review.");
    },
  });
}

export function useDeleteReview() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/reviews/${id}`),
    onSuccess: () => {
      invalidateReviews(queryClient);
      toast.success("Review deleted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete review.");
    },
  });
}

/** The signed-in customer's own reviews, any status. Safe to call from public pages — disabled when logged out. */
export function useAccountReviews() {
  const token = useCustomerAuthToken();

  return useQuery({
    queryKey: ["account", "reviews"],
    queryFn: () => accountApi.get<Review[]>("/account/reviews"),
    enabled: Boolean(token),
  });
}

export function useSubmitReview() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: ReviewSubmissionPayload) => accountApi.post<Review>("/account/reviews", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["account", "reviews"] });
      queryClient.invalidateQueries({ queryKey: ["account", "orders"] });
      toast.success("Review submitted — it will appear once approved.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not submit review.");
    },
  });
}

/** Public, approved-only reviews for a storefront product detail page. */
export function useStorefrontProductReviews(slug: string | null | undefined, page = 1) {
  return useQuery({
    queryKey: ["storefront", "products", slug, "reviews", page],
    queryFn: () => api.getWithMeta<Review[]>(`/storefront/products/${slug}/reviews?page=${page}&per_page=10`),
    enabled: Boolean(slug),
  });
}
