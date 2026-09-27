"use client";

import { use } from "react";
import { useRouter } from "next/navigation";

import { can } from "@/lib/permissions";
import { useCurrentUser } from "@/hooks/use-auth";
import { useSupplier, useUpdateSupplier } from "@/hooks/use-suppliers";
import { PermissionDenied } from "@/components/permission-denied";
import { SupplierForm } from "@/components/purchasing/supplier-form";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/types/api";

export default function EditSupplierPage({ params }: PageProps<"/purchasing/suppliers/[id]">) {
  const { id } = use(params);
  const supplierId = Number(id);

  const { data: currentUser } = useCurrentUser();
  const storeId = currentUser?.current_store_id;
  const router = useRouter();
  const { data: supplier, isLoading, isError } = useSupplier(supplierId);
  const updateSupplier = useUpdateSupplier(supplierId);

  if (currentUser && !can(currentUser, "suppliers.update")) {
    return <PermissionDenied />;
  }

  if (isLoading || !storeId) {
    return <Skeleton className="h-64 w-full max-w-2xl" />;
  }

  if (isError || !supplier) {
    return (
      <Alert variant="danger">
        <AlertDescription>Could not load this supplier. It may have been deleted.</AlertDescription>
      </Alert>
    );
  }

  return (
    <Card className="max-w-2xl">
      <CardHeader>
        <CardTitle>Edit {supplier.name}</CardTitle>
      </CardHeader>
      <CardContent>
        <SupplierForm
          storeId={storeId}
          defaultValues={supplier}
          isPending={updateSupplier.isPending}
          submitLabel="Save changes"
          serverError={updateSupplier.error instanceof ApiError ? updateSupplier.error.message : null}
          onSubmit={(values) =>
            updateSupplier.mutate(values, {
              onSuccess: () => router.push("/purchasing/suppliers"),
            })
          }
        />
      </CardContent>
    </Card>
  );
}
