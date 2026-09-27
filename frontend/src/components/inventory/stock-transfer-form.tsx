"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useFieldArray, useForm, useWatch } from "react-hook-form";
import { Plus, Trash2 } from "lucide-react";
import { z } from "zod";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { StockTransferPayload } from "@/hooks/use-inventory";
import type { Product } from "@/types/product";
import type { Warehouse } from "@/types/warehouse";

const transferSchema = z
  .object({
    from_warehouse_id: z.string().min(1, "Select a source warehouse."),
    to_warehouse_id: z.string().min(1, "Select a destination warehouse."),
    note: z.string(),
    items: z
      .array(
        z.object({
          product_id: z.string().min(1, "Select a product."),
          quantity: z
            .string()
            .min(1, "Required.")
            .refine((v) => Number.isInteger(Number(v)) && Number(v) >= 1, "Enter a quantity of at least 1."),
        }),
      )
      .min(1, "Add at least one product."),
  })
  .refine((data) => data.from_warehouse_id !== data.to_warehouse_id || !data.from_warehouse_id, {
    message: "Source and destination warehouses must differ.",
    path: ["to_warehouse_id"],
  });

type FormValues = z.infer<typeof transferSchema>;

interface StockTransferFormProps {
  storeId: number;
  warehouses: Warehouse[];
  products: Product[];
  onSubmit: (values: StockTransferPayload) => void;
  isPending: boolean;
  serverError?: string | null;
}

export function StockTransferForm({
  storeId,
  warehouses,
  products,
  onSubmit,
  isPending,
  serverError,
}: StockTransferFormProps) {
  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(transferSchema),
    defaultValues: {
      from_warehouse_id: "",
      to_warehouse_id: "",
      note: "",
      items: [{ product_id: "", quantity: "" }],
    },
  });

  const { fields, append, remove } = useFieldArray({ control, name: "items" });
  const fromWarehouseId = useWatch({ control, name: "from_warehouse_id" });
  const toWarehouseId = useWatch({ control, name: "to_warehouse_id" });
  const items = useWatch({ control, name: "items" });

  const submit = handleSubmit((values) => {
    onSubmit({
      store_id: storeId,
      from_warehouse_id: Number(values.from_warehouse_id),
      to_warehouse_id: Number(values.to_warehouse_id),
      note: values.note || null,
      items: values.items.map((item) => ({ product_id: Number(item.product_id), quantity: Number(item.quantity) })),
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
          <Label>From warehouse</Label>
          <Select value={fromWarehouseId} onValueChange={(v) => setValue("from_warehouse_id", v, { shouldValidate: true })}>
            <SelectTrigger>
              <SelectValue placeholder="Select warehouse">
                {warehouses.find((w) => String(w.id) === fromWarehouseId)?.name}
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
          {errors.from_warehouse_id ? <p className="text-xs text-danger">{errors.from_warehouse_id.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <Label>To warehouse</Label>
          <Select value={toWarehouseId} onValueChange={(v) => setValue("to_warehouse_id", v, { shouldValidate: true })}>
            <SelectTrigger>
              <SelectValue placeholder="Select warehouse">
                {warehouses.find((w) => String(w.id) === toWarehouseId)?.name}
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
          {errors.to_warehouse_id ? <p className="text-xs text-danger">{errors.to_warehouse_id.message}</p> : null}
        </div>
      </div>

      <div className="space-y-2">
        <Label>Items</Label>
        <div className="space-y-2">
          {fields.map((field, index) => {
            const itemErrors = errors.items?.[index];
            const selectedProductId = items?.[index]?.product_id;
            return (
              <div key={field.id} className="flex items-start gap-2">
                <div className="flex-1 space-y-1">
                  <Select
                    value={selectedProductId}
                    onValueChange={(v) => setValue(`items.${index}.product_id`, v, { shouldValidate: true })}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select product">
                        {products.find((p) => String(p.id) === selectedProductId)?.name}
                      </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                      {products.map((p) => (
                        <SelectItem key={p.id} value={String(p.id)}>
                          {p.name} ({p.sku})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {itemErrors?.product_id ? <p className="text-xs text-danger">{itemErrors.product_id.message}</p> : null}
                </div>
                <div className="w-28 space-y-1">
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
        <Button type="button" variant="outline" size="sm" onClick={() => append({ product_id: "", quantity: "" })}>
          <Plus />
          Add item
        </Button>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="note">Note (optional)</Label>
        <Textarea id="note" rows={2} {...register("note")} />
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          Complete transfer
        </Button>
      </div>
    </form>
  );
}
