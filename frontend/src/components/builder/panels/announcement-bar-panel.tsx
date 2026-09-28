"use client";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { AnnouncementBarSettings } from "@/types/homepage-block";

export function AnnouncementBarPanel({ value, onChange }: ContentPanelProps<AnnouncementBarSettings>) {
  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="announcement-bar-text">Text</Label>
        <Input id="announcement-bar-text" value={value.text} onChange={(event) => onChange({ ...value, text: event.target.value })} />
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label htmlFor="announcement-bar-link-label">Link label</Label>
          <Input
            id="announcement-bar-link-label"
            value={value.link_label ?? ""}
            onChange={(event) => onChange({ ...value, link_label: event.target.value || null })}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="announcement-bar-link-url">Link URL</Label>
          <Input
            id="announcement-bar-link-url"
            value={value.link_url ?? ""}
            onChange={(event) => onChange({ ...value, link_url: event.target.value || null })}
          />
        </div>
      </div>
      <label className="flex items-center gap-2 text-sm">
        <Checkbox checked={value.dismissible} onCheckedChange={(checked) => onChange({ ...value, dismissible: checked === true })} />
        Dismissible
      </label>
    </div>
  );
}
