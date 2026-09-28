"use client";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { CountdownSettings } from "@/types/homepage-block";

function toDatetimeLocalValue(iso: string): string {
  const d = new Date(iso);
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function CountdownPanel({ value, onChange }: ContentPanelProps<CountdownSettings>) {
  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="countdown-heading">Heading</Label>
        <Input id="countdown-heading" value={value.heading} onChange={(event) => onChange({ ...value, heading: event.target.value })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="countdown-subheading">Subheading</Label>
        <Textarea
          id="countdown-subheading"
          rows={2}
          value={value.subheading ?? ""}
          onChange={(event) => onChange({ ...value, subheading: event.target.value || null })}
        />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="countdown-ends-at">Ends at</Label>
        <Input
          id="countdown-ends-at"
          type="datetime-local"
          value={toDatetimeLocalValue(value.ends_at)}
          onChange={(event) => onChange({ ...value, ends_at: new Date(event.target.value).toISOString() })}
        />
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label htmlFor="countdown-cta-label">Button label</Label>
          <Input
            id="countdown-cta-label"
            value={value.cta_label ?? ""}
            onChange={(event) => onChange({ ...value, cta_label: event.target.value || null })}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="countdown-cta-url">Button link</Label>
          <Input
            id="countdown-cta-url"
            value={value.cta_url ?? ""}
            onChange={(event) => onChange({ ...value, cta_url: event.target.value || null })}
          />
        </div>
      </div>
    </div>
  );
}
