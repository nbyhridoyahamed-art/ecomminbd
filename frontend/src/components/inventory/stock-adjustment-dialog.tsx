"use client";

import { useState } from "react";

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
import { useCreateStockAdjustment } from "@/hooks/use-inventory";
import type { StockLevel } from "@/types/inventory";

const DIRECTION_LABELS: Record<string, string> = { increase: "Increase", decrease: "Decrease" };

interface StockAdjustmentDialogProps {
  product: StockLevel | null;
  warehouseId: number;
  onClose: () => void;
}

export function StockAdjustmentDialog({ product, warehouseId, onClose }: StockAdjustmentDialogProps) {
  return (
    <Dialog open={Boolean(product)} onOpenChange={(open) => !open && onClose()}>
      <DialogContent>
        {product ? (
          // Keying on the product resets all local form state when a
          // different row's dialog opens, without needing an effect.
          <StockAdjustmentDialogBody
            key={product.product_id}
            product={product}
            warehouseId={warehouseId}
            onClose={onClose}
          />
        ) : null}
      </DialogContent>
    </Dialog>
  );
}

interface StockAdjustmentDialogBodyProps {
  product: StockLevel;
  warehouseId: number;
  onClose: () => void;
}

function StockAdjustmentDialogBody({ product, warehouseId, onClose }: StockAdjustmentDialogBodyProps) {
  const [direction, setDirection] = useState<"increase" | "decrease">("increase");
  const [quantity, setQuantity] = useState("");
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const adjustment = useCreateStockAdjustment();

  const handleSubmit = () => {
    const parsedQuantity = Number(quantity);
    if (!parsedQuantity || parsedQuantity < 1) {
      setError("Enter a quantity of at least 1.");
      return;
    }

    adjustment.mutate(
      {
        product_id: product.product_id,
        warehouse_id: warehouseId,
        direction,
        quantity: parsedQuantity,
        reason: reason || null,
      },
      { onSuccess: onClose },
    );
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle>Adjust stock</DialogTitle>
        <DialogDescription>
          {product.product_name} ({product.sku}) — currently {product.quantity} on hand.
        </DialogDescription>
      </DialogHeader>

      <div className="space-y-4">
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label>Direction</Label>
            <Select value={direction} onValueChange={(value) => setDirection(value as "increase" | "decrease")}>
              <SelectTrigger>
                <SelectValue>{DIRECTION_LABELS[direction]}</SelectValue>
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="increase">Increase</SelectItem>
                <SelectItem value="decrease">Decrease</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="adjustment-quantity">Quantity</Label>
            <Input
              id="adjustment-quantity"
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
          <Label htmlFor="adjustment-reason">Reason (optional)</Label>
          <Textarea
            id="adjustment-reason"
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
