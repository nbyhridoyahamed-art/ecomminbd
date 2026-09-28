"use client";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { HomepageBlockAnimation } from "@/types/homepage-block";

const OPTIONS: { value: Exclude<HomepageBlockAnimation, null>; label: string }[] = [
  { value: "none", label: "None" },
  { value: "fade", label: "Fade in" },
  { value: "slide", label: "Slide up" },
  { value: "scale", label: "Scale in" },
  { value: "reveal", label: "Reveal" },
];

interface AnimationPanelProps {
  value: HomepageBlockAnimation;
  onChange: (value: HomepageBlockAnimation) => void;
}

/** The "Animation" tab (spec sections 58/122) — plays once when the block scrolls into view; respects prefers-reduced-motion globally (see globals.css). */
export function AnimationPanel({ value, onChange }: AnimationPanelProps) {
  return (
    <div className="space-y-3">
      <p className="text-sm text-text-secondary">Plays once as this block scrolls into view. Disabled automatically for visitors who prefer reduced motion.</p>
      <div className="grid grid-cols-2 gap-2">
        {OPTIONS.map((option) => (
          <Button
            key={option.value}
            type="button"
            variant={(value ?? "none") === option.value ? "secondary" : "outline"}
            className={cn("justify-start")}
            onClick={() => onChange(option.value === "none" ? null : option.value)}
          >
            {option.label}
          </Button>
        ))}
      </div>
    </div>
  );
}
