"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { User } from "@/types/auth";

export interface UserFormValues {
  name: string;
  email: string;
  phone?: string | null;
  password?: string;
  current_store_id?: number | null;
  status?: "active" | "suspended";
  roles?: string[];
}

export function useUsers(page: number, search: string) {
  return useQuery({
    queryKey: ["users", { page, search }],
    queryFn: () =>
      api.getWithMeta<User[]>(
        `/users?page=${page}&per_page=20${search ? `&search=${encodeURIComponent(search)}` : ""}`,
      ),
  });
}

export function useUser(id: number | null | undefined) {
  return useQuery({
    queryKey: ["users", "detail", id],
    queryFn: () => api.get<User>(`/users/${id}`),
    enabled: Boolean(id),
  });
}

export function useCreateUser() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: UserFormValues) => api.post<User>("/users", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["users"] });
      toast.success("User created.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not create user.");
    },
  });
}

export function useUpdateUser(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: UserFormValues) => api.put<User>(`/users/${id}`, payload),
    onSuccess: (user) => {
      queryClient.invalidateQueries({ queryKey: ["users"] });
      queryClient.setQueryData(["users", "detail", id], user);
      toast.success("User updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update user.");
    },
  });
}

export function useDeleteUser() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/users/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["users"] });
      toast.success("User deleted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete user.");
    },
  });
}
