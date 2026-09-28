"use client";

import Link from "next/link";
import { Package } from "lucide-react";

import { formatMoney } from "@/lib/money";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { useAccountOrders } from "@/hooks/use-account";
import { useCustomerLogout } from "@/hooks/use-customer-auth";

const STATUS_VARIANT: Record<string, "neutral" | "info" | "warning" | "success" | "danger"> = {
  pending: "neutral",
  processing: "info",
  shipped: "warning",
  delivered: "success",
  cancelled: "danger",
};

export default function AccountOverviewPage() {
  const { data, isLoading } = useAccountOrders(1);
  const logout = useCustomerLogout();
  const recentOrders = data?.data.slice(0, 3) ?? [];

  return (
    <div className="space-y-6">
      <div className="grid gap-4 tablet:grid-cols-3">
        <Card>
          <CardContent className="p-4">
            <p className="text-sm text-text-secondary">Total orders</p>
            <p className="text-page-title font-semibold text-text-primary">{data?.meta?.total ?? "–"}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="flex items-center justify-between p-4">
            <div>
              <p className="text-sm text-text-secondary">Addresses</p>
              <Link href="/account/addresses" className="text-sm font-medium text-primary hover:underline">
                Manage
              </Link>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="flex items-center justify-between p-4">
            <div>
              <p className="text-sm text-text-secondary">Account</p>
              <Button variant="link" className="h-auto p-0 text-danger" onClick={() => logout.mutate()}>
                Sign out
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Recent Orders</CardTitle>
          <Link href="/account/orders" className="text-sm font-medium text-primary hover:underline">
            View all
          </Link>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-2">
              <Skeleton className="h-12 w-full" />
              <Skeleton className="h-12 w-full" />
            </div>
          ) : recentOrders.length === 0 ? (
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
            <div className="divide-y divide-border">
              {recentOrders.map((order) => (
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
          )}
        </CardContent>
      </Card>
    </div>
  );
}
