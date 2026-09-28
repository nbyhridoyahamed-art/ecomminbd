"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { BlogPost } from "@/types/blog-post";

export interface BlogPostFormValues {
  store_id: number;
  title: string;
  slug: string;
  excerpt?: string | null;
  featured_image_url?: string | null;
  published_at?: string | null;
  is_active?: boolean;
}

export function useBlogPosts(storeId: number | null | undefined) {
  return useQuery({
    queryKey: ["blog-posts", storeId],
    queryFn: () => api.get<BlogPost[]>(`/blog-posts?store_id=${storeId}`),
    enabled: Boolean(storeId),
  });
}

export function useCreateBlogPost() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: BlogPostFormValues) => api.post<BlogPost>("/blog-posts", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["blog-posts"] });
      toast.success("Post added.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not add post."),
  });
}

export function useUpdateBlogPost(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: BlogPostFormValues) => api.put<BlogPost>(`/blog-posts/${id}`, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["blog-posts"] });
      toast.success("Post updated.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not update post."),
  });
}

export function useDeleteBlogPost() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/blog-posts/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["blog-posts"] });
      toast.success("Post deleted.");
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not delete post."),
  });
}
