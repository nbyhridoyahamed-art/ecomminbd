"use client";

import { cn } from "@/lib/utils";
import { useScrollReveal } from "@/hooks/use-scroll-reveal";
import type {
  BreakpointStyle,
  HomepageBlockAnimation,
  HomepageBlockResponsive,
  HomepageBlockStyles,
  HomepageBlockVisibility,
} from "@/types/homepage-block";

function breakpointDeclarations(style: BreakpointStyle | undefined, hidden: boolean | undefined): string[] {
  const decls: string[] = [];
  if (!style && hidden !== false) return decls;

  if (style?.width) decls.push(`width:${style.width}`);
  if (style?.height) decls.push(`height:${style.height}`);
  if (style?.padding) decls.push(`padding:${style.padding}`);
  if (style?.margin) decls.push(`margin:${style.margin}`);
  if (style?.font_size) decls.push(`font-size:${style.font_size}`);
  if (style?.gap) decls.push(`gap:${style.gap}`);
  if (style?.columns) decls.push(`grid-template-columns:repeat(${style.columns}, minmax(0, 1fr))`);
  if (style?.alignment) decls.push(`text-align:${style.alignment === "stretch" ? "left" : style.alignment}`);
  if (style?.display) decls.push(`display:${style.display}`);
  if (hidden === false) decls.push("display:none");

  return decls;
}

/** Builds the block's scoped `<style>` text from its stored design/responsive/visibility JSON — the only place this translation happens. */
export function buildBlockCss(
  scopeClass: string,
  styles: HomepageBlockStyles | undefined,
  responsive: HomepageBlockResponsive | undefined,
  visibility: HomepageBlockVisibility | undefined,
): string {
  const base: string[] = [];
  if (styles?.background_color) base.push(`background-color:${styles.background_color}`);
  if (styles?.text_color) base.push(`color:${styles.text_color}`);
  if (styles?.border_radius) base.push(`border-radius:${styles.border_radius}`);
  if (styles?.box_shadow) base.push(`box-shadow:${styles.box_shadow}`);
  base.push(...breakpointDeclarations(responsive?.desktop, visibility?.desktop));

  const tablet = breakpointDeclarations(responsive?.tablet, visibility?.tablet);
  const mobile = breakpointDeclarations(responsive?.mobile, visibility?.mobile);

  let css = "";
  if (base.length > 0) css += `.${scopeClass}{${base.join(";")}}`;
  if (tablet.length > 0) css += `@media (max-width:1023px){.${scopeClass}{${tablet.join(";")}}}`;
  if (mobile.length > 0) css += `@media (max-width:767px){.${scopeClass}{${mobile.join(";")}}}`;

  return css;
}

interface BlockStyleScopeProps {
  blockId: number | string;
  styles?: HomepageBlockStyles;
  responsive?: HomepageBlockResponsive;
  visibility?: HomepageBlockVisibility;
  animation?: HomepageBlockAnimation;
  className?: string;
  children: React.ReactNode;
}

/** Wraps a block renderer with its design/responsive/visibility/animation config applied — every storefront block renderer uses this, never raw Tailwind classes for per-instance style. */
export function BlockStyleScope({ blockId, styles, responsive, visibility, animation, className, children }: BlockStyleScopeProps) {
  const scopeClass = `hb-${blockId}`;
  const css = buildBlockCss(scopeClass, styles, responsive, visibility);
  const { ref, revealed } = useScrollReveal<HTMLDivElement>();
  const hasAnimation = Boolean(animation && animation !== "none");

  return (
    <>
      {css ? <style dangerouslySetInnerHTML={{ __html: css }} /> : null}
      <div
        ref={ref}
        className={cn(
          scopeClass,
          styles?.custom_class || undefined,
          hasAnimation ? `hb-anim-${animation}` : undefined,
          hasAnimation && revealed ? "hb-anim-in" : undefined,
          className,
        )}
      >
        {children}
      </div>
    </>
  );
}
