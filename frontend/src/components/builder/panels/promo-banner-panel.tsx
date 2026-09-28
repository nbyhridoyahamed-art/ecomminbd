"use client";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { PromoBannerSettings } from "@/types/homepage-block";

export function PromoBannerPanel({ value, onChange }: ContentPanelProps<PromoBannerSettings>) {
  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="promo-banner-image">Image URL</Label>
        <Input
          id="promo-banner-image"
          value={value.image_url ?? ""}
          placeholder="https://..."
          onChange={(event) => onChange({ ...value, image_url: event.target.value || null })}
        />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="promo-banner-link">Link URL</Label>
        <Input
          id="promo-banner-link"
          value={value.link_url ?? ""}
          onChange={(event) => onChange({ ...value, link_url: event.target.value || null })}
        />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="promo-banner-alt">Alt text</Label>
        <Input
          id="promo-banner-alt"
          value={value.alt_text ?? ""}
          onChange={(event) => onChange({ ...value, alt_text: event.target.value || null })}
        />
      </div>
    </div>
  );
}
