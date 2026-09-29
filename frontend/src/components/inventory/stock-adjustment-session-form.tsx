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
import type { StockAdjustmentSessionPayload } from "@/hooks/use-inventory";
import type { Product } from "@/types/product";
import type { Warehouse } from "@/types/warehouse";

const sessionSchema = z.object({
  warehouse_id: z.string().min(1, "Select a warehouse."),
  reference: z.string(),
  note: z.string(),
  items: z
    .array(
      z.object({
        product_id: z.string().min(1, "Select a product."),
        product_variant_id: z.string(),
        direction: z.enum(["increase", "decrease"]),
        quantity: z
          .string()
          .min(1, "Required.")
          .refine((v) => Number.isInteger(Number(v)) && Number(v) >= 1, "Enter a quantity of at least 1."),
        reason: z.string(),
      }),
    )
    .min(1, "Add at least one product."),
});

type FormValues = z.infer<typeof sessionSchema>;

interface StockAdjustmentSessionFormProps {
  storeId: number;
  warehouses: Warehouse[];
  products: Product[];
  defaultWarehouseId?: number | null;
  onSubmit: (values: StockAdjustmentSessionPayload) => void;
  isPending: boolean;
  serverError?: string | null;
}

export function StockAdjustmentSessionForm({
  storeId,
  warehouses,
  products,
  defaultWarehouseId,
  onSubmit,
  isPending,
  serverError,
}: StockAdjustmentSessionFormProps) {
  const {
    register,
    control,
    handleSubmit,
    setValue,
    setError,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(sessionSchema),
    defaultValues: {
      warehouse_id: defaultWarehouseId ? String(defaultWarehouseId) : "",
      reference: "",
      note: "",
      items: [{ product_id: "", product_variant_id: "", direction: "increase", quantity: "", reason: "" }],
    },
  });

  const { fields, append, remove } = useFieldArray({ control, name: "items" });
  const warehouseId = useWatch({ control, name: "warehouse_id" });
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
      reference: values.reference || null,
      note: values.note || null,
      items: values.items.map((item) => ({
        product_id: Number(item.product_id),
        product_variant_id: item.product_variant_id ? Number(item.product_variant_id) : null,
        direction: item.direction,
        quantity: Number(item.quantity),
        reason: item.reason || null,
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
          <Label htmlFor="stocktake-form-warehouse">Warehouse</Label>
          <Select value={warehouseId} onValueChange={(v) => setValue("warehouse_id", v, { shouldValidate: true })}>
            <SelectTrigger id="stocktake-form-warehouse">
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

        <div className="space-y-1.5">
          <Label htmlFor="stocktake-form-reference">Reference (optional)</Label>
          <Input id="stocktake-form-reference" placeholder="e.g. Q3 count" {...register("reference")} />
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
            const direction = items?.[index]?.direction ?? "increase";
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
                <Select
                  value={direction}
                  onValueChange={(v) => setValue(`items.${index}.direction`, v as "increase" | "decrease")}
                >
                  <SelectTrigger className="w-32" aria-label={`Direction for item ${index + 1}`}>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="increase">Increase</SelectItem>
                    <SelectItem value="decrease">Decrease</SelectItem>
                  </SelectContent>
                </Select>
                <div className="w-24 space-y-1">
                  <Input
                    type="number"
                    min={1}
                    inputMode="numeric"
                    placeholder="Qty"
                    error={Boolean(itemErrors?.quantity)}
                    {...register(`items.${index}.quantity`)}
                  />
                  {itemErrors?.quantity ? <p className="text-xs text-danger">{itemErrors.quantity.message}</p> : null}
                </div>
                <div className="w-40 space-y-1">
                  <Input placeholder="Reason (optional)" {...register(`items.${index}.reason`)} />
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
          onClick={() => append({ product_id: "", product_variant_id: "", direction: "increase", quantity: "", reason: "" })}
        >
          <Plus />
          Add item
        </Button>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="stocktake-form-note">Note (optional)</Label>
        <Textarea id="stocktake-form-note" rows={2} {...register("note")} />
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          Record stocktake
        </Button>
      </div>
    </form>
  );
}
