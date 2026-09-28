"use client";

import { Plus, Trash2 } from "lucide-react";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { GalleryImage, GallerySettings } from "@/types/homepage-block";

const MAX_IMAGES = 24;

export function GalleryPanel({ value, onChange }: ContentPanelProps<GallerySettings>) {
  function addRow() {
    onChange({ ...value, images: [...value.images, { image_url: "", alt_text: null }] });
  }

  function updateRow(index: number, patch: Partial<GalleryImage>) {
    onChange({ ...value, images: value.images.map((image, i) => (i === index ? { ...image, ...patch } : image)) });
  }

  function removeRow(index: number) {
    onChange({ ...value, images: value.images.filter((_, i) => i !== index) });
  }

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="gallery-heading">Heading (optional)</Label>
        <Input id="gallery-heading" value={value.heading ?? ""} onChange={(event) => onChange({ ...value, heading: event.target.value || null })} />
      </div>

      <div className="space-y-2">
        <Label>Images</Label>
        <div className="space-y-3">
          {value.images.map((image, index) => (
            <div key={index} className="flex items-start gap-2 rounded-md border border-border p-3">
              <div className="flex-1 space-y-1.5">
                <Input placeholder="https://..." value={image.image_url} onChange={(event) => updateRow(index, { image_url: event.target.value })} />
                <Input
                  placeholder="Alt text"
                  value={image.alt_text ?? ""}
                  onChange={(event) => updateRow(index, { alt_text: event.target.value || null })}
                />
              </div>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Remove image"
                disabled={value.images.length <= 1}
                onClick={() => removeRow(index)}
              >
                <Trash2 className="text-danger" />
              </Button>
            </div>
          ))}
        </div>
        <Button type="button" variant="outline" size="sm" disabled={value.images.length >= MAX_IMAGES} onClick={addRow}>
          <Plus />
          Add image
        </Button>
      </div>
    </div>
  );
}
