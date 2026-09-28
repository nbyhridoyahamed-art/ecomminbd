"use client";

import { use } from "react";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft } from "lucide-react";

import { formatMoney } from "@/lib/money";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { useAccountOrder } from "@/hooks/use-account";
import { ApiError } from "@/types/api";

const STATUS_VARIANT: Record<string, "neutral" | "info" | "warning" | "success" | "danger"> = {
  pending: "neutral",
  processing: "info",
  shipped: "warning",
  delivered: "success",
  cancelled: "danger",
};

const PAYMENT_METHOD_LABELS: Record<string, string> = {
  cod: "Cash on Delivery",
};

export default function AccountOrderDetailPage({ params }: PageProps<"/account/orders/[uuid]">) {
  const { uuid } = use(params);
  const { data: order, isLoading, error } = useAccountOrder(uuid);

  if (error instanceof ApiError && error.status === 404) {
    notFound();
  }

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-1/3" />
        <Skeleton className="h-48 w-full" />
      </div>
    );
  }

  if (error || !order) {
    return <p className="text-text-secondary">Something went wrong loading this order. Please try again.</p>;
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <Link
            href="/account/orders"
            className="mb-1 flex items-center gap-1 text-sm text-text-secondary hover:text-text-primary"
          >
            <ArrowLeft className="size-4" />
            Back to orders
          </Link>
          <h2 className="text-lg font-semibold text-text-primary">{order.order_number}</h2>
          <p className="text-sm text-text-muted">{new Date(order.created_at).toLocaleString()}</p>
        </div>
        <Badge variant={STATUS_VARIANT[order.status]}>{order.status}</Badge>
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Order Summary</CardTitle>
          <span className="text-sm text-text-secondary">
            {PAYMENT_METHOD_LABELS[order.payment_method] ?? order.payment_method}
          </span>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2 divide-y divide-border">
            {order.items.map((item, index) => (
              <div key={index} className="flex justify-between py-2 text-sm">
                <span className="text-text-secondary">
                  {item.product_name}
                  {item.product_variant
                    ? ` (${item.product_variant.attribute_values.map((av) => av.value).join(" / ")})`
                    : ""}{" "}
                  &times; {item.quantity}
                </span>
                <span className="text-text-primary">{formatMoney(item.line_total, order.currency_code)}</span>
              </div>
            ))}
          </div>

          <div className="space-y-1 border-t border-border pt-3 text-sm">
            <div className="flex justify-between text-text-secondary">
              <span>Subtotal</span>
              <span>{formatMoney(order.subtotal_amount, order.currency_code)}</span>
            </div>
            {order.discount_amount > 0 ? (
              <div className="flex justify-between text-text-secondary">
                <span>Discount</span>
                <span>-{formatMoney(order.discount_amount, order.currency_code)}</span>
              </div>
            ) : null}
            <div className="flex justify-between text-text-secondary">
              <span>Shipping</span>
              <span>{formatMoney(order.shipping_amount, order.currency_code)}</span>
            </div>
            <div className="flex justify-between font-semibold text-text-primary">
              <span>Total</span>
              <span>{formatMoney(order.total_amount, order.currency_code)}</span>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Shipping To</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-text-secondary">
            {order.shipping.recipient_name} &middot; {order.shipping.phone}
            <br />
            {order.shipping.address_line}
            {order.shipping.upazila || order.shipping.district || order.shipping.division ? (
              <>
                <br />
                {[order.shipping.upazila, order.shipping.district, order.shipping.division]
                  .filter(Boolean)
                  .join(", ")}
              </>
            ) : null}
          </p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Status History</CardTitle>
        </CardHeader>
        <CardContent>
          <ol className="space-y-3">
            {order.status_history.map((entry, index) => (
              <li key={index} className="flex items-center justify-between text-sm">
                <Badge variant={STATUS_VARIANT[entry.to_status]}>{entry.to_status}</Badge>
                <span className="text-text-muted">{new Date(entry.created_at).toLocaleString()}</span>
              </li>
            ))}
          </ol>
        </CardContent>
      </Card>
    </div>
  );
}
