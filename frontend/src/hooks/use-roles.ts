"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type { Role } from "@/types/role";

export interface RoleFormValues {
  name: string;
  permissions: string[];
}

export function useRoles() {
  return useQuery({
    queryKey: ["roles"],
    queryFn: () => api.get<Role[]>("/roles"),
  });
}

export function usePermissions() {
  return useQuery({
    queryKey: ["permissions"],
    queryFn: () => api.get<string[]>("/permissions"),
  });
}

export function useCreateRole() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: RoleFormValues) => api.post<Role>("/roles", payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["roles"] });
      toast.success("Role created.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not create role.");
    },
  });
}

export function useUpdateRole(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: RoleFormValues) => api.put<Role>(`/roles/${id}`, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["roles"] });
      toast.success("Role updated.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not update role.");
    },
  });
}

export function useDeleteRole() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete<null>(`/roles/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["roles"] });
      toast.success("Role deleted.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not delete role.");
    },
  });
}
