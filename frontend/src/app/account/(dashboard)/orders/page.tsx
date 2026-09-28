"use client";

import { useState } from "react";
import Link from "next/link";
import { Package } from "lucide-react";

import { formatMoney } from "@/lib/money";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { StorefrontPagination } from "@/components/storefront/storefront-pagination";
import { useAccountOrders } from "@/hooks/use-account";

const STATUS_VARIANT: Record<string, "neutral" | "info" | "warning" | "success" | "danger"> = {
  pending: "neutral",
  processing: "info",
  shipped: "warning",
  delivered: "success",
  cancelled: "danger",
};

export default function AccountOrdersPage() {
  const [page, setPage] = useState(1);
  const { data, isLoading } = useAccountOrders(page);
  const orders = data?.data ?? [];

  return (
    <Card>
      <CardContent className="p-4">
        {isLoading ? (
          <div className="space-y-2">
            <Skeleton className="h-12 w-full" />
            <Skeleton className="h-12 w-full" />
            <Skeleton className="h-12 w-full" />
          </div>
        ) : orders.length === 0 ? (
          <EmptyState
            icon={<Package />}
            title="No orders yet"
            description="Your orders will show up here once you place one."
            action={
              <Button asChild>
                <Link href="/products">Start shopping</Link>
              </Button>
            }
          />
        ) : (
          <>
            <div className="divide-y divide-border">
              {orders.map((order) => (
                <Link
                  key={order.uuid}
                  href={`/account/orders/${order.uuid}`}
                  className="flex items-center justify-between py-3 hover:bg-border/20"
                >
                  <div>
                    <p className="text-sm font-medium text-text-primary">{order.order_number}</p>
                    <p className="text-xs text-text-muted">{new Date(order.created_at).toLocaleDateString()}</p>
                  </div>
                  <div className="flex items-center gap-3">
                    <span className="text-sm text-text-primary">
                      {formatMoney(order.total_amount, order.currency_code)}
                    </span>
                    <Badge variant={STATUS_VARIANT[order.status]}>{order.status}</Badge>
                  </div>
                </Link>
              ))}
            </div>
            <div className="mt-4">
              <StorefrontPagination meta={data?.meta} onPageChange={setPage} />
            </div>
          </>
        )}
      </CardContent>
    </Card>
  );
}
