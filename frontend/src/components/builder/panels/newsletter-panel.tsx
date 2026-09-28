"use client";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { NewsletterSettings } from "@/types/homepage-block";

export function NewsletterPanel({ value, onChange }: ContentPanelProps<NewsletterSettings>) {
  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="newsletter-heading">Heading</Label>
        <Input id="newsletter-heading" value={value.heading} onChange={(event) => onChange({ ...value, heading: event.target.value })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="newsletter-subheading">Subheading</Label>
        <Textarea
          id="newsletter-subheading"
          rows={2}
          value={value.subheading ?? ""}
          onChange={(event) => onChange({ ...value, subheading: event.target.value || null })}
        />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="newsletter-cta-label">Button label</Label>
        <Input id="newsletter-cta-label" value={value.cta_label} onChange={(event) => onChange({ ...value, cta_label: event.target.value })} />
      </div>
    </div>
  );
}
