"use client";

import { use } from "react";

import { can } from "@/lib/permissions";
import { useCurrentUser } from "@/hooks/use-auth";
import { useCustomer, useUpdateCustomer } from "@/hooks/use-customers";
import { PermissionDenied } from "@/components/permission-denied";
import { CustomerAddressList } from "@/components/customers/customer-address-list";
import { CustomerForm } from "@/components/customers/customer-form";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/types/api";

export default function EditCustomerPage({ params }: PageProps<"/orders/customers/[id]">) {
  const { id } = use(params);
  const customerId = Number(id);

  const { data: currentUser } = useCurrentUser();
  const storeId = currentUser?.current_store_id;
  const { data: customer, isLoading, isError } = useCustomer(customerId);
  const updateCustomer = useUpdateCustomer(customerId);

  if (currentUser && !can(currentUser, "customers.update")) {
    return <PermissionDenied />;
  }

  if (isLoading || !storeId) {
    return <Skeleton className="h-64 w-full max-w-2xl" />;
  }

  if (isError || !customer) {
    return (
      <Alert variant="danger">
        <AlertDescription>Could not load this customer. It may have been deleted.</AlertDescription>
      </Alert>
    );
  }

  return (
    <div className="max-w-2xl space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Edit {customer.name}</CardTitle>
        </CardHeader>
        <CardContent>
          <CustomerForm
            storeId={storeId}
            defaultValues={customer}
            isPending={updateCustomer.isPending}
            submitLabel="Save changes"
            serverError={updateCustomer.error instanceof ApiError ? updateCustomer.error.message : null}
            onSubmit={(values) => updateCustomer.mutate(values)}
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Addresses</CardTitle>
        </CardHeader>
        <CardContent>
          <CustomerAddressList
            customerId={customer.id}
            addresses={customer.addresses ?? []}
            canManage={can(currentUser, "customers.update")}
          />
        </CardContent>
      </Card>
    </div>
  );
}
