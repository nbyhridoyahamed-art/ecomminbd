"use client";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { LimitOnlySettings } from "@/types/homepage-block";

export function BlogPostsPanel({ value, onChange }: ContentPanelProps<LimitOnlySettings>) {
  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="blog-posts-heading">Heading</Label>
        <Input id="blog-posts-heading" value={value.heading} onChange={(event) => onChange({ ...value, heading: event.target.value })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="blog-posts-limit">Number to show</Label>
        <Input
          id="blog-posts-limit"
          type="number"
          min={1}
          max={12}
          value={value.limit}
          onChange={(event) => onChange({ ...value, limit: Number(event.target.value) })}
        />
      </div>
    </div>
  );
}
