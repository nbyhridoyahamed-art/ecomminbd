"use client";

import { useForm } from "react-hook-form";

import type { PurchaseReturnRequestFormValues } from "@/hooks/use-purchase-returns";
import { variantLabel } from "@/lib/variant";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { PurchaseOrderItem } from "@/types/purchase-order";

interface FormValues {
  reason: string;
  quantities: Record<string, string>;
}

interface PurchaseReturnRequestFormProps {
  items: PurchaseOrderItem[];
  onSubmit: (values: PurchaseReturnRequestFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
}

export function PurchaseReturnRequestForm({ items, onSubmit, isPending, serverError }: PurchaseReturnRequestFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
    setError,
    clearErrors,
  } = useForm<FormValues>({
    defaultValues: {
      reason: "",
      quantities: Object.fromEntries(items.map((item) => [String(item.id), ""])),
    },
  });

  const submit = handleSubmit((values) => {
    clearErrors("root");

    const returnItems = Object.entries(values.quantities)
      .map(([itemId, quantity]) => ({ purchase_order_item_id: Number(itemId), quantity: Number(quantity) }))
      .filter((item) => item.quantity > 0);

    if (returnItems.length === 0) {
      setError("root", { message: "Enter a quantity for at least one item." });
      return;
    }

    for (const item of returnItems) {
      const orderItem = items.find((i) => i.id === item.purchase_order_item_id);
      if (orderItem && item.quantity > orderItem.quantity_received) {
        setError("root", { message: `Cannot return more than ${orderItem.quantity_received} of "${orderItem.product_name}".` });
        return;
      }
    }

    onSubmit({ reason: values.reason || null, items: returnItems });
  });

  return (
    <form onSubmit={submit} className="space-y-4">
      {serverError ? (
        <Alert variant="danger">
          <AlertDescription>{serverError}</AlertDescription>
        </Alert>
      ) : null}
      {errors.root ? (
        <Alert variant="danger">
          <AlertDescription>{errors.root.message}</AlertDescription>
        </Alert>
      ) : null}

      <div className="space-y-2">
        {items.map((item) => (
          <div key={item.id} className="flex items-center gap-3">
            <div className="flex-1">
              <p className="text-sm font-medium text-text-primary">{item.product_name}</p>
              <p className="text-xs text-text-muted">
                {variantLabel(item.product_variant) ?? item.sku} &middot; {item.quantity_received} received
              </p>
            </div>
            <Input
              type="number"
              min={0}
              max={item.quantity_received}
              inputMode="numeric"
              placeholder="0"
              className="w-28"
              {...register(`quantities.${String(item.id)}`)}
            />
          </div>
        ))}
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="purchase-return-reason">Reason (optional)</Label>
        <Textarea id="purchase-return-reason" rows={2} {...register("reason")} />
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          Request return
        </Button>
      </div>
    </form>
  );
}
