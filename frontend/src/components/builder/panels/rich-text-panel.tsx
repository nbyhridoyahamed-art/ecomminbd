"use client";

import { RichTextEditor } from "@/components/builder/rich-text-editor";
import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { RichTextSettings } from "@/types/homepage-block";

export function RichTextPanel({ value, onChange }: ContentPanelProps<RichTextSettings>) {
  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="rich-text-heading">Heading (optional)</Label>
        <Input id="rich-text-heading" value={value.heading ?? ""} onChange={(event) => onChange({ ...value, heading: event.target.value || null })} />
      </div>
      <div className="space-y-1.5">
        <Label>Content</Label>
        <RichTextEditor value={value.body} onChange={(body) => onChange({ ...value, body })} placeholder="Write something..." />
      </div>
    </div>
  );
}
