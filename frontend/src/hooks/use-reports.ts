"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";
import type {
  LowStockReportRow,
  ProductPerformanceRow,
  ReorderSuggestionRow,
  ReportGranularity,
  SalesReport,
} from "@/types/report";

export interface DateRangeFilters {
  dateFrom: string;
  dateTo: string;
  warehouseId?: number | null;
}

function buildReportFilterParams(storeId: number | null | undefined, filters: DateRangeFilters) {
  const params = new URLSearchParams({
    store_id: String(storeId),
    date_from: filters.dateFrom,
    date_to: filters.dateTo,
  });
  if (filters.warehouseId) params.set("warehouse_id", String(filters.warehouseId));
  return params;
}

export interface SalesReportFilters extends DateRangeFilters {
  granularity: ReportGranularity;
}

export function useSalesReport(storeId: number | null | undefined, filters: SalesReportFilters) {
  return useQuery({
    queryKey: ["reports", "sales", storeId, filters],
    queryFn: () => {
      const params = buildReportFilterParams(storeId, filters);
      params.set("granularity", filters.granularity);
      return api.get<SalesReport>(`/reports/sales?${params.toString()}`);
    },
    enabled: Boolean(storeId) && Boolean(filters.dateFrom) && Boolean(filters.dateTo),
  });
}

export function useExportSalesReport() {
  return useMutation({
    mutationFn: ({ storeId, filters }: { storeId: number; filters: SalesReportFilters }) => {
      const params = buildReportFilterParams(storeId, filters);
      params.set("granularity", filters.granularity);
      return api.download(`/reports/sales/export?${params.toString()}`, "sales-report.csv");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not export the sales report.");
    },
  });
}

export function useExportSalesReportPdf() {
  return useMutation({
    mutationFn: ({ storeId, filters }: { storeId: number; filters: SalesReportFilters }) => {
      const params = buildReportFilterParams(storeId, filters);
      params.set("granularity", filters.granularity);
      return api.download(`/reports/sales/export-pdf?${params.toString()}`, "sales-report.pdf");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not export the sales report.");
    },
  });
}

export function useProductPerformance(
  storeId: number | null | undefined,
  filters: DateRangeFilters,
  page: number,
) {
  return useQuery({
    queryKey: ["reports", "products-performance", storeId, filters, page],
    queryFn: () => {
      const params = buildReportFilterParams(storeId, filters);
      params.set("page", String(page));
      params.set("per_page", "20");
      return api.getWithMeta<ProductPerformanceRow[]>(`/reports/products-performance?${params.toString()}`);
    },
    enabled: Boolean(storeId) && Boolean(filters.dateFrom) && Boolean(filters.dateTo),
  });
}

export function useExportProductPerformance() {
  return useMutation({
    mutationFn: ({ storeId, filters }: { storeId: number; filters: DateRangeFilters }) => {
      const params = buildReportFilterParams(storeId, filters);
      return api.download(`/reports/products-performance/export?${params.toString()}`, "product-performance.csv");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not export the product performance report.");
    },
  });
}

export function useExportProductPerformancePdf() {
  return useMutation({
    mutationFn: ({ storeId, filters }: { storeId: number; filters: DateRangeFilters }) => {
      const params = buildReportFilterParams(storeId, filters);
      return api.download(`/reports/products-performance/export-pdf?${params.toString()}`, "product-performance.pdf");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not export the product performance report.");
    },
  });
}

export function useLowStockReport(storeId: number | null | undefined, page: number) {
  return useQuery({
    queryKey: ["reports", "low-stock", storeId, page],
    queryFn: () =>
      api.getWithMeta<LowStockReportRow[]>(
        `/reports/low-stock?store_id=${storeId}&page=${page}&per_page=20`,
      ),
    enabled: Boolean(storeId),
  });
}

export function useExportLowStockReport() {
  return useMutation({
    mutationFn: ({ storeId }: { storeId: number }) =>
      api.download(`/reports/low-stock/export?store_id=${storeId}`, "low-stock-report.csv"),
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not export the low stock report.");
    },
  });
}

export function useExportLowStockReportPdf() {
  return useMutation({
    mutationFn: ({ storeId }: { storeId: number }) =>
      api.download(`/reports/low-stock/export-pdf?store_id=${storeId}`, "low-stock-report.pdf"),
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not export the low stock report.");
    },
  });
}

export function useReorderSuggestions(storeId: number | null | undefined, page: number) {
  return useQuery({
    queryKey: ["reports", "reorder-suggestions", storeId, page],
    queryFn: () =>
      api.getWithMeta<ReorderSuggestionRow[]>(
        `/reports/reorder-suggestions?store_id=${storeId}&page=${page}&per_page=20`,
      ),
    enabled: Boolean(storeId),
  });
}

export function useExportReorderSuggestions() {
  return useMutation({
    mutationFn: ({ storeId }: { storeId: number }) =>
      api.download(`/reports/reorder-suggestions/export?store_id=${storeId}`, "reorder-suggestions.csv"),
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not export the reorder suggestions report.");
    },
  });
}

export function useExportReorderSuggestionsPdf() {
  return useMutation({
    mutationFn: ({ storeId }: { storeId: number }) =>
      api.download(`/reports/reorder-suggestions/export-pdf?store_id=${storeId}`, "reorder-suggestions.pdf"),
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not export the reorder suggestions report.");
    },
  });
}
