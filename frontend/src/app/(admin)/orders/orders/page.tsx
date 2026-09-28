"use client";

import { useState } from "react";
import Link from "next/link";
import { Plus, ShoppingCart } from "lucide-react";

import { can } from "@/lib/permissions";
import { useCurrentUser } from "@/hooks/use-auth";
import { useAllCustomers } from "@/hooks/use-customers";
import { useOrders } from "@/hooks/use-orders";
import { formatMoney } from "@/lib/money";
import { PermissionDenied } from "@/components/permission-denied";
import { Badge, type BadgeProps } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { Order, OrderStatus } from "@/types/order";

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

export default function OrdersPage() {
  const { data: currentUser } = useCurrentUser();
  const storeId = currentUser?.current_store_id;
  const { data: customersData } = useAllCustomers(storeId);
  const customers = customersData?.data ?? [];

  const [page, setPage] = useState(1);
  const [status, setStatus] = useState("all");
  const [customerId, setCustomerId] = useState("all");

  const { data, isLoading } = useOrders(storeId, {
    page,
    status: status !== "all" ? status : null,
    customerId: customerId !== "all" ? Number(customerId) : null,
  });

  if (currentUser && !can(currentUser, "orders.view")) {
    return <PermissionDenied />;
  }

  const canCreate = can(currentUser, "orders.create");

  const columns: DataTableColumn<Order>[] = [
    {
      id: "number",
      header: "Order #",
      cell: (row) => (
        <Link href={`/orders/orders/${row.id}`} className="font-medium text-primary hover:underline">
          {row.order_number}
        </Link>
      ),
    },
    {
      id: "source",
      header: "Source",
      cell: (row) => (
        <Badge variant={row.source === "storefront" ? "info" : "neutral"}>
          {row.source === "storefront" ? "Storefront" : "Admin"}
        </Badge>
      ),
    },
    { id: "customer", header: "Customer", cell: (row) => row.customer.name },
    { id: "warehouse", header: "Warehouse", cell: (row) => row.warehouse.name },
    { id: "items", header: "Items", cell: (row) => row.items.length },
    { id: "total", header: "Total", cell: (row) => formatMoney(row.total_amount, row.currency_code) },
    {
      id: "status",
      header: "Status",
      cell: (row) => <Badge variant={STATUS_VARIANTS[row.status]}>{STATUS_LABELS[row.status]}</Badge>,
    },
    { id: "when", header: "Created", cell: (row) => new Date(row.created_at).toLocaleDateString() },
  ];

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <div className="flex flex-wrap gap-2">
          <Select
            value={status}
            onValueChange={(v) => {
              setStatus(v);
              setPage(1);
            }}
          >
            <SelectTrigger className="w-44">
              <SelectValue placeholder="All statuses" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All statuses</SelectItem>
              {Object.entries(STATUS_LABELS).map(([value, label]) => (
                <SelectItem key={value} value={value}>
                  {label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Select
            value={customerId}
            onValueChange={(v) => {
              setCustomerId(v);
              setPage(1);
            }}
          >
            <SelectTrigger className="w-48">
              <SelectValue placeholder="All customers" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All customers</SelectItem>
              {customers.map((c) => (
                <SelectItem key={c.id} value={String(c.id)}>
                  {c.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        {canCreate ? (
          <Button asChild>
            <Link href="/orders/orders/new">
              <Plus />
              New order
            </Link>
          </Button>
        ) : null}
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
            icon={<ShoppingCart />}
            title="No orders yet"
            description="Create an order to start selling to your customers."
            action={
              canCreate ? (
                <Button asChild size="sm">
                  <Link href="/orders/orders/new">New order</Link>
                </Button>
              ) : undefined
            }
          />
        }
      />
    </div>
  );
}
