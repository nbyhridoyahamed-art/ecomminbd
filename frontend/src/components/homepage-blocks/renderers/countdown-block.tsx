"use client";

import Link from "next/link";

import { Button } from "@/components/ui/button";
import { useCountdown } from "@/hooks/use-countdown";
import type { CountdownSettings } from "@/types/homepage-block";

const UNITS = [
  { key: "days", label: "Days" },
  { key: "hours", label: "Hours" },
  { key: "minutes", label: "Minutes" },
  { key: "seconds", label: "Seconds" },
] as const;

export function CountdownBlock({ settings }: { settings: CountdownSettings }) {
  const countdown = useCountdown(settings.ends_at);

  if (countdown.expired) return null;

  return (
    <section className="space-y-6 rounded-lg border border-border bg-surface px-6 py-10 text-center">
      <div className="space-y-2">
        <h2 className="text-section font-semibold text-text-primary">{settings.heading}</h2>
        {settings.subheading ? <p className="mx-auto max-w-xl text-text-secondary">{settings.subheading}</p> : null}
      </div>
      <div className="flex flex-wrap items-center justify-center gap-4 tablet:gap-6">
        {UNITS.map((unit) => (
          <div key={unit.key} className="flex flex-col items-center gap-1 rounded-md bg-surface-elevated px-5 py-3">
            <span className="text-page-title font-semibold text-text-primary tablet:text-display">
              {String(countdown[unit.key]).padStart(2, "0")}
            </span>
            <span className="text-xs uppercase text-text-muted">{unit.label}</span>
          </div>
        ))}
      </div>
      {settings.cta_label && settings.cta_url ? (
        <Button asChild size="lg">
          <Link href={settings.cta_url}>{settings.cta_label}</Link>
        </Button>
      ) : null}
    </section>
  );
}
