"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { ChevronLeft, ChevronRight } from "lucide-react";

import { Button } from "@/components/ui/button";
import type { HeroSliderSettings } from "@/types/homepage-block";

export function HeroSliderBlock({ settings }: { settings: HeroSliderSettings }) {
  const [index, setIndex] = useState(0);

  useEffect(() => {
    if (!settings.autoplay || settings.slides.length === 0) return;
    const id = setInterval(() => {
      setIndex((i) => (i + 1) % settings.slides.length);
    }, settings.interval_seconds * 1000);
    return () => clearInterval(id);
  }, [settings.autoplay, settings.interval_seconds, settings.slides.length]);

  if (settings.slides.length === 0) return null;

  const multiple = settings.slides.length > 1;
  const activeIndex = index % settings.slides.length;
  const slide = settings.slides[activeIndex];
  const hasImage = Boolean(slide.image_url);

  return (
    <section
      className={`relative overflow-hidden rounded-lg border border-border px-6 py-12 text-center tablet:py-16 ${hasImage ? "text-white" : "bg-surface"}`}
      style={hasImage ? { backgroundImage: `url(${slide.image_url})`, backgroundSize: "cover", backgroundPosition: "center" } : undefined}
    >
      {hasImage ? <div className="absolute inset-0 bg-black/40" /> : null}
      <div className="relative">
        <p className={`text-page-title font-semibold tablet:text-display ${hasImage ? "text-white" : "text-text-primary"}`}>
          {slide.heading}
        </p>
        {slide.subheading ? (
          <p className={`mx-auto mt-3 max-w-xl ${hasImage ? "text-white/90" : "text-text-secondary"}`}>{slide.subheading}</p>
        ) : null}
        {slide.cta_label && slide.cta_url ? (
          <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
            <Button asChild size="lg">
              <Link href={slide.cta_url}>{slide.cta_label}</Link>
            </Button>
          </div>
        ) : null}
      </div>

      {multiple ? (
        <>
          <button
            type="button"
            aria-label="Previous slide"
            onClick={() => setIndex((i) => (i - 1 + settings.slides.length) % settings.slides.length)}
            className={`absolute left-2 top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-full transition-colors ${hasImage ? "bg-black/30 text-white hover:bg-black/50" : "bg-border/40 text-text-primary hover:bg-border/60"}`}
          >
            <ChevronLeft className="size-5" />
          </button>
          <button
            type="button"
            aria-label="Next slide"
            onClick={() => setIndex((i) => (i + 1) % settings.slides.length)}
            className={`absolute right-2 top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-full transition-colors ${hasImage ? "bg-black/30 text-white hover:bg-black/50" : "bg-border/40 text-text-primary hover:bg-border/60"}`}
          >
            <ChevronRight className="size-5" />
          </button>
          <div className="relative mt-6 flex items-center justify-center gap-2">
            {settings.slides.map((_, dotIndex) => (
              <button
                key={dotIndex}
                type="button"
                aria-label={`Go to slide ${dotIndex + 1}`}
                onClick={() => setIndex(dotIndex)}
                className={`size-2 rounded-full transition-colors ${
                  dotIndex === activeIndex ? (hasImage ? "bg-white" : "bg-text-primary") : hasImage ? "bg-white/50" : "bg-text-primary/30"
                }`}
              />
            ))}
          </div>
        </>
      ) : null}
    </section>
  );
}
