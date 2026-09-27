"use client";

import { useState } from "react";
import Link from "next/link";
import { ArrowLeftRight, Plus } from "lucide-react";

import { can } from "@/lib/permissions";
import { useCurrentUser } from "@/hooks/use-auth";
import { useStockTransfers } from "@/hooks/use-inventory";
import { PermissionDenied } from "@/components/permission-denied";
import { Button } from "@/components/ui/button";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import type { StockTransfer } from "@/types/inventory";

export default function StockTransfersPage() {
  const { data: currentUser } = useCurrentUser();
  const storeId = currentUser?.current_store_id;
  const [page, setPage] = useState(1);

  const { data, isLoading } = useStockTransfers(storeId, page);

  if (currentUser && !can(currentUser, "inventory.transfer")) {
    return <PermissionDenied />;
  }

  const columns: DataTableColumn<StockTransfer>[] = [
    {
      id: "number",
      header: "Transfer #",
      cell: (row) => (
        <Link href={`/inventory/transfers/${row.id}`} className="font-medium text-primary hover:underline">
          {row.transfer_number}
        </Link>
      ),
    },
    { id: "from", header: "From", cell: (row) => row.from_warehouse.name },
    { id: "to", header: "To", cell: (row) => row.to_warehouse.name },
    { id: "items", header: "Items", cell: (row) => row.items.length },
    { id: "by", header: "By", cell: (row) => row.created_by ?? "—" },
    { id: "when", header: "When", cell: (row) => new Date(row.created_at).toLocaleString() },
  ];

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <Button asChild>
          <Link href="/inventory/transfers/new">
            <Plus />
            New transfer
          </Link>
        </Button>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        meta={data?.meta}
        onPageChange={setPage}
        emptyState={
          <EmptyState
            icon={<ArrowLeftRight />}
            title="No transfers yet"
            description="Move stock between warehouses and it'll show up here."
            action={
              <Button asChild size="sm">
                <Link href="/inventory/transfers/new">New transfer</Link>
              </Button>
            }
          />
        }
      />
    </div>
  );
}
