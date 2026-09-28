"use client";

import { AutoManualPicker } from "@/components/builder/auto-manual-picker";
import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useTestimonials } from "@/hooks/use-testimonials";
import type { AutoManualTestimonialSettings } from "@/types/homepage-block";

export function TestimonialsPanel({ value, onChange, storeId }: ContentPanelProps<AutoManualTestimonialSettings>) {
  const { data: testimonials } = useTestimonials(storeId);
  const options = (testimonials ?? []).map((testimonial) => ({ id: testimonial.id, label: testimonial.name }));

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="testimonials-heading">Heading</Label>
        <Input id="testimonials-heading" value={value.heading} onChange={(event) => onChange({ ...value, heading: event.target.value })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="testimonials-limit">Number to show</Label>
        <Input
          id="testimonials-limit"
          type="number"
          min={1}
          max={12}
          value={value.limit}
          onChange={(event) => onChange({ ...value, limit: Number(event.target.value) })}
        />
      </div>
      <AutoManualPicker
        mode={value.mode}
        onModeChange={(mode) => onChange({ ...value, mode })}
        selectedIds={value.testimonial_ids}
        onSelectedIdsChange={(testimonial_ids) => onChange({ ...value, testimonial_ids })}
        options={options}
        autoDescription="Shows your active testimonials automatically."
      />
    </div>
  );
}
