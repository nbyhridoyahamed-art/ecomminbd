import Link from "next/link";

import { Button } from "@/components/ui/button";
import type { HeroSettings } from "@/types/homepage-block";

export function HeroBlock({ settings }: { settings: HeroSettings }) {
  const hasImage = Boolean(settings.image_url);

  return (
    <section
      className={`relative overflow-hidden rounded-lg border border-border px-6 py-12 text-center tablet:py-16 ${hasImage ? "text-white" : "bg-surface"}`}
      style={hasImage ? { backgroundImage: `url(${settings.image_url})`, backgroundSize: "cover", backgroundPosition: "center" } : undefined}
    >
      {hasImage ? <div className="absolute inset-0 bg-black/40" /> : null}
      <div className="relative">
        <h1 className={`text-page-title font-semibold tablet:text-display ${hasImage ? "text-white" : "text-text-primary"}`}>
          {settings.heading}
        </h1>
        {settings.subheading ? (
          <p className={`mx-auto mt-3 max-w-xl ${hasImage ? "text-white/90" : "text-text-secondary"}`}>{settings.subheading}</p>
        ) : null}
        <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
          {settings.cta_label && settings.cta_url ? (
            <Button asChild size="lg">
              <Link href={settings.cta_url}>{settings.cta_label}</Link>
            </Button>
          ) : null}
          {settings.secondary_cta_label && settings.secondary_cta_url ? (
            <Button asChild size="lg" variant="outline">
              <Link href={settings.secondary_cta_url}>{settings.secondary_cta_label}</Link>
            </Button>
          ) : null}
        </div>
      </div>
    </section>
  );
}
