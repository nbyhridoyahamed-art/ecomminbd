"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { HomepageBlock, HomepageBlockRevision, HomepageBlockType } from "@/types/homepage-block";
import type { StorefrontHomepageBlock } from "@/types/storefront";

export interface HomepageBlockPreview extends StorefrontHomepageBlock {
  is_active: boolean;
}

export interface HomepageBlockFormValues {
  store_id?: number;
  type?: HomepageBlockType;
  settings: Record<string, unknown>;
  styles?: Record<string, unknown>;
  responsive?: Record<string, unknown>;
  visibility?: Record<string, unknown>;
  animation?: string | null;
}

function invalidate(queryClient: ReturnType<typeof useQueryClient>) {
  queryClient.invalidateQueries({ queryKey: ["homepage-blocks"] });
}

export function useHomepageBlocks(storeId: number | null | undefined) {
  return useQuery({
    queryKey: ["homepage-blocks", storeId],
    queryFn: () => api.get<HomepageBlock[]>(`/homepage-blocks?store_id=${storeId}`),
    enabled: Boolean(storeId),
  });
}

/** Every block for the store, draft included, each already resolved exactly like the public endpoint — powers the builder canvas's live preview. */
export function useHomepageBlockPreview(storeId: number | null | undefined) {
  return useQuery({
    queryKey: ["homepage-blocks", "preview", storeId],
    queryFn: () => api.get<HomepageBlockPreview[]>(`/homepage-blocks/preview?store_id=${storeId}`),
    enabled: Boolean(storeId),
  });
}

export function useHomepageBlock(id: number | null | undefined) {
  return useQuery({
    queryKey: ["homepage-blocks", "detail", id],
    queryFn: () => api.get<HomepageBlock>(`/homepage-blocks/${id}`),
    enabled: Boolean(id),
  });
}

export function useCreateHomepageBlock() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: HomepageBlockFormValues) => api.post<HomepageBlock>("/homepage-blocks", payload),
    onSuccess: () => {
      invalidate(queryClient);
      toast.success("Block added.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not add block."),
  });
}

export function useUpdateHomepageBlock(id: number, options?: { silent?: boolean }) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: HomepageBlockFormValues) => api.put<HomepageBlock>(`/homepage-blocks/${id}`, payload),
    onSuccess: () => {
      invalidate(queryClient);
      if (!options?.silent) toast.success("Block updated.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not update block."),
  });
}

export function useDeleteHomepageBlock() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/homepage-blocks/${id}`),
    onSuccess: () => {
      invalidate(queryClient);
      toast.success("Block deleted.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not delete block."),
  });
}

export function useReorderHomepageBlocks() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (order: number[]) => api.post<null>("/homepage-blocks/reorder", { order }),
    onSuccess: () => invalidate(queryClient),
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not reorder blocks."),
  });
}

export function useDuplicateHomepageBlock() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.post<HomepageBlock>(`/homepage-blocks/${id}/duplicate`),
    onSuccess: () => {
      invalidate(queryClient);
      toast.success("Block duplicated.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not duplicate block."),
  });
}

export function usePublishHomepageBlock() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.post<HomepageBlock>(`/homepage-blocks/${id}/publish`),
    onSuccess: () => {
      invalidate(queryClient);
      toast.success("Block published.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not publish block."),
  });
}

export function useUnpublishHomepageBlock() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.post<HomepageBlock>(`/homepage-blocks/${id}/unpublish`),
    onSuccess: () => {
      invalidate(queryClient);
      toast.success("Block unpublished.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not unpublish block."),
  });
}

export function useScheduleHomepageBlock() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, scheduledAt }: { id: number; scheduledAt: string }) =>
      api.post<HomepageBlock>(`/homepage-blocks/${id}/schedule`, { scheduled_at: scheduledAt }),
    onSuccess: () => {
      invalidate(queryClient);
      toast.success("Block scheduled.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not schedule block."),
  });
}

export function useHomepageBlockRevisions(id: number | null | undefined) {
  return useQuery({
    queryKey: ["homepage-blocks", "revisions", id],
    queryFn: () => api.get<HomepageBlockRevision[]>(`/homepage-blocks/${id}/revisions`),
    enabled: Boolean(id),
  });
}

export function useRestoreHomepageBlockRevision(blockId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (revisionId: number) => api.post<HomepageBlock>(`/homepage-blocks/${blockId}/revisions/${revisionId}/restore`),
    onSuccess: () => {
      invalidate(queryClient);
      queryClient.invalidateQueries({ queryKey: ["homepage-blocks", "revisions", blockId] });
      toast.success("Block restored.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not restore block."),
  });
}

export function useSaveHomepageBlockAsSection() {
  return useMutation({
    mutationFn: ({ id, name }: { id: number; name: string }) => api.post(`/homepage-blocks/${id}/save-as-section`, { name }),
    onSuccess: () => toast.success("Saved to your section library."),
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not save section."),
  });
}
