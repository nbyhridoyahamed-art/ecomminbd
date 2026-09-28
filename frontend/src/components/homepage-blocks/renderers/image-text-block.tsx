import Link from "next/link";

import { Button } from "@/components/ui/button";
import type { ImageTextSettings } from "@/types/homepage-block";

export function ImageTextBlock({ settings }: { settings: ImageTextSettings }) {
  return (
    <section className="flex flex-col gap-6 tablet:flex-row tablet:items-center">
      <div className={settings.image_position === "right" ? "tablet:order-2 tablet:w-1/2" : "tablet:order-1 tablet:w-1/2"}>
        {settings.image_url ? (
          // eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset
          <img src={settings.image_url} alt="" className="w-full rounded-lg object-cover" />
        ) : null}
      </div>
      <div className={settings.image_position === "right" ? "space-y-3 tablet:order-1 tablet:w-1/2" : "space-y-3 tablet:order-2 tablet:w-1/2"}>
        <h2 className="text-section font-semibold text-text-primary">{settings.heading}</h2>
        <p className="text-text-secondary">{settings.body}</p>
        {settings.cta_label && settings.cta_url ? (
          <Button asChild>
            <Link href={settings.cta_url}>{settings.cta_label}</Link>
          </Button>
        ) : null}
      </div>
    </section>
  );
}
