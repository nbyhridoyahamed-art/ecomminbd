"use client";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { CtaSettings } from "@/types/homepage-block";

export function CtaPanel({ value, onChange }: ContentPanelProps<CtaSettings>) {
  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="cta-heading">Heading</Label>
        <Input id="cta-heading" value={value.heading} onChange={(event) => onChange({ ...value, heading: event.target.value })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="cta-subheading">Subheading</Label>
        <Textarea
          id="cta-subheading"
          rows={2}
          value={value.subheading ?? ""}
          onChange={(event) => onChange({ ...value, subheading: event.target.value || null })}
        />
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label htmlFor="cta-label">Button label</Label>
          <Input id="cta-label" value={value.cta_label} onChange={(event) => onChange({ ...value, cta_label: event.target.value })} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="cta-url">Button link</Label>
          <Input id="cta-url" value={value.cta_url} onChange={(event) => onChange({ ...value, cta_url: event.target.value })} />
        </div>
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="cta-background-image">Background image URL</Label>
        <Input
          id="cta-background-image"
          value={value.background_image_url ?? ""}
          placeholder="https://..."
          onChange={(event) => onChange({ ...value, background_image_url: event.target.value || null })}
        />
      </div>
    </div>
  );
}
