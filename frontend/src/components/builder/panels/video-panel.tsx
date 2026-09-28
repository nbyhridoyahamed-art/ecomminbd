"use client";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { VideoSettings } from "@/types/homepage-block";

export function VideoPanel({ value, onChange }: ContentPanelProps<VideoSettings>) {
  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="video-heading">Heading (optional)</Label>
        <Input id="video-heading" value={value.heading ?? ""} onChange={(event) => onChange({ ...value, heading: event.target.value || null })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="video-url">Video URL</Label>
        <Input
          id="video-url"
          value={value.video_url}
          placeholder="https://..."
          onChange={(event) => onChange({ ...value, video_url: event.target.value })}
        />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="video-poster">Poster image URL</Label>
        <Input
          id="video-poster"
          value={value.poster_image_url ?? ""}
          placeholder="https://..."
          onChange={(event) => onChange({ ...value, poster_image_url: event.target.value || null })}
        />
      </div>
      <label className="flex items-center gap-2 text-sm">
        <Checkbox checked={value.autoplay} onCheckedChange={(checked) => onChange({ ...value, autoplay: checked === true })} />
        Autoplay (muted, loops)
      </label>
    </div>
  );
}
