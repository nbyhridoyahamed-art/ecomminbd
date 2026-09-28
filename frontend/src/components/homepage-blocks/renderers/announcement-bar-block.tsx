"use client";

import { useState } from "react";
import Link from "next/link";
import { X } from "lucide-react";

import type { AnnouncementBarSettings } from "@/types/homepage-block";

export function AnnouncementBarBlock({ settings }: { settings: AnnouncementBarSettings }) {
  const [dismissed, setDismissed] = useState(false);

  if (dismissed) return null;

  return (
    <div className="relative flex w-full flex-wrap items-center justify-center gap-1.5 bg-primary px-10 py-2 text-center text-sm text-white">
      <span>{settings.text}</span>
      {settings.link_url && settings.link_label ? (
        <Link href={settings.link_url} className="font-semibold underline underline-offset-2 hover:no-underline">
          {settings.link_label}
        </Link>
      ) : null}
      {settings.dismissible ? (
        <button
          type="button"
          aria-label="Dismiss"
          onClick={() => setDismissed(true)}
          className="absolute right-3 top-1/2 -translate-y-1/2 text-white/80 hover:text-white"
        >
          <X className="size-4" />
        </button>
      ) : null}
    </div>
  );
}
