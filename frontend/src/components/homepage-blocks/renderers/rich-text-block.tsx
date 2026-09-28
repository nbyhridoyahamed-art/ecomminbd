import type { RichTextSettings } from "@/types/homepage-block";

export function RichTextBlock({ settings }: { settings: RichTextSettings }) {
  if (!settings.body) return null;

  return (
    <section className="mx-auto max-w-3xl space-y-3">
      {settings.heading ? <h2 className="text-section font-semibold text-text-primary">{settings.heading}</h2> : null}
      {/* staff-authored via the TipTap editor (builder.edit only), same trust boundary as any other admin-authored content */}
      <div className="rich-text-content" dangerouslySetInnerHTML={{ __html: settings.body }} />
    </section>
  );
}
