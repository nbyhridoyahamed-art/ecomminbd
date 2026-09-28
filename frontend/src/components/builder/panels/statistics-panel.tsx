"use client";

import { Plus, Trash2 } from "lucide-react";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { StatisticsSettings, ValueLabelItem } from "@/types/homepage-block";

const MAX_ITEMS = 8;

export function StatisticsPanel({ value, onChange }: ContentPanelProps<StatisticsSettings>) {
  function addRow() {
    onChange({ ...value, items: [...value.items, { value: "", label: "" }] });
  }

  function updateRow(index: number, patch: Partial<ValueLabelItem>) {
    onChange({ ...value, items: value.items.map((item, i) => (i === index ? { ...item, ...patch } : item)) });
  }

  function removeRow(index: number) {
    onChange({ ...value, items: value.items.filter((_, i) => i !== index) });
  }

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="statistics-heading">Heading (optional)</Label>
        <Input
          id="statistics-heading"
          value={value.heading ?? ""}
          onChange={(event) => onChange({ ...value, heading: event.target.value || null })}
        />
      </div>

      <div className="space-y-2">
        <Label>Stats</Label>
        <div className="space-y-3">
          {value.items.map((item, index) => (
            <div key={index} className="flex items-start gap-2 rounded-md border border-border p-3">
              <div className="flex-1 space-y-1.5">
                <Input placeholder="Value (e.g. 10,000+)" value={item.value} onChange={(event) => updateRow(index, { value: event.target.value })} />
                <Input placeholder="Label" value={item.label} onChange={(event) => updateRow(index, { label: event.target.value })} />
              </div>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Remove stat"
                disabled={value.items.length <= 1}
                onClick={() => removeRow(index)}
              >
                <Trash2 className="text-danger" />
              </Button>
            </div>
          ))}
        </div>
        <Button type="button" variant="outline" size="sm" disabled={value.items.length >= MAX_ITEMS} onClick={addRow}>
          <Plus />
          Add stat
        </Button>
      </div>
    </div>
  );
}
