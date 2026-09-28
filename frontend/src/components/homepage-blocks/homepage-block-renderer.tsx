import { BlockStyleScope } from "@/components/homepage-blocks/block-style-scope";
import { CategoryGridBlock } from "@/components/homepage-blocks/renderers/category-grid-block";
import { FeaturedProductsBlock } from "@/components/homepage-blocks/renderers/featured-products-block";
import { HeroBlock } from "@/components/homepage-blocks/renderers/hero-block";
import { RichTextBlock } from "@/components/homepage-blocks/renderers/rich-text-block";
import type {
  AutoManualCategorySettings,
  AutoManualProductSettings,
  HeroSettings,
  RichTextSettings,
} from "@/types/homepage-block";
import type { StorefrontHomepageBlock } from "@/types/storefront";

/**
 * The block registry's storefront-rendering half (spec section 60). One
 * switch, not "one giant conditional renderer" per block's markup — each
 * case delegates immediately to that type's own renderer component,
 * passing only the settings/resolved-data slice it needs. Every case is
 * wrapped in the same BlockStyleScope so design/responsive/visibility/
 * animation apply identically regardless of type.
 */
export function HomepageBlockRenderer({ block }: { block: StorefrontHomepageBlock }) {
  const content = renderContent(block);
  if (content === null) return null;

  return (
    <BlockStyleScope blockId={block.id} styles={block.styles} responsive={block.responsive} visibility={block.visibility} animation={block.animation}>
      {content}
    </BlockStyleScope>
  );
}

function renderContent(block: StorefrontHomepageBlock) {
  switch (block.type) {
    case "hero":
      return <HeroBlock settings={block.settings as unknown as HeroSettings} />;
    case "category_grid":
      return <CategoryGridBlock settings={block.settings as unknown as AutoManualCategorySettings} categories={block.data.categories ?? []} />;
    case "featured_products":
      return <FeaturedProductsBlock settings={block.settings as unknown as AutoManualProductSettings} products={block.data.products ?? []} />;
    case "rich_text":
      return <RichTextBlock settings={block.settings as unknown as RichTextSettings} />;
    default:
      return null;
  }
}
