import Link from "next/link";

import type { MultiBannerSettings } from "@/types/homepage-block";

/**
 * Renders a fixed-length row of banners (2 or 3, depending on which block
 * type — two_column_banner or three_column_banner — was added). Registered
 * for BOTH types by the content-panel/renderer registries; this is the one
 * shared component for both.
 */
export function MultiColumnBannerBlock({ settings }: { settings: MultiBannerSettings }) {
  return (
    <div className={settings.banners.length === 3 ? "grid grid-cols-1 gap-4 tablet:grid-cols-3" : "grid grid-cols-1 gap-4 tablet:grid-cols-2"}>
      {settings.banners.map((banner, index) => {
        if (!banner.image_url) return null;

        const image = (
          // eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset
          <img src={banner.image_url} alt={banner.alt_text ?? ""} className="w-full rounded-lg object-cover" />
        );

        return <div key={index}>{banner.link_url ? <Link href={banner.link_url} className="block">{image}</Link> : <div>{image}</div>}</div>;
      })}
    </div>
  );
}
