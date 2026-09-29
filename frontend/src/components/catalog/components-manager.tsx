"use client";

import { useState } from "react";
import { Package, Plus, Save, Trash2 } from "lucide-react";

import { useAddComponent, useDeleteComponent, useUpdateComponent } from "@/hooks/use-bundle-components";
import { useAllProducts } from "@/hooks/use-products";
import { VariantPicker } from "@/components/catalog/variant-picker";
import { Button } from "@/components/ui/button";
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { BundleAvailability, BundleComponent } from "@/types/product";

interface ComponentsManagerProps {
  storeId: number;
  bundleProductId: number;
  components: BundleComponent[];
  bundleAvailability?: BundleAvailability;
  canEdit: boolean;
}

export function ComponentsManager({ storeId, bundleProductId, components, bundleAvailability, canEdit }: ComponentsManagerProps) {
  const { data: productsData } = useAllProducts(storeId);
  const products = (productsData?.data ?? []).filter((p) => p.type !== "bundle" && p.id !== bundleProductId);

  const [productId, setProductId] = useState("");
  const [variantId, setVariantId] = useState("");
  const [quantity, setQuantity] = useState("1");
  const [componentToDelete, setComponentToDelete] = useState<BundleComponent | null>(null);

  const selectedProduct = products.find((p) => String(p.id) === productId);
  const addComponent = useAddComponent(bundleProductId);
  const deleteComponent = useDeleteComponent(bundleProductId);

  const add = () => {
    addComponent.mutate(
      {
        product_id: Number(productId),
        product_variant_id: variantId ? Number(variantId) : null,
        quantity: Number(quantity),
      },
      {
        onSuccess: () => {
          setProductId("");
          setVariantId("");
          setQuantity("1");
        },
      },
    );
  };

  return (
    <div className="space-y-6">
      {bundleAvailability ? (
        <div className="rounded-lg border border-border bg-surface-secondary p-3 text-sm">
          <p className="font-medium text-text-primary">
            {bundleAvailability.total_available} available to sell across all warehouses
          </p>
          {bundleAvailability.by_warehouse.length > 0 ? (
            <ul className="mt-1 space-y-0.5 text-xs text-text-muted">
              {bundleAvailability.by_warehouse.map((row) => (
                <li key={row.warehouse_id}>
                  {row.warehouse_name}: {row.available}
                </li>
              ))}
            </ul>
          ) : (
            <p className="mt-1 text-xs text-text-muted">Add components with stock to make this bundle sellable.</p>
          )}
        </div>
      ) : null}

      {canEdit ? (
        <div className="flex items-start gap-2">
          <div className="flex-1 space-y-1">
            <Select
              value={productId}
              onValueChange={(value) => {
                setProductId(value);
                setVariantId("");
              }}
            >
              <SelectTrigger aria-label="Select product">
                <SelectValue placeholder="Select product">{selectedProduct?.name}</SelectValue>
              </SelectTrigger>
              <SelectContent>
                {products.map((p) => (
                  <SelectItem key={p.id} value={String(p.id)}>
                    {p.name} ({p.sku})
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <VariantPicker product={selectedProduct} value={variantId} onChange={setVariantId} />
          <div className="w-24">
            <Input
              type="number"
              min={1}
              inputMode="numeric"
              placeholder="Qty"
              value={quantity}
              onChange={(e) => setQuantity(e.target.value)}
            />
          </div>
          <Button
            type="button"
            onClick={add}
            loading={addComponent.isPending}
            disabled={!productId || !quantity}
          >
            <Plus />
            Add
          </Button>
        </div>
      ) : null}

      {components.length === 0 ? (
        <EmptyState
          icon={<Package />}
          title="No components yet"
          description="Add the products this bundle is made of above."
        />
      ) : (
        <div className="overflow-x-auto rounded-lg border border-border">
          <table className="w-full text-left text-table">
            <thead className="border-b border-border">
              <tr>
                <th className="px-3 py-2 font-medium text-text-secondary">Component</th>
                <th className="px-3 py-2 font-medium text-text-secondary">Quantity per bundle</th>
                <th className="px-3 py-2" />
              </tr>
            </thead>
            <tbody>
              {components.map((component) => (
                <ComponentRow
                  key={component.id}
                  bundleProductId={bundleProductId}
                  component={component}
                  canEdit={canEdit}
                  onRequestDelete={() => setComponentToDelete(component)}
                />
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Dialog open={Boolean(componentToDelete)} onOpenChange={(open) => !open && setComponentToDelete(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Remove component</DialogTitle>
            <DialogDescription>This can&apos;t be undone.</DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setComponentToDelete(null)}>
              Cancel
            </Button>
            <Button
              variant="destructive"
              loading={deleteComponent.isPending}
              onClick={() => {
                if (componentToDelete) {
                  deleteComponent.mutate(componentToDelete.id, { onSuccess: () => setComponentToDelete(null) });
                }
              }}
            >
              Remove
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

interface ComponentRowProps {
  bundleProductId: number;
  component: BundleComponent;
  canEdit: boolean;
  onRequestDelete: () => void;
}

function ComponentRow({ bundleProductId, component, canEdit, onRequestDelete }: ComponentRowProps) {
  const [quantity, setQuantity] = useState(String(component.quantity));
  const updateComponent = useUpdateComponent(bundleProductId);

  const dirty = quantity !== String(component.quantity);
  const label = `${component.product_name} (${component.product_sku}${component.product_variant_sku ? ` — ${component.product_variant_sku}` : ""})`;

  return (
    <tr className="border-b border-border last:border-0">
      <td className="px-3 py-2 text-text-primary">{label}</td>
      <td className="px-3 py-2">
        <Input
          type="number"
          min={1}
          inputMode="numeric"
          value={quantity}
          onChange={(e) => setQuantity(e.target.value)}
          disabled={!canEdit}
          className="h-8 w-24"
        />
      </td>
      <td className="px-3 py-2 text-right">
        <div className="flex justify-end gap-1">
          {canEdit && dirty ? (
            <Button
              size="icon"
              variant="ghost"
              aria-label="Save component"
              loading={updateComponent.isPending}
              onClick={() => updateComponent.mutate({ componentId: component.id, quantity: Number(quantity) })}
            >
              <Save className="text-success" />
            </Button>
          ) : null}
          {canEdit ? (
            <Button size="icon" variant="ghost" aria-label="Remove component" onClick={onRequestDelete}>
              <Trash2 className="text-danger" />
            </Button>
          ) : null}
        </div>
      </td>
    </tr>
  );
}
