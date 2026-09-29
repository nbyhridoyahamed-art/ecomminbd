"use client";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { ImageTextSettings } from "@/types/homepage-block";

export function ImageTextPanel({ value, onChange }: ContentPanelProps<ImageTextSettings>) {
  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="image-text-image">Image URL</Label>
        <Input
          id="image-text-image"
          value={value.image_url ?? ""}
          placeholder="https://..."
          onChange={(event) => onChange({ ...value, image_url: event.target.value || null })}
        />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="image-text-heading">Heading</Label>
        <Input id="image-text-heading" value={value.heading} onChange={(event) => onChange({ ...value, heading: event.target.value })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="image-text-body">Body</Label>
        <Textarea id="image-text-body" rows={4} value={value.body} onChange={(event) => onChange({ ...value, body: event.target.value })} />
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label htmlFor="image-text-cta-label">Button label</Label>
          <Input
            id="image-text-cta-label"
            value={value.cta_label ?? ""}
            onChange={(event) => onChange({ ...value, cta_label: event.target.value || null })}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="image-text-cta-url">Button link</Label>
          <Input
            id="image-text-cta-url"
            value={value.cta_url ?? ""}
            onChange={(event) => onChange({ ...value, cta_url: event.target.value || null })}
          />
        </div>
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="image-text-panel-image-position">Image position</Label>
        <Select value={value.image_position} onValueChange={(position) => onChange({ ...value, image_position: position as "left" | "right" })}>
          <SelectTrigger className="max-w-xs" id="image-text-panel-image-position">
            <SelectValue>{value.image_position === "right" ? "Right" : "Left"}</SelectValue>
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="left">Left</SelectItem>
            <SelectItem value="right">Right</SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>
  );
}
