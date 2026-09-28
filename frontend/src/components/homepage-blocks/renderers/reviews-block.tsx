import { Star } from "lucide-react";

import type { AutoManualTestimonialSettings } from "@/types/homepage-block";
import type { StorefrontTestimonial } from "@/types/storefront";

export function ReviewsBlock({
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
      <div className="grid grid-cols-1 gap-4 tablet:grid-cols-2 desktop:grid-cols-4">
        {testimonials.map((testimonial) => {
          const rating = testimonial.rating;

          return (
            <div key={testimonial.id} className="flex flex-col gap-2 rounded-lg border border-border bg-surface p-4">
              {rating !== null ? (
                <div className="flex gap-0.5">
                  {Array.from({ length: 5 }, (_, index) => (
                    <Star key={index} className={index < rating ? "size-4 fill-warning text-warning" : "size-4 fill-none text-text-muted"} />
                  ))}
                </div>
              ) : null}
              <p className="text-sm text-text-secondary">{testimonial.quote}</p>
              <p className="text-sm font-medium text-text-primary">{testimonial.name}</p>
            </div>
          );
        })}
      </div>
    </section>
  );
}
