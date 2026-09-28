import { Search } from "lucide-react";

/**
 * The "SEO" tab (spec section 58) is intentionally a placeholder — per-page
 * and per-block SEO metadata (meta tags, canonical, schema) is Phase 15's
 * job (DEVELOPMENT_ROADMAP.md). Image-bearing blocks already collect their
 * own alt text directly in the Content tab (gallery, banners) rather than
 * duplicating it here.
 */
export function SeoPanel() {
  return (
    <div className="flex flex-col items-center gap-2 py-8 text-center text-sm text-text-muted">
      <Search className="size-8" />
      <p>Page-level SEO metadata (meta tags, canonical URLs, schema) arrives with Phase 15.</p>
      <p>Image alt text for this block is set directly in its Content tab.</p>
    </div>
  );
}
