import Link from "next/link";

import { Button } from "@/components/ui/button";
import type { CtaSettings } from "@/types/homepage-block";

export function CtaBlock({ settings }: { settings: CtaSettings }) {
  const hasImage = Boolean(settings.background_image_url);

  return (
    <section
      className={`relative overflow-hidden rounded-lg border border-border px-6 py-12 text-center tablet:py-16 ${hasImage ? "text-white" : "bg-surface"}`}
      style={hasImage ? { backgroundImage: `url(${settings.background_image_url})`, backgroundSize: "cover", backgroundPosition: "center" } : undefined}
    >
      {hasImage ? <div className="absolute inset-0 bg-black/40" /> : null}
      <div className="relative">
        <p className={`text-page-title font-semibold tablet:text-display ${hasImage ? "text-white" : "text-text-primary"}`}>
          {settings.heading}
        </p>
        {settings.subheading ? (
          <p className={`mx-auto mt-3 max-w-xl ${hasImage ? "text-white/90" : "text-text-secondary"}`}>{settings.subheading}</p>
        ) : null}
        <div className="mt-6">
          <Button asChild size="lg">
            <Link href={settings.cta_url}>{settings.cta_label}</Link>
          </Button>
        </div>
      </div>
    </section>
  );
}
