import type { SpacerSettings } from "@/types/homepage-block";

export function SpacerBlock({ settings }: { settings: SpacerSettings }) {
  return <div style={{ height: settings.height_px }} aria-hidden="true" />;
}
