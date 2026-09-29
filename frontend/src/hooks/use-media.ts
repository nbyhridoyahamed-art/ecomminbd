"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { Media } from "@/types/media";

export interface MediaFilters {
  page: number;
  search?: string;
}

function buildMediaParams(storeId: number, filters: MediaFilters) {
  const params = new URLSearchParams();
  params.set("store_id", String(storeId));
  params.set("page", String(filters.page));
  params.set("per_page", "24");
  if (filters.search) params.set("search", filters.search);
  return params;
}

export function useMedia(storeId: number | null | undefined, filters: MediaFilters) {
  return useQuery({
    queryKey: ["media", storeId, filters],
    queryFn: () => api.getWithMeta<Media[]>(`/media?${buildMediaParams(storeId!, filters).toString()}`),
    enabled: Boolean(storeId),
  });
}

export function useUploadMedia() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ file, storeId, altText }: { file: File; storeId: number; altText?: string }) => {
      const formData = new FormData();
      formData.append("image", file);
      formData.append("store_id", String(storeId));
      if (altText) formData.append("alt_text", altText);
      return api.post<Media>("/media", formData);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["media"] });
      toast.success("Uploaded to the media library.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not upload the file.");
    },
  });
}

export function useUpdateMedia() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, altText }: { id: number; altText: string }) =>
      api.put<Media>(`/media/${id}`, { alt_text: altText }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["media"] });
      toast.success("Media updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update media.");
    },
  });
}

export function useDeleteMedia() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/media/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["media"] });
      toast.success("Media deleted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete media.");
    },
  });
}
