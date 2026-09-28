"use client";

import { useState } from "react";
import { Plus, Trash2 } from "lucide-react";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useAllProducts } from "@/hooks/use-products";
import type { FlashSaleSettings } from "@/types/homepage-block";

const MAX_ITEMS = 12;

function toDatetimeLocalValue(iso: string): string {
  const d = new Date(iso);
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function FlashSalePanel({ value, onChange, storeId }: ContentPanelProps<FlashSaleSettings>) {
  const [search, setSearch] = useState("");
  const { data: productsData } = useAllProducts(storeId);
  const products = productsData?.data ?? [];

  const addedIds = new Set(value.items.map((item) => item.product_id));
  const atLimit = value.items.length >= MAX_ITEMS;
  const candidates = products
    .filter((product) => !addedIds.has(product.id))
    .filter((product) => product.name.toLowerCase().includes(search.toLowerCase()));

  function addItem(productId: number, defaultPrice: number) {
    onChange({ ...value, items: [...value.items, { product_id: productId, sale_price: defaultPrice }] });
  }

  function updateSalePrice(productId: number, salePrice: number) {
    onChange({
      ...value,
      items: value.items.map((item) => (item.product_id === productId ? { ...item, sale_price: salePrice } : item)),
    });
  }

  function removeItem(productId: number) {
    onChange({ ...value, items: value.items.filter((item) => item.product_id !== productId) });
  }

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="flash-sale-heading">Heading</Label>
        <Input id="flash-sale-heading" value={value.heading} onChange={(event) => onChange({ ...value, heading: event.target.value })} />
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="flash-sale-ends-at">Ends at</Label>
        <Input
          id="flash-sale-ends-at"
          type="datetime-local"
          value={toDatetimeLocalValue(value.ends_at)}
          onChange={(event) => onChange({ ...value, ends_at: new Date(event.target.value).toISOString() })}
        />
      </div>

      <div className="space-y-2">
        <Label>{`Products (${value.items.length}/${MAX_ITEMS})`}</Label>

        {value.items.length > 0 ? (
          <div className="space-y-2">
            {value.items.map((item) => {
              const product = products.find((candidate) => candidate.id === item.product_id);
              return (
                <div key={item.product_id} className="flex items-center gap-2 rounded-md border border-border p-3">
                  <span className="flex-1 truncate text-sm text-text-primary">{product?.name ?? `Product #${item.product_id}`}</span>
                  <Input
                    type="number"
                    step="0.01"
                    min="0"
                    aria-label="Sale price"
                    className="w-28"
                    value={item.sale_price}
                    onChange={(event) => updateSalePrice(item.product_id, Number(event.target.value))}
                  />
                  <Button type="button" variant="ghost" size="icon" aria-label="Remove product" onClick={() => removeItem(item.product_id)}>
                    <Trash2 className="text-danger" />
                  </Button>
                </div>
              );
            })}
          </div>
        ) : (
          <p className="text-xs text-text-muted">No products added yet.</p>
        )}

        {atLimit ? (
          <p className="text-xs text-text-muted">Limit of {MAX_ITEMS} products reached.</p>
        ) : (
          <div className="space-y-2">
            <Input placeholder="Search products to add..." value={search} onChange={(event) => setSearch(event.target.value)} />
            <div className="max-h-56 space-y-1 overflow-y-auto rounded-md border border-border p-2">
              {candidates.length === 0 ? (
                <p className="p-2 text-sm text-text-muted">No matches.</p>
              ) : (
                candidates.map((product) => (
                  <div key={product.id} className="flex items-center justify-between gap-2 rounded px-2 py-1.5 text-sm hover:bg-border/30">
                    <span className="truncate">{product.name}</span>
                    <Button type="button" variant="outline" size="sm" onClick={() => addItem(product.id, product.price)}>
                      <Plus />
                      Add
                    </Button>
                  </div>
                ))
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
