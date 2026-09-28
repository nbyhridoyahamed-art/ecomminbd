import type { StatisticsSettings } from "@/types/homepage-block";

export function StatisticsBlock({ settings }: { settings: StatisticsSettings }) {
  if (settings.items.length === 0) return null;

  return (
    <section className="space-y-4">
      {settings.heading ? <h2 className="text-section font-semibold text-text-primary">{settings.heading}</h2> : null}
      <div className="grid grid-cols-2 gap-6 text-center tablet:grid-cols-4">
        {settings.items.map((item, index) => (
          <div key={index} className="space-y-1">
            <p className="text-page-title font-semibold text-text-primary">{item.value}</p>
            <p className="text-sm text-text-secondary">{item.label}</p>
          </div>
        ))}
      </div>
    </section>
  );
}
