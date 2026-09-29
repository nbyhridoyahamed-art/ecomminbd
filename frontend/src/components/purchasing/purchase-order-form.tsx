"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useFieldArray, useForm, useWatch } from "react-hook-form";
import { Plus, Trash2 } from "lucide-react";
import { z } from "zod";

import { VariantPicker } from "@/components/catalog/variant-picker";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { PurchaseOrderFormValues } from "@/hooks/use-purchase-orders";
import type { Product } from "@/types/product";
import type { Supplier } from "@/types/supplier";
import type { Warehouse } from "@/types/warehouse";

const moneyField = z
  .string()
  .min(1, "Required.")
  .refine((v) => /^\d+(\.\d{1,2})?$/.test(v), "Enter a valid amount, e.g. 199.99");

const purchaseOrderSchema = z.object({
  warehouse_id: z.string().min(1, "Select a receiving warehouse."),
  supplier_id: z.string().min(1, "Select a supplier."),
  notes: z.string(),
  items: z
    .array(
      z.object({
        product_id: z.string().min(1, "Select a product."),
        product_variant_id: z.string(),
        quantity_ordered: z
          .string()
          .min(1, "Required.")
          .refine((v) => Number.isInteger(Number(v)) && Number(v) >= 1, "Enter a quantity of at least 1."),
        unit_cost: moneyField,
      }),
    )
    .min(1, "Add at least one product."),
});

type FormValues = z.infer<typeof purchaseOrderSchema>;

interface PurchaseOrderFormProps {
  storeId: number;
  warehouses: Warehouse[];
  suppliers: Supplier[];
  products: Product[];
  onSubmit: (values: PurchaseOrderFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
  submitLabel: string;
}

export function PurchaseOrderForm({
  storeId,
  warehouses,
  suppliers,
  products,
  onSubmit,
  isPending,
  serverError,
  submitLabel,
}: PurchaseOrderFormProps) {
  const {
    register,
    control,
    handleSubmit,
    setValue,
    setError,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(purchaseOrderSchema),
    defaultValues: {
      warehouse_id: "",
      supplier_id: "",
      notes: "",
      items: [{ product_id: "", product_variant_id: "", quantity_ordered: "", unit_cost: "" }],
    },
  });

  const { fields, append, remove } = useFieldArray({ control, name: "items" });
  const warehouseId = useWatch({ control, name: "warehouse_id" });
  const supplierId = useWatch({ control, name: "supplier_id" });
  const items = useWatch({ control, name: "items" });

  const submit = handleSubmit((values) => {
    for (let i = 0; i < values.items.length; i++) {
      const item = values.items[i];
      const product = products.find((p) => String(p.id) === item.product_id);
      if (product && product.type === "variable" && product.variants.length > 0 && !item.product_variant_id) {
        setError(`items.${i}.product_variant_id`, { message: "Select a variant." });
        return;
      }
    }

    onSubmit({
      store_id: storeId,
      warehouse_id: Number(values.warehouse_id),
      supplier_id: Number(values.supplier_id),
      notes: values.notes || null,
      items: values.items.map((item) => ({
        product_id: Number(item.product_id),
        product_variant_id: item.product_variant_id ? Number(item.product_variant_id) : null,
        quantity_ordered: Number(item.quantity_ordered),
        unit_cost: item.unit_cost,
      })),
    });
  });

  return (
    <form onSubmit={submit} className="space-y-4">
      {serverError ? (
        <Alert variant="danger">
          <AlertDescription>{serverError}</AlertDescription>
        </Alert>
      ) : null}

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="purchase-order-form-supplier">Supplier</Label>
          <Select value={supplierId} onValueChange={(v) => setValue("supplier_id", v, { shouldValidate: true })}>
            <SelectTrigger id="purchase-order-form-supplier">
              <SelectValue placeholder="Select supplier">
                {suppliers.find((s) => String(s.id) === supplierId)?.name}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {suppliers.map((s) => (
                <SelectItem key={s.id} value={String(s.id)}>
                  {s.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.supplier_id ? <p className="text-xs text-danger">{errors.supplier_id.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="purchase-order-form-warehouse">Receiving warehouse</Label>
          <Select value={warehouseId} onValueChange={(v) => setValue("warehouse_id", v, { shouldValidate: true })}>
            <SelectTrigger id="purchase-order-form-warehouse">
              <SelectValue placeholder="Select warehouse">
                {warehouses.find((w) => String(w.id) === warehouseId)?.name}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {warehouses.map((w) => (
                <SelectItem key={w.id} value={String(w.id)}>
                  {w.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.warehouse_id ? <p className="text-xs text-danger">{errors.warehouse_id.message}</p> : null}
        </div>
      </div>

      <div className="space-y-2">
        <Label>Items</Label>
        <div className="space-y-2">
          {fields.map((field, index) => {
            const itemErrors = errors.items?.[index];
            const selectedProductId = items?.[index]?.product_id;
            const selectedProduct = products.find((p) => String(p.id) === selectedProductId);
            const selectedVariantId = items?.[index]?.product_variant_id ?? "";
            return (
              <div key={field.id} className="flex items-start gap-2">
                <div className="flex-1 space-y-1">
                  <Select
                    value={selectedProductId}
                    onValueChange={(v) => {
                      setValue(`items.${index}.product_id`, v, { shouldValidate: true });
                      setValue(`items.${index}.product_variant_id`, "");
                    }}
                  >
                    <SelectTrigger aria-label={`Product for item ${index + 1}`}>
                      <SelectValue placeholder="Select product">{selectedProduct?.name}</SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                      {products
                        .filter((p) => p.type !== "bundle")
                        .map((p) => (
                          <SelectItem key={p.id} value={String(p.id)}>
                            {p.name} ({p.sku})
                          </SelectItem>
                        ))}
                    </SelectContent>
                  </Select>
                  {itemErrors?.product_id ? <p className="text-xs text-danger">{itemErrors.product_id.message}</p> : null}
                </div>
                <VariantPicker
                  product={selectedProduct}
                  value={selectedVariantId}
                  onChange={(v) => setValue(`items.${index}.product_variant_id`, v, { shouldValidate: true })}
                  error={itemErrors?.product_variant_id?.message}
                />
                <div className="w-24 space-y-1">
                  <Input
                    type="number"
                    min={1}
                    inputMode="numeric"
                    placeholder="Qty"
                    error={Boolean(itemErrors?.quantity_ordered)}
                    {...register(`items.${index}.quantity_ordered`)}
                  />
                  {itemErrors?.quantity_ordered ? (
                    <p className="text-xs text-danger">{itemErrors.quantity_ordered.message}</p>
                  ) : null}
                </div>
                <div className="w-32 space-y-1">
                  <Input
                    inputMode="decimal"
                    placeholder="Unit cost"
                    error={Boolean(itemErrors?.unit_cost)}
                    {...register(`items.${index}.unit_cost`)}
                  />
                  {itemErrors?.unit_cost ? <p className="text-xs text-danger">{itemErrors.unit_cost.message}</p> : null}
                </div>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  aria-label="Remove item"
                  disabled={fields.length === 1}
                  onClick={() => remove(index)}
                >
                  <Trash2 className="text-danger" />
                </Button>
              </div>
            );
          })}
        </div>
        {errors.items?.root || errors.items?.message ? (
          <p className="text-xs text-danger">{errors.items.root?.message ?? errors.items.message}</p>
        ) : null}
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => append({ product_id: "", product_variant_id: "", quantity_ordered: "", unit_cost: "" })}
        >
          <Plus />
          Add item
        </Button>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="notes">Notes (optional)</Label>
        <Textarea id="notes" rows={2} {...register("notes")} />
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
