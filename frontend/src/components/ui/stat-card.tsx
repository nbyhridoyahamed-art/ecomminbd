import * as React from "react";

import { cn } from "@/lib/utils";
import { Card, CardContent } from "@/components/ui/card";

interface StatCardProps {
  label: string;
  value: React.ReactNode;
  icon?: React.ReactNode;
  trend?: { direction: "up" | "down" | "flat"; label: string };
  /** Accent color for the icon badge — "danger" calls out an alert-style stat (e.g. low stock). */
  tone?: "primary" | "danger";
  className?: string;
}

function StatCard({ label, value, icon, trend, tone = "primary", className }: StatCardProps) {
  return (
    <Card className={cn(className)}>
      <CardContent className="flex items-start justify-between gap-4 p-5">
        <div className="space-y-1">
          <p className="text-sm text-text-secondary">{label}</p>
          <p className="text-2xl font-semibold text-text-primary">{value}</p>
          {trend ? (
            <p
              className={cn(
                "text-xs font-medium",
                trend.direction === "up" && "text-success",
                trend.direction === "down" && "text-danger",
                trend.direction === "flat" && "text-text-muted",
              )}
            >
              {trend.label}
            </p>
          ) : null}
        </div>
        {icon ? (
          <div
            className={cn(
              "flex size-9 shrink-0 items-center justify-center rounded-md [&_svg]:size-5",
              tone === "danger" ? "bg-danger/10 text-danger" : "bg-primary/10 text-primary",
            )}
          >
            {icon}
          </div>
        ) : null}
      </CardContent>
    </Card>
  );
}

export { StatCard };
