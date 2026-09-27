"use client";

import { use } from "react";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";

import { can } from "@/lib/permissions";
import { useCurrentUser } from "@/hooks/use-auth";
import { useStockTransfer } from "@/hooks/use-inventory";
import { PermissionDenied } from "@/components/permission-denied";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";

export default function StockTransferShowPage({ params }: PageProps<"/inventory/transfers/[id]">) {
  const { id } = use(params);
  const transferId = Number(id);

  const { data: currentUser } = useCurrentUser();
  const { data: transfer, isLoading, isError } = useStockTransfer(transferId);

  if (currentUser && !can(currentUser, "inventory.transfer")) {
    return <PermissionDenied />;
  }

  if (isLoading) {
    return <Skeleton className="h-64 w-full max-w-2xl" />;
  }

  if (isError || !transfer) {
    return (
      <Alert variant="danger">
        <AlertDescription>Could not load this transfer. It may not exist.</AlertDescription>
      </Alert>
    );
  }

  return (
    <div className="max-w-2xl space-y-4">
      <Button variant="ghost" size="sm" asChild>
        <Link href="/inventory/transfers">
          <ArrowLeft />
          Back to transfers
        </Link>
      </Button>

      <Card>
        <CardHeader>
          <CardTitle>{transfer.transfer_number}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p className="text-text-muted">From</p>
              <p className="font-medium text-text-primary">{transfer.from_warehouse.name}</p>
            </div>
            <div>
              <p className="text-text-muted">To</p>
              <p className="font-medium text-text-primary">{transfer.to_warehouse.name}</p>
            </div>
            <div>
              <p className="text-text-muted">Created by</p>
              <p className="font-medium text-text-primary">{transfer.created_by ?? "—"}</p>
            </div>
            <div>
              <p className="text-text-muted">Date</p>
              <p className="font-medium text-text-primary">{new Date(transfer.created_at).toLocaleString()}</p>
            </div>
          </div>

          {transfer.note ? (
            <div className="text-sm">
              <p className="text-text-muted">Note</p>
              <p className="text-text-primary">{transfer.note}</p>
            </div>
          ) : null}

          <div>
            <p className="mb-2 text-sm text-text-muted">Items</p>
            <div className="overflow-x-auto rounded-lg border border-border">
              <table className="w-full text-left text-table">
                <thead className="border-b border-border">
                  <tr>
                    <th className="px-4 py-2 font-medium text-text-secondary">Product</th>
                    <th className="px-4 py-2 font-medium text-text-secondary">SKU</th>
                    <th className="px-4 py-2 font-medium text-text-secondary">Quantity</th>
                  </tr>
                </thead>
                <tbody>
                  {transfer.items.map((item) => (
                    <tr key={item.product_id} className="border-b border-border last:border-0">
                      <td className="px-4 py-2 text-text-primary">{item.product_name}</td>
                      <td className="px-4 py-2 text-text-primary">{item.sku}</td>
                      <td className="px-4 py-2 text-text-primary">{item.quantity}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
