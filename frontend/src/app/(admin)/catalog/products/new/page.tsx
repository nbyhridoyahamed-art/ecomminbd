"use client";

import { useRouter } from "next/navigation";

import { can } from "@/lib/permissions";
import { useCurrentUser } from "@/hooks/use-auth";
import { useCategories } from "@/hooks/use-categories";
import { useAllBrands } from "@/hooks/use-brands";
import { useCreateProduct } from "@/hooks/use-products";
import { PermissionDenied } from "@/components/permission-denied";
import { ProductForm } from "@/components/settings/product-form";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ApiError } from "@/types/api";

export default function NewProductPage() {
  const { data: currentUser } = useCurrentUser();
  const storeId = currentUser?.current_store_id;
  const router = useRouter();
  const { data: categories } = useCategories(storeId);
  const { data: brandsData } = useAllBrands(storeId);
  const createProduct = useCreateProduct();

  if (currentUser && !can(currentUser, "products.create")) {
    return <PermissionDenied />;
  }

  if (!storeId) {
    return null;
  }

  return (
    <Card className="max-w-3xl">
      <CardHeader>
        <CardTitle>Add product</CardTitle>
      </CardHeader>
      <CardContent>
        <ProductForm
          storeId={storeId}
          categories={categories ?? []}
          brands={brandsData?.data ?? []}
          isPending={createProduct.isPending}
          submitLabel="Create product"
          serverError={createProduct.error instanceof ApiError ? createProduct.error.message : null}
          onSubmit={(values) =>
            createProduct.mutate(values, {
              onSuccess: (product) => router.push(`/catalog/products/${product.id}`),
            })
          }
        />
      </CardContent>
    </Card>
  );
}
