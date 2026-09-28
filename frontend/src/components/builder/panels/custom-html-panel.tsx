"use client";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { CustomHtmlSettings } from "@/types/homepage-block";

export function CustomHtmlPanel({ value, onChange }: ContentPanelProps<CustomHtmlSettings>) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor="custom-html-code">HTML</Label>
      <Textarea
        id="custom-html-code"
        className="font-mono text-xs"
        rows={12}
        value={value.html ?? ""}
        onChange={(event) => onChange({ ...value, html: event.target.value || null })}
      />
      <p className="text-xs text-text-muted">Raw HTML, rendered as-is on the storefront. Staff use only — never paste markup from an untrusted source.</p>
    </div>
  );
}
