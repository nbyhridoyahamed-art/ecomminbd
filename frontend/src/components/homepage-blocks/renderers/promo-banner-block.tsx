import Link from "next/link";

import type { PromoBannerSettings } from "@/types/homepage-block";

export function PromoBannerBlock({ settings }: { settings: PromoBannerSettings }) {
  if (!settings.image_url) return null;

  const image = (
    // eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset
    <img src={settings.image_url} alt={settings.alt_text ?? ""} className="w-full rounded-lg object-cover" />
  );

  if (settings.link_url) {
    return (
      <Link href={settings.link_url} className="block">
        {image}
      </Link>
    );
  }

  return <div>{image}</div>;
}
