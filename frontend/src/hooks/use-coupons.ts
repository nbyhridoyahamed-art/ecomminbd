"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { Coupon, CouponDiscountType, CouponStatus } from "@/types/coupon";

export interface CouponFormValues {
  store_id: number;
  code: string;
  description?: string | null;
  discount_type: CouponDiscountType;
  percentage_value?: number | null;
  fixed_amount?: string | null;
  minimum_order_amount?: string | null;
  usage_limit?: number | null;
  per_customer_limit?: number | null;
  starts_at?: string | null;
  expires_at?: string | null;
  status?: CouponStatus;
}

export function useCoupons(storeId: number | null | undefined, page: number, search: string) {
  return useQuery({
    queryKey: ["coupons", storeId, { page, search }],
    queryFn: () =>
      api.getWithMeta<Coupon[]>(
        `/coupons?store_id=${storeId}&page=${page}&per_page=20${search ? `&search=${encodeURIComponent(search)}` : ""}`,
      ),
    enabled: Boolean(storeId),
  });
}

export function useCoupon(id: number | null | undefined) {
  return useQuery({
    queryKey: ["coupons", "detail", id],
    queryFn: () => api.get<Coupon>(`/coupons/${id}`),
    enabled: Boolean(id),
  });
}

export function useCreateCoupon() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: CouponFormValues) => api.post<Coupon>("/coupons", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["coupons"] });
      toast.success("Coupon created.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not create coupon.");
    },
  });
}

export function useUpdateCoupon(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: CouponFormValues) => api.put<Coupon>(`/coupons/${id}`, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["coupons"] });
      toast.success("Coupon updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update coupon.");
    },
  });
}

export function useDeleteCoupon() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/coupons/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["coupons"] });
      toast.success("Coupon deleted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete coupon.");
    },
  });
}
