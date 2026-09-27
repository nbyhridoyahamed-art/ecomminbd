"use client";

import { useState } from "react";
import Link from "next/link";
import { Pencil, Plus, Trash2, Users } from "lucide-react";

import { can } from "@/lib/permissions";
import { useCurrentUser } from "@/hooks/use-auth";
import { useCustomers, useDeleteCustomer } from "@/hooks/use-customers";
import { PermissionDenied } from "@/components/permission-denied";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import type { Customer } from "@/types/customer";

export default function CustomersPage() {
  const { data: currentUser } = useCurrentUser();
  const storeId = currentUser?.current_store_id;
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [customerToDelete, setCustomerToDelete] = useState<Customer | null>(null);

  const { data, isLoading } = useCustomers(storeId, page, search);
  const deleteCustomer = useDeleteCustomer();

  if (currentUser && !can(currentUser, "customers.view")) {
    return <PermissionDenied />;
  }

  const canCreate = can(currentUser, "customers.create");
  const canDelete = can(currentUser, "customers.delete");

  const columns: DataTableColumn<Customer>[] = [
    { id: "name", header: "Name", cell: (c) => <span className="font-medium">{c.name}</span> },
    { id: "phone", header: "Phone", cell: (c) => c.phone },
    { id: "email", header: "Email", cell: (c) => c.email ?? "—" },
    { id: "orders", header: "Orders", cell: (c) => c.orders_count ?? 0 },
    {
      id: "status",
      header: "Status",
      cell: (c) => <Badge variant={c.status === "active" ? "success" : "warning"}>{c.status}</Badge>,
    },
    {
      id: "actions",
      header: "",
      className: "text-right",
      cell: (c) => (
        <div className="flex justify-end gap-1">
          <Button variant="ghost" size="icon" asChild aria-label={`Edit ${c.name}`}>
            <Link href={`/orders/customers/${c.id}`}>
              <Pencil />
            </Link>
          </Button>
          {canDelete ? (
            <Button variant="ghost" size="icon" aria-label={`Delete ${c.name}`} onClick={() => setCustomerToDelete(c)}>
              <Trash2 className="text-danger" />
            </Button>
          ) : null}
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <Input
          placeholder="Search customers..."
          value={search}
          onChange={(event) => {
            setSearch(event.target.value);
            setPage(1);
          }}
          className="max-w-xs"
        />
        {canCreate ? (
          <Button asChild>
            <Link href="/orders/customers/new">
              <Plus />
              Add customer
            </Link>
          </Button>
        ) : null}
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        rowKey={(c) => c.id}
        isLoading={isLoading}
        meta={data?.meta}
        onPageChange={setPage}
        emptyState={
          <EmptyState
            icon={<Users />}
            title="No customers yet"
            description="Add a customer before creating your first order."
            action={
              canCreate ? (
                <Button asChild size="sm">
                  <Link href="/orders/customers/new">Add customer</Link>
                </Button>
              ) : undefined
            }
          />
        }
      />

      <Dialog open={Boolean(customerToDelete)} onOpenChange={(open) => !open && setCustomerToDelete(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delete customer</DialogTitle>
            <DialogDescription>
              {customerToDelete && (customerToDelete.orders_count ?? 0) > 0
                ? `${customerToDelete.orders_count} order(s) reference this customer. Deleting it will not delete those orders, but they will keep a record of a now-deleted customer.`
                : "This action cannot be undone."}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setCustomerToDelete(null)}>
              Cancel
            </Button>
            <Button
              variant="destructive"
              loading={deleteCustomer.isPending}
              onClick={() => {
                if (customerToDelete) {
                  deleteCustomer.mutate(customerToDelete.id, {
                    onSuccess: () => setCustomerToDelete(null),
                  });
                }
              }}
            >
              Delete
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
