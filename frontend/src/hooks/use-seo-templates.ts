"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { SeoTemplate } from "@/types/seo-template";

export interface SeoTemplateFormValues {
  store_id: number;
  entity_type: string;
  title_template?: string | null;
  description_template?: string | null;
}

export function useSeoTemplates(storeId: number | null | undefined) {
  return useQuery({
    queryKey: ["seo-templates", storeId],
    queryFn: () => api.get<SeoTemplate[]>(`/seo-templates?store_id=${storeId}`),
    enabled: Boolean(storeId),
  });
}

export function useSeoTemplate(id: number | null | undefined) {
  return useQuery({
    queryKey: ["seo-templates", "detail", id],
    queryFn: () => api.get<SeoTemplate>(`/seo-templates/${id}`),
    enabled: Boolean(id),
  });
}

export function useCreateSeoTemplate() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: SeoTemplateFormValues) => api.post<SeoTemplate>("/seo-templates", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["seo-templates"] });
      toast.success("SEO template created.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not create SEO template.");
    },
  });
}

export function useUpdateSeoTemplate(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: SeoTemplateFormValues) => api.put<SeoTemplate>(`/seo-templates/${id}`, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["seo-templates"] });
      toast.success("SEO template updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update SEO template.");
    },
  });
}

export function useDeleteSeoTemplate() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/seo-templates/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["seo-templates"] });
      toast.success("SEO template deleted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete SEO template.");
    },
  });
}
