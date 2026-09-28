"use client";

import { use, useState } from "react";
import { ArrowLeft, Ban, CheckCircle2 } from "lucide-react";
import Link from "next/link";

import { can } from "@/lib/permissions";
import { useCurrentUser } from "@/hooks/use-auth";
import {
  useCancelPurchaseOrder,
  usePlacePurchaseOrder,
  usePurchaseOrder,
  useRecordPurchaseReceipt,
} from "@/hooks/use-purchase-orders";
import { useCreatePurchaseReturn } from "@/hooks/use-purchase-returns";
import { formatMoney } from "@/lib/money";
import { variantLabel } from "@/lib/variant";
import { PermissionDenied } from "@/components/permission-denied";
import { PurchaseReturnRequestForm } from "@/components/purchasing/purchase-return-request-form";
import { ReceivePurchaseOrderForm } from "@/components/purchasing/receive-purchase-order-form";
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
import { ApiError } from "@/types/api";
import type { PurchaseOrderStatus } from "@/types/purchase-order";

type BadgeVariant = BadgeProps["variant"];

const STATUS_LABELS: Record<PurchaseOrderStatus, string> = {
  draft: "Draft",
  ordered: "Ordered",
  partially_received: "Partially received",
  received: "Received",
  cancelled: "Cancelled",
};

const STATUS_VARIANTS: Record<PurchaseOrderStatus, BadgeVariant> = {
  draft: "neutral",
  ordered: "info",
  partially_received: "warning",
  received: "success",
  cancelled: "danger",
};

const RETURN_STATUS_LABELS: Record<string, string> = {
  requested: "Requested",
  approved: "Approved",
  rejected: "Rejected",
  shipped_back: "Shipped back",
  credited: "Credited",
};

const RETURN_STATUS_VARIANTS: Record<string, BadgeVariant> = {
  requested: "neutral",
  approved: "info",
  rejected: "danger",
  shipped_back: "warning",
  credited: "success",
};

export default function PurchaseOrderShowPage({ params }: PageProps<"/purchasing/purchase-orders/[id]">) {
  const { id } = use(params);
  const orderId = Number(id);

  const { data: currentUser } = useCurrentUser();
  const { data: order, isLoading, isError } = usePurchaseOrder(orderId);
  const placeOrder = usePlacePurchaseOrder(orderId);
  const cancelOrder = useCancelPurchaseOrder(orderId);
  const recordReceipt = useRecordPurchaseReceipt(orderId);
  const createReturn = useCreatePurchaseReturn(orderId);
  const [confirmCancel, setConfirmCancel] = useState(false);

  if (currentUser && !can(currentUser, "purchase_orders.view")) {
    return <PermissionDenied />;
  }

  if (isLoading) {
    return <Skeleton className="h-96 w-full max-w-3xl" />;
  }

  if (isError || !order) {
    return (
      <Alert variant="danger">
        <AlertDescription>Could not load this purchase order. It may not exist.</AlertDescription>
      </Alert>
    );
  }

  const canUpdate = can(currentUser, "purchase_orders.update");
  const canCancel = can(currentUser, "purchase_orders.cancel");
  const canReceive = can(currentUser, "purchase_orders.receive");
  const canReceiveNow = canReceive && (order.status === "ordered" || order.status === "partially_received");
  const canRequestReturn =
    can(currentUser, "purchase_returns.create") && (order.status === "partially_received" || order.status === "received");
  const returnableItems = order.items.filter((item) => item.quantity_received > 0);

  return (
    <div className="max-w-3xl space-y-4">
      <Button variant="ghost" size="sm" asChild>
        <Link href="/purchasing/purchase-orders">
          <ArrowLeft />
          Back to purchase orders
        </Link>
      </Button>

      <Card>
        <CardHeader className="flex flex-row items-start justify-between gap-4">
          <div>
            <CardTitle className="flex items-center gap-2">
              {order.po_number}
              <Badge variant={STATUS_VARIANTS[order.status]}>{STATUS_LABELS[order.status]}</Badge>
            </CardTitle>
          </div>
          <div className="flex gap-2">
            {order.status === "draft" && canUpdate ? (
              <Button size="sm" onClick={() => placeOrder.mutate()} loading={placeOrder.isPending}>
                <CheckCircle2 />
                Place order
              </Button>
            ) : null}
            {(order.status === "draft" || order.status === "ordered") && canCancel ? (
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
              <p className="text-text-muted">Supplier</p>
              <p className="font-medium text-text-primary">{order.supplier.name}</p>
            </div>
            <div>
              <p className="text-text-muted">Warehouse</p>
              <p className="font-medium text-text-primary">{order.warehouse.name}</p>
            </div>
            <div>
              <p className="text-text-muted">Created by</p>
              <p className="font-medium text-text-primary">{order.created_by ?? "—"}</p>
            </div>
            <div>
              <p className="text-text-muted">Date</p>
              <p className="font-medium text-text-primary">{new Date(order.created_at).toLocaleString()}</p>
            </div>
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
                    <th className="px-4 py-2 font-medium text-text-secondary">Ordered</th>
                    <th className="px-4 py-2 font-medium text-text-secondary">Received</th>
                    <th className="px-4 py-2 font-medium text-text-secondary">Unit cost</th>
                    <th className="px-4 py-2 font-medium text-text-secondary">Line total</th>
                  </tr>
                </thead>
                <tbody>
                  {order.items.map((item) => (
                    <tr key={item.id} className="border-b border-border last:border-0">
                      <td className="px-4 py-2 text-text-primary">
                        {item.product_name}
                        <span className="ml-1 text-xs text-text-muted">
                          ({item.sku}){variantLabel(item.product_variant) ? ` — ${variantLabel(item.product_variant)}` : ""}
                        </span>
                      </td>
                      <td className="px-4 py-2 text-text-primary">{item.quantity_ordered}</td>
                      <td className="px-4 py-2 text-text-primary">{item.quantity_received}</td>
                      <td className="px-4 py-2 text-text-primary">{formatMoney(item.unit_cost, order.currency_code)}</td>
                      <td className="px-4 py-2 text-text-primary">
                        {formatMoney(item.unit_cost * item.quantity_ordered, order.currency_code)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p className="mt-2 text-right text-sm font-medium text-text-primary">
              Total: {formatMoney(order.total_amount, order.currency_code)}
            </p>
          </div>
        </CardContent>
      </Card>

      {canReceiveNow ? (
        <Card>
          <CardHeader>
            <CardTitle>Record receipt</CardTitle>
          </CardHeader>
          <CardContent>
            <ReceivePurchaseOrderForm
              items={order.items}
              isPending={recordReceipt.isPending}
              serverError={recordReceipt.error instanceof ApiError ? recordReceipt.error.message : null}
              onSubmit={(values) => recordReceipt.mutate(values)}
            />
          </CardContent>
        </Card>
      ) : null}

      {order.receipts.length > 0 ? (
        <Card>
          <CardHeader>
            <CardTitle>Receipt history</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {order.receipts.map((receipt) => (
              <div key={receipt.id} className="rounded-lg border border-border p-3">
                <div className="flex items-center justify-between text-sm">
                  <span className="font-medium text-text-primary">{receipt.receipt_number}</span>
                  <span className="text-text-muted">{new Date(receipt.created_at).toLocaleString()}</span>
                </div>
                <p className="text-xs text-text-muted">Received by {receipt.received_by ?? "—"}</p>
                {receipt.note ? <p className="mt-1 text-sm text-text-primary">{receipt.note}</p> : null}
                <ul className="mt-2 space-y-1 text-sm text-text-primary">
                  {receipt.items.map((item, i) => (
                    <li key={i}>
                      {item.product_name} ({item.product_variant_sku ?? item.sku}) &mdash; {item.quantity_received}
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </CardContent>
        </Card>
      ) : null}

      {order.returns.length > 0 ? (
        <Card>
          <CardHeader>
            <CardTitle>Returns</CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="space-y-2 text-sm">
              {order.returns.map((purchaseReturn) => (
                <li key={purchaseReturn.id} className="flex items-center justify-between">
                  <Link
                    href={`/purchasing/purchase-returns/${purchaseReturn.id}`}
                    className="font-medium text-primary hover:underline"
                  >
                    {purchaseReturn.return_number}
                  </Link>
                  <div className="flex items-center gap-3">
                    {purchaseReturn.credit_amount !== null ? (
                      <span className="text-text-secondary">{formatMoney(purchaseReturn.credit_amount, order.currency_code)}</span>
                    ) : null}
                    <Badge variant={RETURN_STATUS_VARIANTS[purchaseReturn.status]}>
                      {RETURN_STATUS_LABELS[purchaseReturn.status]}
                    </Badge>
                  </div>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      ) : null}

      {canRequestReturn && returnableItems.length > 0 ? (
        <Card>
          <CardHeader>
            <CardTitle>Request a return</CardTitle>
          </CardHeader>
          <CardContent>
            <PurchaseReturnRequestForm
              items={returnableItems}
              isPending={createReturn.isPending}
              serverError={createReturn.error instanceof ApiError ? createReturn.error.message : null}
              onSubmit={(values) => createReturn.mutate(values)}
            />
          </CardContent>
        </Card>
      ) : null}

      <Dialog open={confirmCancel} onOpenChange={setConfirmCancel}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Cancel purchase order</DialogTitle>
            <DialogDescription>
              This will cancel {order.po_number}. It cannot be placed or received against afterwards. This action
              cannot be undone.
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
