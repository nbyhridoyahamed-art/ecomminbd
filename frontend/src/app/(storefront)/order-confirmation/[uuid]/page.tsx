"use client";

import { use, useEffect, useRef } from "react";
import Link from "next/link";
import { notFound } from "next/navigation";
import { CheckCircle2 } from "lucide-react";

import { trackEvent } from "@/lib/analytics";
import { formatMoney } from "@/lib/money";
import { ApiError } from "@/types/api";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useStorefrontOrder } from "@/hooks/use-storefront-checkout";

const PAYMENT_METHOD_LABELS: Record<string, string> = {
  cod: "Cash on Delivery",
};

export default function OrderConfirmationPage({ params }: PageProps<"/order-confirmation/[uuid]">) {
  const { uuid } = use(params);
  const { data: order, isLoading, error } = useStorefrontOrder(uuid);
  const hasTrackedPurchaseRef = useRef(false);

  useEffect(() => {
    const orderUuid = order?.uuid;
    if (!orderUuid || hasTrackedPurchaseRef.current) return;
    hasTrackedPurchaseRef.current = true;
    trackEvent("purchase", { order_uuid: orderUuid });
  }, [order?.uuid]);

  if (error instanceof ApiError && error.status === 404) {
    notFound();
  }

  if (isLoading) {
    return (
      <div className="mx-auto max-w-2xl space-y-4 px-4 py-8">
        <Skeleton className="h-8 w-2/3" />
        <Skeleton className="h-48 w-full" />
      </div>
    );
  }

  if (error || !order) {
    return (
      <div className="mx-auto max-w-2xl px-4 py-16 text-center">
        <p className="text-text-secondary">Something went wrong loading this order. Please try again.</p>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6 px-4 py-8">
      <div className="space-y-2 text-center">
        <CheckCircle2 className="mx-auto size-12 text-success" />
        <h1 className="text-page-title font-semibold text-text-primary">Order placed successfully!</h1>
        <p className="text-text-secondary">
          Order <span className="font-medium text-text-primary">{order.order_number}</span> — we&apos;ll contact you
          at {order.shipping.phone} to confirm delivery.
        </p>
      </div>

      <div className="space-y-4 rounded-lg border border-border p-4">
        <div className="flex items-center justify-between">
          <h2 className="font-semibold text-text-primary">Order Summary</h2>
          <span className="text-sm text-text-secondary">
            {PAYMENT_METHOD_LABELS[order.payment_method] ?? order.payment_method}
          </span>
        </div>

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
          <div className="flex justify-between text-text-secondary">
            <span>Shipping</span>
            <span>{formatMoney(order.shipping_amount, order.currency_code)}</span>
          </div>
          {order.discount_amount > 0 ? (
            <div className="flex justify-between text-text-secondary">
              <span>Discount{order.coupon_code ? ` (${order.coupon_code})` : ""}</span>
              <span>-{formatMoney(order.discount_amount, order.currency_code)}</span>
            </div>
          ) : null}
          <div className="flex justify-between font-semibold text-text-primary">
            <span>Total</span>
            <span>{formatMoney(order.total_amount, order.currency_code)}</span>
          </div>
        </div>
      </div>

      <div className="space-y-2 rounded-lg border border-border p-4">
        <h2 className="font-semibold text-text-primary">Shipping To</h2>
        <p className="text-sm text-text-secondary">
          {order.shipping.recipient_name} &middot; {order.shipping.phone}
          <br />
          {order.shipping.address_line}
          {order.shipping.upazila || order.shipping.district || order.shipping.division ? (
            <>
              <br />
              {[order.shipping.upazila, order.shipping.district, order.shipping.division].filter(Boolean).join(", ")}
            </>
          ) : null}
        </p>
      </div>

      <Button asChild size="lg" className="w-full">
        <Link href="/products">Continue shopping</Link>
      </Button>
    </div>
  );
}
