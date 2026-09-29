"use client";

import { useState } from "react";

import { useCreateStockAdjustment } from "@/hooks/use-inventory";
import { useAllWarehouses } from "@/hooks/use-warehouses";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { ProductVariant } from "@/types/product";

const DIRECTION_LABELS: Record<string, string> = { increase: "Increase", decrease: "Decrease" };

interface VariantStockAdjustmentDialogProps {
  productId: number;
  storeId: number;
  variant: ProductVariant | null;
  onClose: () => void;
}

export function VariantStockAdjustmentDialog({
  productId,
  storeId,
  variant,
  onClose,
}: VariantStockAdjustmentDialogProps) {
  return (
    <Dialog open={Boolean(variant)} onOpenChange={(open) => !open && onClose()}>
      <DialogContent>
        {variant ? (
          // Keying on the variant resets all local form state when a
          // different row's dialog opens, without needing an effect.
          <VariantStockAdjustmentDialogBody
            key={variant.id}
            productId={productId}
            storeId={storeId}
            variant={variant}
            onClose={onClose}
          />
        ) : null}
      </DialogContent>
    </Dialog>
  );
}

interface VariantStockAdjustmentDialogBodyProps {
  productId: number;
  storeId: number;
  variant: ProductVariant;
  onClose: () => void;
}

function VariantStockAdjustmentDialogBody({
  productId,
  storeId,
  variant,
  onClose,
}: VariantStockAdjustmentDialogBodyProps) {
  const { data: warehousesData } = useAllWarehouses(storeId);
  const warehouses = warehousesData?.data ?? [];

  const [warehouseId, setWarehouseId] = useState("");
  const [direction, setDirection] = useState<"increase" | "decrease">("increase");
  const [quantity, setQuantity] = useState("");
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const adjustment = useCreateStockAdjustment();

  const handleSubmit = () => {
    if (!warehouseId) {
      setError("Select a warehouse.");
      return;
    }

    const parsedQuantity = Number(quantity);
    if (!parsedQuantity || parsedQuantity < 1) {
      setError("Enter a quantity of at least 1.");
      return;
    }

    adjustment.mutate(
      {
        product_id: productId,
        product_variant_id: variant.id,
        warehouse_id: Number(warehouseId),
        direction,
        quantity: parsedQuantity,
        reason: reason || null,
      },
      { onSuccess: onClose },
    );
  };

  const label = variant.attribute_values.map((av) => av.value).join(" / ") || variant.sku;

  return (
    <>
      <DialogHeader>
        <DialogTitle>Adjust stock</DialogTitle>
        <DialogDescription>
          {label} ({variant.sku})
        </DialogDescription>
      </DialogHeader>

      <div className="space-y-4">
        <div className="space-y-1.5">
          <Label htmlFor="variant-stock-adjustment-dialog-warehouse">Warehouse</Label>
          <Select value={warehouseId} onValueChange={setWarehouseId}>
            <SelectTrigger id="variant-stock-adjustment-dialog-warehouse">
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
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label htmlFor="variant-stock-adjustment-dialog-direction">Direction</Label>
            <Select value={direction} onValueChange={(value) => setDirection(value as "increase" | "decrease")}>
              <SelectTrigger id="variant-stock-adjustment-dialog-direction">
                <SelectValue>{DIRECTION_LABELS[direction]}</SelectValue>
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="increase">Increase</SelectItem>
                <SelectItem value="decrease">Decrease</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="variant-adjustment-quantity">Quantity</Label>
            <Input
              id="variant-adjustment-quantity"
              type="number"
              min={1}
              inputMode="numeric"
              value={quantity}
              onChange={(event) => setQuantity(event.target.value)}
              error={Boolean(error)}
            />
          </div>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="variant-adjustment-reason">Reason (optional)</Label>
          <Textarea
            id="variant-adjustment-reason"
            rows={2}
            placeholder="e.g. Stocktake correction, damaged goods, initial stock"
            value={reason}
            onChange={(event) => setReason(event.target.value)}
          />
        </div>

        {error ? <p className="text-xs text-danger">{error}</p> : null}
      </div>

      <DialogFooter>
        <Button variant="outline" onClick={onClose}>
          Cancel
        </Button>
        <Button loading={adjustment.isPending} onClick={handleSubmit}>
          Save adjustment
        </Button>
      </DialogFooter>
    </>
  );
}
