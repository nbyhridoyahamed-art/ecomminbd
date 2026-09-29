"use client";

import { useState } from "react";

import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

export interface PickerOption {
  id: number;
  label: string;
}

interface AutoManualPickerProps {
  mode: "auto" | "manual";
  onModeChange: (mode: "auto" | "manual") => void;
  selectedIds: number[];
  onSelectedIdsChange: (ids: number[]) => void;
  options: PickerOption[];
  autoDescription: string;
}

/**
 * The shared "auto vs. hand-picked" selector every auto/manual block type
 * uses (category_grid, featured_products, brand_carousel, testimonials,
 * reviews) — a search-filtered checkbox list, the same pattern
 * VariantsManager already established for attribute-value selection,
 * rather than a new searchable-combobox primitive.
 */
export function AutoManualPicker({ mode, onModeChange, selectedIds, onSelectedIdsChange, options, autoDescription }: AutoManualPickerProps) {
  const [search, setSearch] = useState("");
  const filtered = options.filter((option) => option.label.toLowerCase().includes(search.toLowerCase()));

  function toggle(id: number) {
    onSelectedIdsChange(selectedIds.includes(id) ? selectedIds.filter((existing) => existing !== id) : [...selectedIds, id]);
  }

  return (
    <div className="space-y-3">
      <div className="space-y-1.5">
        <Label htmlFor="auto-manual-picker-source">Source</Label>
        <Select value={mode} onValueChange={(value) => onModeChange(value as "auto" | "manual")}>
          <SelectTrigger className="max-w-xs" id="auto-manual-picker-source">
            <SelectValue>{mode === "auto" ? "Automatic" : "Hand-picked"}</SelectValue>
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="auto">Automatic</SelectItem>
            <SelectItem value="manual">Hand-picked</SelectItem>
          </SelectContent>
        </Select>
        {mode === "auto" ? <p className="text-xs text-text-muted">{autoDescription}</p> : null}
      </div>

      {mode === "manual" ? (
        <div className="space-y-2">
          <Input placeholder="Search..." value={search} onChange={(event) => setSearch(event.target.value)} />
          <div className="max-h-56 space-y-1 overflow-y-auto rounded-md border border-border p-2">
            {filtered.length === 0 ? (
              <p className="p-2 text-sm text-text-muted">No matches.</p>
            ) : (
              filtered.map((option) => (
                <label key={option.id} className="flex items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-border/30">
                  <Checkbox checked={selectedIds.includes(option.id)} onCheckedChange={() => toggle(option.id)} />
                  {option.label}
                </label>
              ))
            )}
          </div>
          <p className="text-xs text-text-muted">{selectedIds.length} selected — shown in the order picked.</p>
        </div>
      ) : null}
    </div>
  );
}
