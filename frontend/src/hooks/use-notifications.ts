"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { api } from "@/lib/api";
import type { AppNotification } from "@/types/notification";

const NOTIFICATIONS_QUERY_KEY = ["notifications"];

export function useNotifications() {
  return useQuery({
    queryKey: NOTIFICATIONS_QUERY_KEY,
    queryFn: () => api.getWithMeta<AppNotification[]>("/notifications?per_page=10"),
    // A short poll rather than a real push channel (websockets/SSE) — this
    // is Wave 1's only consumer of the notification inbox, so a dedicated
    // realtime transport isn't worth it yet (see DEVELOPMENT_ROADMAP.md).
    refetchInterval: 30000,
  });
}

export function useMarkNotificationRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => api.post<AppNotification>(`/notifications/${id}/read`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_QUERY_KEY });
    },
  });
}

export function useMarkAllNotificationsRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => api.post<null>("/notifications/read-all"),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_QUERY_KEY });
    },
  });
}
