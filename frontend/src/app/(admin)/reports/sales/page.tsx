"use client";

import { useState } from "react";
import { DollarSign, Download, Receipt, TrendingUp } from "lucide-react";

import { can } from "@/lib/permissions";
import { formatMoney } from "@/lib/money";
import { useCurrentUser } from "@/hooks/use-auth";
import { useAllWarehouses } from "@/hooks/use-warehouses";
import { useExportSalesReport, useSalesReport } from "@/hooks/use-reports";
import { PermissionDenied } from "@/components/permission-denied";
import { SalesTrendChart } from "@/components/charts/sales-trend-chart";
import { Button } from "@/components/ui/button";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { StatCard } from "@/components/ui/stat-card";
import type { ReportGranularity, SalesReportPaymentMethod } from "@/types/report";

function toDateInput(date: Date): string {
  return date.toISOString().slice(0, 10);
}

function defaultDateRange() {
  const to = new Date();
  const from = new Date();
  from.setDate(from.getDate() - 29);
  return { from: toDateInput(from), to: toDateInput(to) };
}

const GRANULARITY_LABELS: Record<ReportGranularity, string> = {
  day: "Daily",
  week: "Weekly",
  month: "Monthly",
};

export default function SalesReportPage() {
  const { data: currentUser } = useCurrentUser();
  const storeId = currentUser?.current_store_id;
  const { data: warehousesData } = useAllWarehouses(storeId);
  const warehouses = warehousesData?.data ?? [];

  const [dateRange, setDateRange] = useState(defaultDateRange);
  const [warehouseId, setWarehouseId] = useState<number | null>(null);
  const [granularity, setGranularity] = useState<ReportGranularity>("day");

  const filters = { dateFrom: dateRange.from, dateTo: dateRange.to, warehouseId, granularity };
  const { data: report, isLoading } = useSalesReport(storeId, filters);
  const exportReport = useExportSalesReport();

  if (currentUser && !can(currentUser, "reports.view")) {
    return <PermissionDenied />;
  }

  const columns: DataTableColumn<SalesReportPaymentMethod>[] = [
    { id: "method", header: "Payment method", cell: (row) => row.payment_method },
    { id: "orders", header: "Orders", cell: (row) => row.orders_count },
    { id: "revenue", header: "Revenue", cell: (row) => formatMoney(row.revenue_amount, "BDT") },
  ];

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <div className="flex flex-wrap items-center gap-2">
          <Input
            type="date"
            value={dateRange.from}
            max={dateRange.to}
            onChange={(event) => setDateRange((prev) => ({ ...prev, from: event.target.value }))}
            className="w-40"
          />
          <span className="text-sm text-text-muted">to</span>
          <Input
            type="date"
            value={dateRange.to}
            min={dateRange.from}
            onChange={(event) => setDateRange((prev) => ({ ...prev, to: event.target.value }))}
            className="w-40"
          />
          <Select
            value={warehouseId ? String(warehouseId) : "all"}
            onValueChange={(value) => setWarehouseId(value === "all" ? null : Number(value))}
          >
            <SelectTrigger className="w-44">
              <SelectValue placeholder="All warehouses" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All warehouses</SelectItem>
              {warehouses.map((warehouse) => (
                <SelectItem key={warehouse.id} value={String(warehouse.id)}>
                  {warehouse.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Select value={granularity} onValueChange={(value) => setGranularity(value as ReportGranularity)}>
            <SelectTrigger className="w-32">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {(Object.keys(GRANULARITY_LABELS) as ReportGranularity[]).map((key) => (
                <SelectItem key={key} value={key}>
                  {GRANULARITY_LABELS[key]}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <Button
          variant="outline"
          loading={exportReport.isPending}
          onClick={() => storeId && exportReport.mutate({ storeId, filters })}
        >
          <Download />
          Export
        </Button>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard
          label="Revenue"
          value={isLoading ? "—" : formatMoney(report?.totals.revenue_amount ?? 0, "BDT")}
          icon={<DollarSign />}
        />
        <StatCard label="Orders" value={isLoading ? "—" : (report?.totals.orders_count ?? 0)} icon={<Receipt />} />
        <StatCard
          label="Average order value"
          value={isLoading ? "—" : formatMoney(report?.totals.average_order_value ?? 0, "BDT")}
          icon={<TrendingUp />}
        />
      </div>

      <SalesTrendChart data={report?.by_period} isLoading={isLoading} currencyCode="BDT" title="Revenue & orders trend" />

      <DataTable columns={columns} data={report?.by_payment_method ?? []} rowKey={(row) => row.payment_method} isLoading={isLoading} />
    </div>
  );
}
