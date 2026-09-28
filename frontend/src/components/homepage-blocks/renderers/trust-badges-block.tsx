import { CreditCard, Headset, Lock, RotateCcw, ShieldCheck, Truck } from "lucide-react";

import type { TrustBadgesSettings } from "@/types/homepage-block";

const ICONS: Record<string, typeof Truck> = {
  truck: Truck,
  shield: ShieldCheck,
  lock: Lock,
  "credit-card": CreditCard,
  "rotate-ccw": RotateCcw,
  headset: Headset,
};

export function TrustBadgesBlock({ settings }: { settings: TrustBadgesSettings }) {
  if (settings.items.length === 0) return null;

  return (
    <section className="space-y-4">
      {settings.heading ? <h2 className="text-section font-semibold text-text-primary">{settings.heading}</h2> : null}
      <div className="flex flex-wrap justify-center gap-6">
        {settings.items.map((item, index) => {
          const Icon = ICONS[item.icon] ?? ShieldCheck;
          return (
            <div key={index} className="flex items-center gap-2">
              <Icon className="size-6 text-primary" />
              <span className="text-sm font-medium text-text-primary">{item.label}</span>
            </div>
          );
        })}
      </div>
    </section>
  );
}
