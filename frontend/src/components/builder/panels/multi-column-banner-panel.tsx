"use client";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { BannerItem, MultiBannerSettings } from "@/types/homepage-block";

/**
 * Edits a fixed-length row of banners (2 or 3 — set by which block type,
 * two_column_banner or three_column_banner, was added). Registered for
 * BOTH types by the content-panel registry; this is the one shared panel
 * for both. No add/remove controls: the array length is fixed by the
 * block type and must never change here.
 */
export function MultiColumnBannerPanel({ value, onChange }: ContentPanelProps<MultiBannerSettings>) {
  function updateBanner(index: number, patch: Partial<BannerItem>) {
    onChange({ ...value, banners: value.banners.map((b, i) => (i === index ? { ...b, ...patch } : b)) });
  }

  return (
    <div className="space-y-4">
      {value.banners.map((banner, index) => (
        <div key={index} className="space-y-3 rounded-lg border border-border p-3">
          <p className="text-sm font-medium text-text-primary">Banner {index + 1}</p>
          <div className="space-y-1.5">
            <Label htmlFor={`multi-banner-image-${index}`}>Image URL</Label>
            <Input
              id={`multi-banner-image-${index}`}
              value={banner.image_url ?? ""}
              placeholder="https://..."
              onChange={(event) => updateBanner(index, { image_url: event.target.value || null })}
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor={`multi-banner-link-${index}`}>Link URL</Label>
            <Input
              id={`multi-banner-link-${index}`}
              value={banner.link_url ?? ""}
              onChange={(event) => updateBanner(index, { link_url: event.target.value || null })}
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor={`multi-banner-alt-${index}`}>Alt text</Label>
            <Input
              id={`multi-banner-alt-${index}`}
              value={banner.alt_text ?? ""}
              onChange={(event) => updateBanner(index, { alt_text: event.target.value || null })}
            />
          </div>
        </div>
      ))}
    </div>
  );
}
