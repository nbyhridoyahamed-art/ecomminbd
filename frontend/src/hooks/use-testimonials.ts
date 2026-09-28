"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { Testimonial } from "@/types/testimonial";

export interface TestimonialFormValues {
  store_id: number;
  name: string;
  role?: string | null;
  quote: string;
  avatar_url?: string | null;
  rating?: number | null;
  sort_order?: number;
  is_active?: boolean;
}

export function useTestimonials(storeId: number | null | undefined) {
  return useQuery({
    queryKey: ["testimonials", storeId],
    queryFn: () => api.get<Testimonial[]>(`/testimonials?store_id=${storeId}`),
    enabled: Boolean(storeId),
  });
}

export function useCreateTestimonial() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: TestimonialFormValues) => api.post<Testimonial>("/testimonials", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["testimonials"] });
      toast.success("Testimonial added.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not add testimonial."),
  });
}

export function useUpdateTestimonial(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: TestimonialFormValues) => api.put<Testimonial>(`/testimonials/${id}`, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["testimonials"] });
      toast.success("Testimonial updated.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not update testimonial."),
  });
}

export function useDeleteTestimonial() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/testimonials/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["testimonials"] });
      toast.success("Testimonial deleted.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not delete testimonial."),
  });
}
