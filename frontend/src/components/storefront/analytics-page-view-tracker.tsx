"use client";

import { useEffect } from "react";
import { usePathname } from "next/navigation";

import { trackEvent } from "@/lib/analytics";

/**
 * Fires a `page_view` analytics event on mount and again on every route
 * change. Mounted once in the storefront layout so it covers every
 * storefront page from one place, including ones no other component tracks
 * individually.
 */
export function AnalyticsPageViewTracker() {
  const pathname = usePathname();

  useEffect(() => {
    trackEvent("page_view", { path: pathname });
  }, [pathname]);

  return null;
}
