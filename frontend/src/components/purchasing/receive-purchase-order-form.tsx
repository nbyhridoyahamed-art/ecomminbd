"use client";

import { useForm } from "react-hook-form";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { PurchaseReceiptPayload } from "@/hooks/use-purchase-orders";
import type { PurchaseOrderItem } from "@/types/purchase-order";

interface FormValues {
  note: string;
  quantities: Record<string, string>;
}

interface ReceivePurchaseOrderFormProps {
  items: PurchaseOrderItem[];
  onSubmit: (values: PurchaseReceiptPayload) => void;
  isPending: boolean;
  serverError?: string | null;
}

export function ReceivePurchaseOrderForm({ items, onSubmit, isPending, serverError }: ReceivePurchaseOrderFormProps) {
  const remainingItems = items.filter((item) => item.quantity_remaining > 0);

  const {
    register,
    handleSubmit,
    formState: { errors },
    setError,
    clearErrors,
  } = useForm<FormValues>({
    defaultValues: {
      note: "",
      quantities: Object.fromEntries(remainingItems.map((item) => [String(item.id), ""])),
    },
  });

  const submit = handleSubmit((values) => {
    clearErrors("root");

    const receiptItems = Object.entries(values.quantities)
      .map(([itemId, quantity]) => ({ purchase_order_item_id: Number(itemId), quantity_received: Number(quantity) }))
      .filter((item) => item.quantity_received > 0);

    if (receiptItems.length === 0) {
      setError("root", { message: "Enter a quantity for at least one item." });
      return;
    }

    for (const item of receiptItems) {
      const orderItem = remainingItems.find((i) => i.id === item.purchase_order_item_id);
      if (orderItem && item.quantity_received > orderItem.quantity_remaining) {
        setError("root", {
          message: `Cannot receive more than ${orderItem.quantity_remaining} of "${orderItem.product_name}".`,
        });
        return;
      }
    }

    onSubmit({ note: values.note || null, items: receiptItems });
  });

  if (remainingItems.length === 0) {
    return null;
  }

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
        {remainingItems.map((item) => (
          <div key={item.id} className="flex items-center gap-3">
            <div className="flex-1">
              <p className="text-sm font-medium text-text-primary">{item.product_name}</p>
              <p className="text-xs text-text-muted">
                {item.sku} &middot; {item.quantity_remaining} remaining of {item.quantity_ordered}
              </p>
            </div>
            <Input
              type="number"
              min={0}
              // No `max` here: a native max-attribute violation blocks form
              // submission silently (no onSubmit call at all), which would
              // swallow the friendlier, product-named error our own
              // validation below produces for the same case.
              inputMode="numeric"
              placeholder="0"
              className="w-28"
              {...register(`quantities.${String(item.id)}`)}
            />
          </div>
        ))}
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="receipt-note">Note (optional)</Label>
        <Textarea id="receipt-note" rows={2} {...register("note")} />
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          Record receipt
        </Button>
      </div>
    </form>
  );
}
