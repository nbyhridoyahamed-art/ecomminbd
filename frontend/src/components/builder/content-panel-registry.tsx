import type { ComponentType } from "react";

import { CategoryGridPanel } from "@/components/builder/panels/category-grid-panel";
import { FeaturedProductsPanel } from "@/components/builder/panels/featured-products-panel";
import { HeroPanel } from "@/components/builder/panels/hero-panel";
import { RichTextPanel } from "@/components/builder/panels/rich-text-panel";
import type { ContentPanelProps } from "@/components/builder/panel-types";
import type { HomepageBlockType } from "@/types/homepage-block";

/**
 * The block registry's Content-tab half (spec section 60: "Create a block
 * registry. Do not build one giant conditional renderer."). Every block
 * type must have an entry here — BuilderPanel looks it up by `block.type`
 * and renders it with the block's current settings.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any -- each entry's props are internally consistent for its own type; the map itself is necessarily heterogeneous
export const CONTENT_PANELS: Partial<Record<HomepageBlockType, ComponentType<ContentPanelProps<any>>>> = {
  hero: HeroPanel,
  category_grid: CategoryGridPanel,
  featured_products: FeaturedProductsPanel,
  rich_text: RichTextPanel,
};
