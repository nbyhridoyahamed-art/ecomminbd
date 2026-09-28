"use client";

import { LayoutGrid } from "lucide-react";

import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { HomepageBlockRenderer } from "@/components/homepage-blocks/homepage-block-renderer";
import { useStorefrontHomepage } from "@/hooks/use-storefront-homepage";

/**
 * Fully block-driven (Phase 13): every section here comes from whatever
 * staff published in /content/homepage, rendered through the exact same
 * registry the admin builder's own live preview uses — this page has no
 * hardcoded sections of its own left to maintain. A Client Component so it
 * can fetch live data (TanStack Query); its parent page.tsx is a Server
 * Component that fetches the store info once more, server-side, purely to
 * build real <head> metadata and JSON-LD.
 */
export function StorefrontHomeClient() {
  const { data: blocks, isLoading } = useStorefrontHomepage();

  if (isLoading) {
    return (
      <div className="mx-auto max-w-[1400px] space-y-12 px-4 py-8">
        <Skeleton className="h-64 w-full" />
        <Skeleton className="h-48 w-full" />
        <Skeleton className="h-48 w-full" />
      </div>
    );
  }

  if (!blocks || blocks.length === 0) {
    return (
      <div className="mx-auto max-w-[1400px] px-4 py-16">
        <EmptyState icon={<LayoutGrid />} title="Nothing published yet" description="Check back soon." />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-[1400px] space-y-12 px-4 py-8">
      {blocks.map((block) => (
        <HomepageBlockRenderer key={block.id} block={block} />
      ))}
    </div>
  );
}
