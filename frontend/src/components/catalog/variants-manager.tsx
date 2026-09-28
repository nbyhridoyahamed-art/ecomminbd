"use client";

import { useMemo, useState } from "react";
import { Boxes, PackageSearch, Plus, Save, Trash2 } from "lucide-react";

import { useAttributes } from "@/hooks/use-attributes";
import { useDeleteVariant, useGenerateVariants, useUpdateVariant } from "@/hooks/use-product-variants";
import { formatMoney } from "@/lib/money";
import { VariantStockAdjustmentDialog } from "@/components/catalog/variant-stock-adjustment-dialog";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { ProductVariant } from "@/types/product";

interface VariantsManagerProps {
  storeId: number;
  productId: number;
  currencyCode: string;
  basePrice: number;
  variants: ProductVariant[];
  canEdit: boolean;
}

export function VariantsManager({ storeId, productId, currencyCode, basePrice, variants, canEdit }: VariantsManagerProps) {
  const { data: attributes } = useAttributes(storeId);
  // Values the user has actively toggled this session — merged below with
  // whichever values existing variants already use, so re-opening the page
  // shows those pre-checked without needing to sync state from an effect.
  const [toggledValueIds, setToggledValueIds] = useState<Set<number>>(new Set());
  const [variantToDelete, setVariantToDelete] = useState<ProductVariant | null>(null);
  const [variantToAdjust, setVariantToAdjust] = useState<ProductVariant | null>(null);

  const usedValueIds = useMemo(() => {
    const used = new Set<number>();
    variants.forEach((variant) => variant.attribute_values.forEach((av) => used.add(av.value_id)));
    return used;
  }, [variants]);

  const selectedValueIds = useMemo(
    () => new Set([...usedValueIds, ...toggledValueIds]),
    [usedValueIds, toggledValueIds],
  );

  const generate = useGenerateVariants(productId);
  const deleteVariant = useDeleteVariant(productId);

  const toggleValue = (id: number) => {
    setToggledValueIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  return (
    <div className="space-y-6">
      <div className="space-y-3">
        <p className="text-sm font-medium text-text-primary">Select attribute values</p>
        {(attributes ?? []).length === 0 ? (
          <p className="text-sm text-text-muted">
            No attributes yet — create one under Catalog &rarr; Attributes first.
          </p>
        ) : (
          <div className="space-y-3">
            {(attributes ?? []).map((attribute) => (
              <div key={attribute.id}>
                <Label className="text-xs uppercase tracking-wide text-text-muted">{attribute.name}</Label>
                <div className="mt-1 flex flex-wrap gap-3">
                  {attribute.values.map((value) => (
                    <label key={value.id} className="flex items-center gap-1.5 text-sm text-text-primary">
                      <Checkbox
                        checked={selectedValueIds.has(value.id)}
                        onCheckedChange={() => toggleValue(value.id)}
                        disabled={!canEdit}
                      />
                      {value.value}
                    </label>
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}
        {canEdit ? (
          <Button
            size="sm"
            onClick={() => generate.mutate([...selectedValueIds])}
            loading={generate.isPending}
            disabled={selectedValueIds.size === 0}
          >
            <Plus />
            Generate variants
          </Button>
        ) : null}
      </div>

      {variants.length === 0 ? (
        <EmptyState
          icon={<Boxes />}
          title="No variants yet"
          description="Select values above and generate variants to create them."
        />
      ) : (
        <div className="overflow-x-auto rounded-lg border border-border">
          <table className="w-full text-left text-table">
            <thead className="border-b border-border">
              <tr>
                <th className="px-3 py-2 font-medium text-text-secondary">Variant</th>
                <th className="px-3 py-2 font-medium text-text-secondary">SKU</th>
                <th className="px-3 py-2 font-medium text-text-secondary">Price</th>
                <th className="px-3 py-2 font-medium text-text-secondary">Stock</th>
                <th className="px-3 py-2 font-medium text-text-secondary">Status</th>
                <th className="px-3 py-2" />
              </tr>
            </thead>
            <tbody>
              {variants.map((variant) => (
                <VariantRow
                  key={variant.id}
                  productId={productId}
                  variant={variant}
                  currencyCode={currencyCode}
                  basePrice={basePrice}
                  canEdit={canEdit}
                  onRequestDelete={() => setVariantToDelete(variant)}
                  onRequestAdjustStock={() => setVariantToAdjust(variant)}
                />
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Dialog open={Boolean(variantToDelete)} onOpenChange={(open) => !open && setVariantToDelete(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delete variant</DialogTitle>
            <DialogDescription>This can&apos;t be undone.</DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setVariantToDelete(null)}>
              Cancel
            </Button>
            <Button
              variant="destructive"
              loading={deleteVariant.isPending}
              onClick={() => {
                if (variantToDelete) {
                  deleteVariant.mutate(variantToDelete.id, { onSuccess: () => setVariantToDelete(null) });
                }
              }}
            >
              Delete
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <VariantStockAdjustmentDialog
        productId={productId}
        storeId={storeId}
        variant={variantToAdjust}
        onClose={() => setVariantToAdjust(null)}
      />
    </div>
  );
}

interface VariantRowProps {
  productId: number;
  variant: ProductVariant;
  currencyCode: string;
  basePrice: number;
  canEdit: boolean;
  onRequestDelete: () => void;
  onRequestAdjustStock: () => void;
}

function VariantRow({
  productId,
  variant,
  currencyCode,
  basePrice,
  canEdit,
  onRequestDelete,
  onRequestAdjustStock,
}: VariantRowProps) {
  const [sku, setSku] = useState(variant.sku);
  const [price, setPrice] = useState(variant.price !== null ? String(variant.price) : "");
  const [status, setStatus] = useState(variant.status);
  const updateVariant = useUpdateVariant(productId);

  const dirty = sku !== variant.sku || price !== (variant.price !== null ? String(variant.price) : "") || status !== variant.status;

  const label = variant.attribute_values.map((av) => av.value).join(" / ") || variant.sku;

  const save = () => {
    updateVariant.mutate({ variantId: variant.id, sku, price: price || null, status });
  };

  return (
    <tr className="border-b border-border last:border-0">
      <td className="px-3 py-2 text-text-primary">{label}</td>
      <td className="px-3 py-2">
        <Input
          value={sku}
          onChange={(e) => setSku(e.target.value)}
          disabled={!canEdit}
          className="h-8 w-40"
        />
      </td>
      <td className="px-3 py-2">
        <Input
          value={price}
          onChange={(e) => setPrice(e.target.value)}
          disabled={!canEdit}
          inputMode="decimal"
          placeholder={`Uses ${formatMoney(basePrice, currencyCode)}`}
          className="h-8 w-36"
        />
      </td>
      <td className="px-3 py-2 text-text-primary">
        {variant.stock_summary ? (
          <div>
            <p>{variant.stock_summary.total_available} available</p>
            <p className="text-xs text-text-muted">
              {variant.stock_summary.total_quantity} on hand
              {variant.stock_summary.total_reserved > 0 ? `, ${variant.stock_summary.total_reserved} reserved` : ""}
            </p>
          </div>
        ) : (
          <span className="text-text-muted">—</span>
        )}
      </td>
      <td className="px-3 py-2">
        <Select value={status} onValueChange={(v) => setStatus(v as "active" | "inactive")}>
          <SelectTrigger className="h-8 w-28" disabled={!canEdit}>
            <SelectValue>{status === "active" ? "Active" : "Inactive"}</SelectValue>
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="inactive">Inactive</SelectItem>
          </SelectContent>
        </Select>
      </td>
      <td className="px-3 py-2 text-right">
        <div className="flex justify-end gap-1">
          {canEdit && dirty ? (
            <Button size="icon" variant="ghost" onClick={save} loading={updateVariant.isPending} aria-label="Save variant">
              <Save className="text-success" />
            </Button>
          ) : null}
          {canEdit ? (
            <Button size="icon" variant="ghost" onClick={onRequestAdjustStock} aria-label="Adjust stock">
              <PackageSearch />
            </Button>
          ) : null}
          {canEdit ? (
            <Button size="icon" variant="ghost" onClick={onRequestDelete} aria-label="Delete variant">
              <Trash2 className="text-danger" />
            </Button>
          ) : null}
        </div>
      </td>
    </tr>
  );
}
