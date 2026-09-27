"use client";

import { useState } from "react";
import { MapPin, Pencil, Plus, Trash2 } from "lucide-react";

import {
  useCreateCustomerAddress,
  useDeleteCustomerAddress,
  useUpdateCustomerAddress,
} from "@/hooks/use-customers";
import { CustomerAddressForm } from "@/components/customers/customer-address-form";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { ApiError } from "@/types/api";
import type { CustomerAddress } from "@/types/customer";

interface CustomerAddressListProps {
  customerId: number;
  addresses: CustomerAddress[];
  canManage: boolean;
}

export function CustomerAddressList({ customerId, addresses, canManage }: CustomerAddressListProps) {
  const [formAddress, setFormAddress] = useState<CustomerAddress | "new" | null>(null);
  const [addressToDelete, setAddressToDelete] = useState<CustomerAddress | null>(null);

  const createAddress = useCreateCustomerAddress(customerId);
  const updateAddress = useUpdateCustomerAddress(customerId, formAddress && formAddress !== "new" ? formAddress.id : 0);
  const deleteAddress = useDeleteCustomerAddress(customerId);

  const isEditing = formAddress !== null && formAddress !== "new";
  const activeMutation = isEditing ? updateAddress : createAddress;

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <p className="text-sm text-text-muted">Saved shipping addresses for this customer.</p>
        {canManage ? (
          <Button size="sm" variant="outline" onClick={() => setFormAddress("new")}>
            <Plus />
            Add address
          </Button>
        ) : null}
      </div>

      {addresses.length === 0 ? (
        <EmptyState icon={<MapPin />} title="No saved addresses" description="Add an address to speed up checkout for this customer." />
      ) : (
        <div className="space-y-2">
          {addresses.map((address) => (
            <div key={address.id} className="flex items-start justify-between gap-3 rounded-lg border border-border p-3">
              <div className="text-sm">
                <div className="flex items-center gap-2">
                  <span className="font-medium text-text-primary">{address.recipient_name}</span>
                  {address.label ? <span className="text-text-muted">({address.label})</span> : null}
                  {address.is_default ? <Badge variant="info">Default</Badge> : null}
                </div>
                <p className="text-text-secondary">{address.phone}</p>
                <p className="text-text-secondary">
                  {address.address_line}
                  {address.upazila ? `, ${address.upazila.name_en}` : ""}
                  {address.district ? `, ${address.district.name_en}` : ""}
                  {address.division ? `, ${address.division.name_en}` : ""}
                </p>
              </div>
              {canManage ? (
                <div className="flex gap-1">
                  <Button variant="ghost" size="icon" aria-label="Edit address" onClick={() => setFormAddress(address)}>
                    <Pencil />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Delete address"
                    onClick={() => setAddressToDelete(address)}
                  >
                    <Trash2 className="text-danger" />
                  </Button>
                </div>
              ) : null}
            </div>
          ))}
        </div>
      )}

      <Dialog open={formAddress !== null} onOpenChange={(open) => !open && setFormAddress(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{isEditing ? "Edit address" : "Add address"}</DialogTitle>
          </DialogHeader>
          <CustomerAddressForm
            defaultValues={isEditing ? formAddress : undefined}
            isPending={activeMutation.isPending}
            submitLabel={isEditing ? "Save changes" : "Add address"}
            serverError={activeMutation.error instanceof ApiError ? activeMutation.error.message : null}
            onSubmit={(values) => activeMutation.mutate(values, { onSuccess: () => setFormAddress(null) })}
          />
        </DialogContent>
      </Dialog>

      <Dialog open={Boolean(addressToDelete)} onOpenChange={(open) => !open && setAddressToDelete(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delete address</DialogTitle>
            <DialogDescription>This action cannot be undone.</DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setAddressToDelete(null)}>
              Cancel
            </Button>
            <Button
              variant="destructive"
              loading={deleteAddress.isPending}
              onClick={() => {
                if (addressToDelete) {
                  deleteAddress.mutate(addressToDelete.id, { onSuccess: () => setAddressToDelete(null) });
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
