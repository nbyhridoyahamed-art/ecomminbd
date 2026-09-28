"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type {
  AnalyticsCustomers,
  AnalyticsFunnel,
  AnalyticsOverview,
  AnalyticsProductRow,
  AnalyticsSearchRow,
} from "@/types/analytics";

export type AnalyticsGranularity = "day" | "week" | "month";

export interface AnalyticsDateRangeFilters {
  dateFrom: string;
  dateTo: string;
}

/**
 * Analytics events aren't warehouse-scoped (unlike Reports), so this stays
 * narrower than use-reports.ts's buildReportFilterParams, which always adds
 * a warehouse_id param.
 */
function buildAnalyticsFilterParams(storeId: number | null | undefined, filters: AnalyticsDateRangeFilters) {
  return new URLSearchParams({
    store_id: String(storeId),
    date_from: filters.dateFrom,
    date_to: filters.dateTo,
  });
}

export interface AnalyticsGranularityFilters extends AnalyticsDateRangeFilters {
  granularity: AnalyticsGranularity;
}

export function useAnalyticsOverview(storeId: number | null | undefined, filters: AnalyticsGranularityFilters) {
  return useQuery({
    queryKey: ["analytics", "overview", storeId, filters],
    queryFn: () => {
      const params = buildAnalyticsFilterParams(storeId, filters);
      params.set("granularity", filters.granularity);
      return api.get<AnalyticsOverview>(`/analytics/overview?${params.toString()}`);
    },
    enabled: Boolean(storeId) && Boolean(filters.dateFrom) && Boolean(filters.dateTo),
  });
}

export function useExportAnalyticsOverview() {
  return useMutation({
    mutationFn: ({ storeId, filters }: { storeId: number; filters: AnalyticsGranularityFilters }) => {
      const params = buildAnalyticsFilterParams(storeId, filters);
      params.set("granularity", filters.granularity);
      return api.download(`/analytics/overview/export?${params.toString()}`, "analytics-overview.csv");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not export the analytics overview.");
    },
  });
}

export function useExportAnalyticsOverviewPdf() {
  return useMutation({
    mutationFn: ({ storeId, filters }: { storeId: number; filters: AnalyticsGranularityFilters }) => {
      const params = buildAnalyticsFilterParams(storeId, filters);
      params.set("granularity", filters.granularity);
      return api.download(`/analytics/overview/export-pdf?${params.toString()}`, "analytics-overview.pdf");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not export the analytics overview.");
    },
  });
}

export function useAnalyticsProducts(
  storeId: number | null | undefined,
  filters: AnalyticsDateRangeFilters,
  page: number,
) {
  return useQuery({
    queryKey: ["analytics", "products", storeId, filters, page],
    queryFn: () => {
      const params = buildAnalyticsFilterParams(storeId, filters);
      params.set("page", String(page));
      params.set("per_page", "20");
      return api.getWithMeta<AnalyticsProductRow[]>(`/analytics/products?${params.toString()}`);
    },
    enabled: Boolean(storeId) && Boolean(filters.dateFrom) && Boolean(filters.dateTo),
  });
}

export function useExportAnalyticsProducts() {
  return useMutation({
    mutationFn: ({ storeId, filters }: { storeId: number; filters: AnalyticsDateRangeFilters }) => {
      const params = buildAnalyticsFilterParams(storeId, filters);
      return api.download(`/analytics/products/export?${params.toString()}`, "analytics-products.csv");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not export the product analytics.");
    },
  });
}

export function useAnalyticsSearches(
  storeId: number | null | undefined,
  filters: AnalyticsDateRangeFilters,
  page: number,
) {
  return useQuery({
    queryKey: ["analytics", "searches", storeId, filters, page],
    queryFn: () => {
      const params = buildAnalyticsFilterParams(storeId, filters);
      params.set("page", String(page));
      params.set("per_page", "20");
      return api.getWithMeta<AnalyticsSearchRow[]>(`/analytics/searches?${params.toString()}`);
    },
    enabled: Boolean(storeId) && Boolean(filters.dateFrom) && Boolean(filters.dateTo),
  });
}

export function useExportAnalyticsSearches() {
  return useMutation({
    mutationFn: ({ storeId, filters }: { storeId: number; filters: AnalyticsDateRangeFilters }) => {
      const params = buildAnalyticsFilterParams(storeId, filters);
      return api.download(`/analytics/searches/export?${params.toString()}`, "analytics-searches.csv");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not export the search analytics.");
    },
  });
}

export function useAnalyticsFunnel(storeId: number | null | undefined, filters: AnalyticsDateRangeFilters) {
  return useQuery({
    queryKey: ["analytics", "funnel", storeId, filters],
    queryFn: () => {
      const params = buildAnalyticsFilterParams(storeId, filters);
      return api.get<AnalyticsFunnel>(`/analytics/funnel?${params.toString()}`);
    },
    enabled: Boolean(storeId) && Boolean(filters.dateFrom) && Boolean(filters.dateTo),
  });
}

export function useAnalyticsCustomers(storeId: number | null | undefined, filters: AnalyticsGranularityFilters) {
  return useQuery({
    queryKey: ["analytics", "customers", storeId, filters],
    queryFn: () => {
      const params = buildAnalyticsFilterParams(storeId, filters);
      params.set("granularity", filters.granularity);
      return api.get<AnalyticsCustomers>(`/analytics/customers?${params.toString()}`);
    },
    enabled: Boolean(storeId) && Boolean(filters.dateFrom) && Boolean(filters.dateTo),
  });
}

export function useExportAnalyticsCustomers() {
  return useMutation({
    mutationFn: ({ storeId, filters }: { storeId: number; filters: AnalyticsGranularityFilters }) => {
      const params = buildAnalyticsFilterParams(storeId, filters);
      params.set("granularity", filters.granularity);
      return api.download(`/analytics/customers/export?${params.toString()}`, "analytics-customers.csv");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not export the customer analytics.");
    },
  });
}
