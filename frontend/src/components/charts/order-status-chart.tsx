"use client";

import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import type { OrderStatusBreakdown } from "@/types/dashboard";

interface OrderStatusChartProps {
  data: OrderStatusBreakdown | undefined;
  isLoading: boolean;
}

const STATUS_LABELS: Record<keyof OrderStatusBreakdown, string> = {
  pending: "Pending",
  processing: "Processing",
  shipped: "Shipped",
  delivered: "Delivered",
  cancelled: "Cancelled",
};

const STATUS_COLORS: Record<keyof OrderStatusBreakdown, string> = {
  pending: "var(--color-text-muted)",
  processing: "var(--color-info)",
  shipped: "var(--color-warning)",
  delivered: "var(--color-success)",
  cancelled: "var(--color-danger)",
};

export function OrderStatusChart({ data, isLoading }: OrderStatusChartProps) {
  const chartData = (Object.keys(STATUS_LABELS) as Array<keyof OrderStatusBreakdown>).map((status) => ({
    status,
    label: STATUS_LABELS[status],
    count: data?.[status] ?? 0,
  }));

  return (
    <Card>
      <CardHeader>
        <CardTitle>Orders by status</CardTitle>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <Skeleton className="h-64 w-full" />
        ) : (
          <ResponsiveContainer width="100%" height={256}>
            <BarChart data={chartData} margin={{ top: 4, right: 8, bottom: 0, left: 0 }}>
              <CartesianGrid stroke="var(--color-border)" strokeDasharray="3 3" vertical={false} />
              <XAxis
                dataKey="label"
                stroke="var(--color-text-muted)"
                fontSize={12}
                tickLine={false}
                axisLine={false}
              />
              <YAxis
                stroke="var(--color-text-muted)"
                fontSize={12}
                tickLine={false}
                axisLine={false}
                allowDecimals={false}
                width={32}
              />
              <Tooltip
                contentStyle={{
                  backgroundColor: "var(--color-surface)",
                  borderColor: "var(--color-border)",
                  borderRadius: "0.5rem",
                  fontSize: "0.8125rem",
                }}
              />
              <Bar dataKey="count" radius={[4, 4, 0, 0]}>
                {chartData.map((entry) => (
                  <Cell key={entry.status} fill={STATUS_COLORS[entry.status]} />
                ))}
              </Bar>
            </BarChart>
          </ResponsiveContainer>
        )}
      </CardContent>
    </Card>
  );
}
