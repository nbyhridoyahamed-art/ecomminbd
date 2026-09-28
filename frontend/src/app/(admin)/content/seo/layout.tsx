"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

import { cn } from "@/lib/utils";

const TABS = [
  { label: "Redirects", href: "/content/seo/redirects" },
  { label: "SEO Templates", href: "/content/seo/templates" },
];

/** seo.manage is a single umbrella permission, so every tab here is already gated by the parent Content layout's own SEO tab — no per-tab permission filtering needed. */
export default function SeoLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();

  return (
    <div className="space-y-4">
      <nav className="flex gap-1">
        {TABS.map((tab) => {
          const isActive = pathname.startsWith(tab.href);
          return (
            <Link
              key={tab.href}
              href={tab.href}
              className={cn(
                "rounded-md px-3 py-1.5 text-sm font-medium transition-colors",
                isActive ? "bg-surface-secondary text-text-primary" : "text-text-secondary hover:text-text-primary",
              )}
            >
              {tab.label}
            </Link>
          );
        })}
      </nav>

      {children}
    </div>
  );
}
