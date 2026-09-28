"use client";

import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { HeroSettings } from "@/types/homepage-block";
import type { ContentPanelProps } from "@/components/builder/panel-types";

export function HeroPanel({ value, onChange }: ContentPanelProps<HeroSettings>) {
  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="hero-heading">Heading</Label>
        <Input id="hero-heading" value={value.heading} onChange={(event) => onChange({ ...value, heading: event.target.value })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="hero-subheading">Subheading</Label>
        <Textarea
          id="hero-subheading"
          rows={2}
          value={value.subheading ?? ""}
          onChange={(event) => onChange({ ...value, subheading: event.target.value || null })}
        />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="hero-image">Background image URL</Label>
        <Input
          id="hero-image"
          value={value.image_url ?? ""}
          placeholder="https://..."
          onChange={(event) => onChange({ ...value, image_url: event.target.value || null })}
        />
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label htmlFor="hero-cta-label">Button label</Label>
          <Input id="hero-cta-label" value={value.cta_label ?? ""} onChange={(event) => onChange({ ...value, cta_label: event.target.value || null })} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="hero-cta-url">Button link</Label>
          <Input id="hero-cta-url" value={value.cta_url ?? ""} onChange={(event) => onChange({ ...value, cta_url: event.target.value || null })} />
        </div>
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label htmlFor="hero-secondary-label">Secondary button label</Label>
          <Input
            id="hero-secondary-label"
            value={value.secondary_cta_label ?? ""}
            onChange={(event) => onChange({ ...value, secondary_cta_label: event.target.value || null })}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="hero-secondary-url">Secondary button link</Label>
          <Input
            id="hero-secondary-url"
            value={value.secondary_cta_url ?? ""}
            onChange={(event) => onChange({ ...value, secondary_cta_url: event.target.value || null })}
          />
        </div>
      </div>
    </div>
  );
}
