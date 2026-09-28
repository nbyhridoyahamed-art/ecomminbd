"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { HomepageBlock, SavedSection } from "@/types/homepage-block";

export function useSavedSections(storeId: number | null | undefined) {
  return useQuery({
    queryKey: ["saved-sections", storeId],
    queryFn: () => api.get<SavedSection[]>(`/saved-sections?store_id=${storeId}`),
    enabled: Boolean(storeId),
  });
}

export function useDeleteSavedSection() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/saved-sections/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["saved-sections"] });
      toast.success("Saved section removed.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not remove saved section."),
  });
}

export function useInsertSavedSection() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, storeId }: { id: number; storeId: number }) =>
      api.post<HomepageBlock>(`/saved-sections/${id}/insert`, { store_id: storeId }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["homepage-blocks"] });
      toast.success("Section added to the homepage as a draft block.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not insert section."),
  });
}
