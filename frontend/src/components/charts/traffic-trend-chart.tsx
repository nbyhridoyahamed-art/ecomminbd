"use client";

import {
  Area,
  CartesianGrid,
  ComposedChart,
  Line,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import type { AnalyticsTrafficPoint } from "@/types/analytics";

interface TrafficTrendChartProps {
  data: AnalyticsTrafficPoint[] | undefined;
  isLoading: boolean;
  title: string;
}

function formatShortDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString("en-US", { month: "short", day: "numeric" });
}

export function TrafficTrendChart({ data, isLoading, title }: TrafficTrendChartProps) {
  const hasData = (data?.length ?? 0) > 0;

  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <Skeleton className="h-64 w-full" />
        ) : !hasData ? (
          <div className="flex h-64 items-center justify-center">
            <p className="text-sm text-text-muted">No traffic recorded for this range.</p>
          </div>
        ) : (
          <>
            <ResponsiveContainer width="100%" height={256}>
              <ComposedChart data={data} margin={{ top: 4, right: 8, bottom: 0, left: 0 }}>
                <CartesianGrid stroke="var(--color-border)" strokeDasharray="3 3" vertical={false} />
                <XAxis
                  dataKey="date"
                  tickFormatter={formatShortDate}
                  stroke="var(--color-text-muted)"
                  fontSize={12}
                  tickLine={false}
                  axisLine={false}
                />
                <YAxis
                  yAxisId="pageViews"
                  stroke="var(--color-text-muted)"
                  fontSize={12}
                  tickLine={false}
                  axisLine={false}
                  allowDecimals={false}
                  width={40}
                />
                <YAxis
                  yAxisId="sessions"
                  orientation="right"
                  stroke="var(--color-text-muted)"
                  fontSize={12}
                  tickLine={false}
                  axisLine={false}
                  allowDecimals={false}
                  width={40}
                />
                <Tooltip
                  labelFormatter={(label) => new Date(String(label)).toLocaleDateString()}
                  formatter={(value, name) =>
                    name === "page_views" ? [Number(value), "Page views"] : [Number(value), "Unique sessions"]
                  }
                  contentStyle={{
                    backgroundColor: "var(--color-surface)",
                    borderColor: "var(--color-border)",
                    borderRadius: "0.5rem",
                    fontSize: "0.8125rem",
                  }}
                />
                <Area
                  yAxisId="pageViews"
                  type="monotone"
                  dataKey="page_views"
                  stroke="var(--color-primary)"
                  fill="var(--color-primary)"
                  fillOpacity={0.12}
                  strokeWidth={2}
                />
                <Line
                  yAxisId="sessions"
                  type="monotone"
                  dataKey="unique_sessions"
                  stroke="var(--color-success)"
                  strokeWidth={2}
                  strokeDasharray="4 3"
                  dot={false}
                />
              </ComposedChart>
            </ResponsiveContainer>
            <div className="mt-2 flex items-center gap-4 text-xs text-text-muted">
              <span className="flex items-center gap-1.5">
                <span className="inline-block h-2 w-2 rounded-full bg-primary" /> Page views
              </span>
              <span className="flex items-center gap-1.5">
                <span className="inline-block h-2 w-2 rounded-full bg-success" /> Unique sessions
              </span>
            </div>
          </>
        )}
      </CardContent>
    </Card>
  );
}
