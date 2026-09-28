"use client";

import { useState } from "react";
import { ChevronDown } from "lucide-react";

import { cn } from "@/lib/utils";
import type { FaqSettings } from "@/types/homepage-block";

export function FaqBlock({ settings }: { settings: FaqSettings }) {
  const [openIndex, setOpenIndex] = useState<number | null>(null);

  if (settings.items.length === 0) return null;

  return (
    <section className="mx-auto max-w-3xl space-y-4">
      {settings.heading ? <h2 className="text-section font-semibold text-text-primary">{settings.heading}</h2> : null}
      <div className="divide-y divide-border rounded-lg border border-border">
        {settings.items.map((item, index) => {
          const isOpen = openIndex === index;
          return (
            <div key={index}>
              <button
                type="button"
                className="flex w-full items-center justify-between gap-4 px-4 py-3 text-left"
                aria-expanded={isOpen}
                onClick={() => setOpenIndex(isOpen ? null : index)}
              >
                <span className="font-medium text-text-primary">{item.question}</span>
                <ChevronDown className={cn("size-4 shrink-0 transition-transform", isOpen && "rotate-180")} />
              </button>
              {isOpen ? <p className="px-4 pb-4 text-sm text-text-secondary">{item.answer}</p> : null}
            </div>
          );
        })}
      </div>
    </section>
  );
}
