"use client";

import { useForm, useWatch } from "react-hook-form";

import type { ReturnRequestFormValues } from "@/hooks/use-returns";
import { variantLabel } from "@/lib/variant";
import { VariantPicker } from "@/components/catalog/variant-picker";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { OrderItem } from "@/types/order";
import type { Product } from "@/types/product";

const NO_EXCHANGE = "none";

interface FormValues {
  reason: string;
  quantities: Record<string, string>;
  exchangeProductIds: Record<string, string>;
  exchangeVariantIds: Record<string, string>;
}

interface ReturnRequestFormProps {
  items: OrderItem[];
  /** Offered as "exchange for" targets — same product with a different variant is a valid (and common) exchange. */
  products: Product[];
  onSubmit: (values: ReturnRequestFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
}

export function ReturnRequestForm({ items, products, onSubmit, isPending, serverError }: ReturnRequestFormProps) {
  const {
    register,
    handleSubmit,
    setValue,
    control,
    formState: { errors },
    setError,
    clearErrors,
  } = useForm<FormValues>({
    defaultValues: {
      reason: "",
      quantities: Object.fromEntries(items.map((item) => [String(item.id), ""])),
      exchangeProductIds: Object.fromEntries(items.map((item) => [String(item.id), NO_EXCHANGE])),
      exchangeVariantIds: Object.fromEntries(items.map((item) => [String(item.id), ""])),
    },
  });

  const exchangeProductIds = useWatch({ control, name: "exchangeProductIds" });
  const exchangeVariantIds = useWatch({ control, name: "exchangeVariantIds" });

  const submit = handleSubmit((values) => {
    clearErrors("root");

    const returnItems = Object.entries(values.quantities)
      .map(([itemId, quantity]) => {
        const exchangeProductId = values.exchangeProductIds[itemId];
        const hasExchange = Boolean(exchangeProductId) && exchangeProductId !== NO_EXCHANGE;

        return {
          order_item_id: Number(itemId),
          quantity: Number(quantity),
          exchange_product_id: hasExchange ? Number(exchangeProductId) : null,
          exchange_product_variant_id: hasExchange && values.exchangeVariantIds[itemId] ? Number(values.exchangeVariantIds[itemId]) : null,
        };
      })
      .filter((item) => item.quantity > 0);

    if (returnItems.length === 0) {
      setError("root", { message: "Enter a quantity for at least one item." });
      return;
    }

    for (const item of returnItems) {
      const orderItem = items.find((i) => i.id === item.order_item_id);
      if (orderItem && item.quantity > orderItem.quantity) {
        setError("root", { message: `Cannot return more than ${orderItem.quantity} of "${orderItem.product_name}".` });
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

      <div className="space-y-3">
        {items.map((item) => {
          const exchangeProductId = exchangeProductIds?.[String(item.id)] ?? NO_EXCHANGE;
          const exchangeProduct = products.find((p) => String(p.id) === exchangeProductId);
          const exchangeVariantId = exchangeVariantIds?.[String(item.id)] ?? "";

          return (
            <div key={item.id} className="space-y-2 rounded-lg border border-border p-3">
              <div className="flex items-center gap-3">
                <div className="flex-1">
                  <p className="text-sm font-medium text-text-primary">{item.product_name}</p>
                  <p className="text-xs text-text-muted">
                    {variantLabel(item.product_variant) ?? item.sku} &middot; {item.quantity} ordered
                  </p>
                </div>
                <Input
                  type="number"
                  min={0}
                  // See ReceivePurchaseOrderForm for why there's no `max` here.
                  inputMode="numeric"
                  placeholder="0"
                  className="w-28"
                  {...register(`quantities.${String(item.id)}`)}
                />
              </div>

              <div className="flex items-center gap-2">
                <Label className="w-24 shrink-0 text-xs font-normal text-text-muted">Exchange for</Label>
                <Select
                  value={exchangeProductId}
                  onValueChange={(v) => {
                    setValue(`exchangeProductIds.${String(item.id)}`, v);
                    setValue(`exchangeVariantIds.${String(item.id)}`, "");
                  }}
                >
                  <SelectTrigger aria-label={`Exchange ${item.product_name} for`} className="flex-1">
                    <SelectValue placeholder="No exchange (refund only)">
                      {exchangeProductId === NO_EXCHANGE ? "No exchange (refund only)" : exchangeProduct?.name}
                    </SelectValue>
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={NO_EXCHANGE}>No exchange (refund only)</SelectItem>
                    {products.map((p) => (
                      <SelectItem key={p.id} value={String(p.id)}>
                        {p.name} ({p.sku})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <VariantPicker
                  product={exchangeProduct}
                  value={exchangeVariantId}
                  onChange={(v) => setValue(`exchangeVariantIds.${String(item.id)}`, v)}
                />
              </div>
            </div>
          );
        })}
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="return-reason">Reason (optional)</Label>
        <Textarea id="return-reason" rows={2} {...register("reason")} />
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          Request return
        </Button>
      </div>
    </form>
  );
}
