import type { AutoManualTestimonialSettings } from "@/types/homepage-block";
import type { StorefrontTestimonial } from "@/types/storefront";

export function TestimonialsBlock({
  settings,
  testimonials,
}: {
  settings: AutoManualTestimonialSettings;
  testimonials: StorefrontTestimonial[];
}) {
  if (testimonials.length === 0) return null;

  return (
    <section className="space-y-4">
      <h2 className="text-section font-semibold text-text-primary">{settings.heading}</h2>
      <div className="grid grid-cols-1 gap-4 tablet:grid-cols-2 desktop:grid-cols-3">
        {testimonials.map((testimonial) => (
          <figure key={testimonial.id} className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-6">
            <blockquote className="text-lg italic text-text-primary">“{testimonial.quote}”</blockquote>
            <figcaption className="mt-auto flex items-center gap-3">
              {testimonial.avatar_url ? (
                // eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset
                <img src={testimonial.avatar_url} alt={testimonial.name} className="size-10 shrink-0 rounded-full object-cover" />
              ) : null}
              <div>
                <p className="text-sm font-medium text-text-primary">{testimonial.name}</p>
                {testimonial.role ? <p className="text-xs text-text-muted">{testimonial.role}</p> : null}
              </div>
            </figcaption>
          </figure>
        ))}
      </div>
    </section>
  );
}
