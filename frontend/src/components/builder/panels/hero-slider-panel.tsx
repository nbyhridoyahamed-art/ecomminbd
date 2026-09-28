"use client";

import { Plus, Trash2 } from "lucide-react";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { HeroSlide, HeroSliderSettings } from "@/types/homepage-block";

const MAX_SLIDES = 8;
const MIN_SLIDES = 1;

export function HeroSliderPanel({ value, onChange }: ContentPanelProps<HeroSliderSettings>) {
  function addRow() {
    onChange({ ...value, slides: [...value.slides, { heading: "", subheading: null, image_url: null, cta_label: null, cta_url: null }] });
  }

  function updateRow(index: number, patch: Partial<HeroSlide>) {
    onChange({ ...value, slides: value.slides.map((s, i) => (i === index ? { ...s, ...patch } : s)) });
  }

  function removeRow(index: number) {
    onChange({ ...value, slides: value.slides.filter((_, i) => i !== index) });
  }

  return (
    <div className="space-y-4">
      <div className="space-y-3">
        {value.slides.map((slide, index) => (
          <div key={index} className="space-y-3 rounded-lg border border-border p-3">
            <div className="flex items-center justify-between">
              <p className="text-sm font-medium text-text-primary">Slide {index + 1}</p>
              <Button
                type="button"
                size="icon"
                variant="ghost"
                aria-label="Remove slide"
                disabled={value.slides.length <= MIN_SLIDES}
                onClick={() => removeRow(index)}
              >
                <Trash2 className="text-danger" />
              </Button>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor={`hero-slider-heading-${index}`}>Heading</Label>
              <Input
                id={`hero-slider-heading-${index}`}
                value={slide.heading}
                onChange={(event) => updateRow(index, { heading: event.target.value })}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor={`hero-slider-subheading-${index}`}>Subheading</Label>
              <Textarea
                id={`hero-slider-subheading-${index}`}
                rows={2}
                value={slide.subheading ?? ""}
                onChange={(event) => updateRow(index, { subheading: event.target.value || null })}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor={`hero-slider-image-${index}`}>Background image URL</Label>
              <Input
                id={`hero-slider-image-${index}`}
                value={slide.image_url ?? ""}
                placeholder="https://..."
                onChange={(event) => updateRow(index, { image_url: event.target.value || null })}
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor={`hero-slider-cta-label-${index}`}>Button label</Label>
                <Input
                  id={`hero-slider-cta-label-${index}`}
                  value={slide.cta_label ?? ""}
                  onChange={(event) => updateRow(index, { cta_label: event.target.value || null })}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor={`hero-slider-cta-url-${index}`}>Button link</Label>
                <Input
                  id={`hero-slider-cta-url-${index}`}
                  value={slide.cta_url ?? ""}
                  onChange={(event) => updateRow(index, { cta_url: event.target.value || null })}
                />
              </div>
            </div>
          </div>
        ))}
      </div>

      <Button type="button" variant="outline" disabled={value.slides.length >= MAX_SLIDES} onClick={addRow}>
        <Plus />
        Add slide
      </Button>

      <div className="space-y-3 border-t border-border pt-4">
        <label className="flex items-center gap-2 text-sm">
          <Checkbox checked={value.autoplay} onCheckedChange={(checked) => onChange({ ...value, autoplay: checked === true })} />
          Autoplay
        </label>
        <div className="space-y-1.5">
          <Label htmlFor="hero-slider-interval">Interval (seconds)</Label>
          <Input
            id="hero-slider-interval"
            type="number"
            min={2}
            max={15}
            value={value.interval_seconds}
            onChange={(event) => onChange({ ...value, interval_seconds: Number(event.target.value) })}
          />
        </div>
      </div>
    </div>
  );
}
