"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { SeoFields, seoFieldsToPayload, seoToFieldsValue, type SeoFieldsValue } from "@/components/shared/seo-fields";
import { useStoreSeo, useUpdateStoreSeo } from "@/hooks/use-store-seo";
import type { Seo } from "@/types/seo";

interface SeoPanelProps {
  storeId: number;
}

/**
 * Site-wide SEO (the homepage's own title/description, Organization/WebSite
 * JSON-LD, a fallback OG image) — one record for the whole store, not
 * per-block, so this panel edits the Store's own seo_metadata row
 * regardless of which block is currently selected on the canvas, with its
 * own Save button rather than joining the selected block's autosave
 * session. Image alt text stays on each block's own Content tab — this
 * panel is page/site-level only.
 */
export function SeoPanel({ storeId }: SeoPanelProps) {
  const { data, isLoading } = useStoreSeo(storeId);

  if (isLoading || !data) {
    return (
      <div className="space-y-3">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-20 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    );
  }

  // Remounts (and re-initializes local edit state) only when the store changes.
  return <SeoPanelForm key={storeId} storeId={storeId} initialSeo={data.seo} />;
}

function SeoPanelForm({ storeId, initialSeo }: { storeId: number; initialSeo: Seo | null }) {
  const updateSeo = useUpdateStoreSeo(storeId);
  const [seo, setSeo] = useState<SeoFieldsValue>(() => seoToFieldsValue(initialSeo));

  return (
    <div className="space-y-4">
      <p className="text-xs text-text-muted">
        Site-wide SEO for the homepage — used wherever a page has no SEO override of its own. This is independent of
        the block currently selected on the canvas.
      </p>
      <SeoFields value={seo} onChange={setSeo} titlePlaceholder="Your store name" />
      <Button type="button" size="sm" loading={updateSeo.isPending} onClick={() => updateSeo.mutate(seoFieldsToPayload(seo))}>
        Save SEO settings
      </Button>
    </div>
  );
}
