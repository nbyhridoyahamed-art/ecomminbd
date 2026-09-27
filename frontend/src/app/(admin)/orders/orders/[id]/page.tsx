"use client";

import { use, useState } from "react";
import { ArrowLeft, Ban, CheckCircle2, PackageCheck, Truck } from "lucide-react";
import Link from "next/link";

import { can } from "@/lib/permissions";
import { useCurrentUser } from "@/hooks/use-auth";
import { useCancelOrder, useDeliverOrder, useOrder, useProcessOrder, useShipOrder } from "@/hooks/use-orders";
import { formatMoney } from "@/lib/money";
import { PermissionDenied } from "@/components/permission-denied";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge, type BadgeProps } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Skeleton } from "@/components/ui/skeleton";
import type { OrderStatus } from "@/types/order";

type BadgeVariant = BadgeProps["variant"];

const STATUS_LABELS: Record<OrderStatus, string> = {
  pending: "Pending",
  processing: "Processing",
  shipped: "Shipped",
  delivered: "Delivered",
  cancelled: "Cancelled",
};

const STATUS_VARIANTS: Record<OrderStatus, BadgeVariant> = {
  pending: "neutral",
  processing: "info",
  shipped: "warning",
  delivered: "success",
  cancelled: "danger",
};

export default function OrderShowPage({ params }: PageProps<"/orders/orders/[id]">) {
  const { id } = use(params);
  const orderId = Number(id);

  const { data: currentUser } = useCurrentUser();
  const { data: order, isLoading, isError } = useOrder(orderId);
  const processOrder = useProcessOrder(orderId);
  const shipOrder = useShipOrder(orderId);
  const deliverOrder = useDeliverOrder(orderId);
  const cancelOrder = useCancelOrder(orderId);
  const [confirmCancel, setConfirmCancel] = useState(false);

  if (currentUser && !can(currentUser, "orders.view")) {
    return <PermissionDenied />;
  }

  if (isLoading) {
    return <Skeleton className="h-96 w-full max-w-3xl" />;
  }

  if (isError || !order) {
    return (
      <Alert variant="danger">
        <AlertDescription>Could not load this order. It may not exist.</AlertDescription>
      </Alert>
    );
  }

  const canUpdate = can(currentUser, "orders.update");
  const canCancel = can(currentUser, "orders.cancel");

  const shippingLine = [order.shipping.address_line, order.shipping.upazila, order.shipping.district, order.shipping.division]
    .filter(Boolean)
    .join(", ");

  return (
    <div className="max-w-3xl space-y-4">
      <Button variant="ghost" size="sm" asChild>
        <Link href="/orders/orders">
          <ArrowLeft />
          Back to orders
        </Link>
      </Button>

      <Card>
        <CardHeader className="flex flex-row items-start justify-between gap-4">
          <div>
            <CardTitle className="flex items-center gap-2">
              {order.order_number}
              <Badge variant={STATUS_VARIANTS[order.status]}>{STATUS_LABELS[order.status]}</Badge>
            </CardTitle>
          </div>
          <div className="flex gap-2">
            {order.status === "pending" && canUpdate ? (
              <Button size="sm" onClick={() => processOrder.mutate()} loading={processOrder.isPending}>
                <PackageCheck />
                Move to processing
              </Button>
            ) : null}
            {(order.status === "pending" || order.status === "processing") && canUpdate ? (
              <Button size="sm" onClick={() => shipOrder.mutate()} loading={shipOrder.isPending}>
                <Truck />
                Ship
              </Button>
            ) : null}
            {order.status === "shipped" && canUpdate ? (
              <Button size="sm" onClick={() => deliverOrder.mutate()} loading={deliverOrder.isPending}>
                <CheckCircle2 />
                Mark delivered
              </Button>
            ) : null}
            {(order.status === "pending" || order.status === "processing") && canCancel ? (
              <Button size="sm" variant="outline" onClick={() => setConfirmCancel(true)}>
                <Ban />
                Cancel
              </Button>
            ) : null}
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
            <div>
              <p className="text-text-muted">Customer</p>
              <p className="font-medium text-text-primary">{order.customer.name}</p>
            </div>
            <div>
              <p className="text-text-muted">Warehouse</p>
              <p className="font-medium text-text-primary">{order.warehouse.name}</p>
            </div>
            <div>
              <p className="text-text-muted">Payment method</p>
              <p className="font-medium text-text-primary">{order.payment_method}</p>
            </div>
            <div>
              <p className="text-text-muted">Date</p>
              <p className="font-medium text-text-primary">{new Date(order.created_at).toLocaleString()}</p>
            </div>
          </div>

          <div className="text-sm">
            <p className="text-text-muted">Shipping to</p>
            <p className="font-medium text-text-primary">{order.shipping.recipient_name}</p>
            <p className="text-text-primary">{order.shipping.phone}</p>
            <p className="text-text-primary">{shippingLine}</p>
          </div>

          {order.notes ? (
            <div className="text-sm">
              <p className="text-text-muted">Notes</p>
              <p className="text-text-primary">{order.notes}</p>
            </div>
          ) : null}

          <div>
            <p className="mb-2 text-sm text-text-muted">Items</p>
            <div className="overflow-x-auto rounded-lg border border-border">
              <table className="w-full text-left text-table">
                <thead className="border-b border-border">
                  <tr>
                    <th className="px-4 py-2 font-medium text-text-secondary">Product</th>
                    <th className="px-4 py-2 font-medium text-text-secondary">Qty</th>
                    <th className="px-4 py-2 font-medium text-text-secondary">Unit price</th>
                    <th className="px-4 py-2 font-medium text-text-secondary">Line total</th>
                  </tr>
                </thead>
                <tbody>
                  {order.items.map((item) => (
                    <tr key={item.id} className="border-b border-border last:border-0">
                      <td className="px-4 py-2 text-text-primary">
                        {item.product_name}
                        <span className="ml-1 text-xs text-text-muted">({item.sku})</span>
                      </td>
                      <td className="px-4 py-2 text-text-primary">{item.quantity}</td>
                      <td className="px-4 py-2 text-text-primary">{formatMoney(item.unit_price, order.currency_code)}</td>
                      <td className="px-4 py-2 text-text-primary">{formatMoney(item.line_total, order.currency_code)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="mt-2 space-y-1 text-right text-sm">
              <p className="text-text-secondary">Subtotal: {formatMoney(order.subtotal_amount, order.currency_code)}</p>
              {order.shipping_amount > 0 ? (
                <p className="text-text-secondary">Shipping: {formatMoney(order.shipping_amount, order.currency_code)}</p>
              ) : null}
              {order.discount_amount > 0 ? (
                <p className="text-text-secondary">Discount: -{formatMoney(order.discount_amount, order.currency_code)}</p>
              ) : null}
              <p className="font-medium text-text-primary">Total: {formatMoney(order.total_amount, order.currency_code)}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Status history</CardTitle>
        </CardHeader>
        <CardContent>
          <ol className="space-y-3">
            {order.status_history.map((entry, index) => (
              <li key={index} className="flex items-center justify-between text-sm">
                <div>
                  <Badge variant={STATUS_VARIANTS[entry.to_status]}>{STATUS_LABELS[entry.to_status]}</Badge>
                  {entry.note ? <span className="ml-2 text-text-secondary">{entry.note}</span> : null}
                </div>
                <div className="text-right text-text-muted">
                  <p>{new Date(entry.created_at).toLocaleString()}</p>
                  {entry.created_by ? <p>{entry.created_by}</p> : null}
                </div>
              </li>
            ))}
          </ol>
        </CardContent>
      </Card>

      <Dialog open={confirmCancel} onOpenChange={setConfirmCancel}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Cancel order</DialogTitle>
            <DialogDescription>
              This will cancel {order.order_number} and release its reserved stock. This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setConfirmCancel(false)}>
              Keep order
            </Button>
            <Button
              variant="destructive"
              loading={cancelOrder.isPending}
              onClick={() => cancelOrder.mutate(undefined, { onSuccess: () => setConfirmCancel(false) })}
            >
              Cancel order
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
