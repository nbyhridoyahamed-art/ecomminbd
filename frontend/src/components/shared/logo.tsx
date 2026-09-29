"use client";

import { useId } from "react";

import { cn } from "@/lib/utils";

interface LogoMarkProps {
  className?: string;
  size?: number;
}

/**
 * The Eleventory brand mark: a hexagonal "e" on a diagonal blue gradient,
 * per BRAND_GUIDELINE. `useId` scopes the gradient so multiple instances
 * (e.g. sidebar + mobile nav sheet) mounted at once don't collide on one
 * `<linearGradient id>`.
 */
export function LogoMark({ className, size = 32 }: LogoMarkProps) {
  const gradientId = `eleventory-mark-${useId()}`;

  return (
    <svg
      viewBox="0 0 32 32"
      width={size}
      height={size}
      className={cn("shrink-0", className)}
      role="img"
      aria-label="Eleventory"
    >
      <defs>
        <linearGradient id={gradientId} x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
          <stop offset="0" stopColor="#3b82f6" />
          <stop offset="1" stopColor="#1d4ed8" />
        </linearGradient>
      </defs>
      <path d="M16 1.2 L29.7 8.6 V23.4 L16 30.8 L2.3 23.4 V8.6 Z" fill={`url(#${gradientId})`} />
      <text
        x="16"
        y="22.3"
        textAnchor="middle"
        fontFamily="var(--font-inter), ui-sans-serif, system-ui, sans-serif"
        fontWeight={800}
        fontSize={17}
        fill="#ffffff"
      >
        e
      </text>
    </svg>
  );
}

interface LogoProps {
  className?: string;
  iconSize?: number;
  textClassName?: string;
}

/** Full horizontal lockup: hex mark + lowercase "eleventory" wordmark. */
export function Logo({ className, iconSize = 32, textClassName }: LogoProps) {
  return (
    <span className={cn("flex items-center gap-2 overflow-hidden", className)}>
      <LogoMark size={iconSize} />
      <span className={cn("truncate font-semibold text-text-primary", textClassName)}>eleventory</span>
    </span>
  );
}
