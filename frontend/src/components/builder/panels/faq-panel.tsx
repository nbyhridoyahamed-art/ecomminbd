"use client";

import { Plus, Trash2 } from "lucide-react";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { FaqItem, FaqSettings } from "@/types/homepage-block";

const MAX_ITEMS = 20;

export function FaqPanel({ value, onChange }: ContentPanelProps<FaqSettings>) {
  function addRow() {
    onChange({ ...value, items: [...value.items, { question: "", answer: "" }] });
  }

  function updateRow(index: number, patch: Partial<FaqItem>) {
    onChange({ ...value, items: value.items.map((item, i) => (i === index ? { ...item, ...patch } : item)) });
  }

  function removeRow(index: number) {
    onChange({ ...value, items: value.items.filter((_, i) => i !== index) });
  }

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="faq-heading">Heading (optional)</Label>
        <Input id="faq-heading" value={value.heading ?? ""} onChange={(event) => onChange({ ...value, heading: event.target.value || null })} />
      </div>

      <div className="space-y-2">
        <Label>Questions</Label>
        <div className="space-y-3">
          {value.items.map((item, index) => (
            <div key={index} className="flex items-start gap-2 rounded-md border border-border p-3">
              <div className="flex-1 space-y-1.5">
                <Input placeholder="Question" value={item.question} onChange={(event) => updateRow(index, { question: event.target.value })} />
                <Textarea placeholder="Answer" rows={3} value={item.answer} onChange={(event) => updateRow(index, { answer: event.target.value })} />
              </div>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Remove question"
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
          Add question
        </Button>
      </div>
    </div>
  );
}
