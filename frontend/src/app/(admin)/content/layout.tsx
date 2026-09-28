"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

import { cn } from "@/lib/utils";
import { can } from "@/lib/permissions";
import { useCurrentUser } from "@/hooks/use-auth";

const TABS = [
  { label: "Pages", href: "/content/pages", permission: "pages.manage" },
  { label: "Homepage", href: "/content/homepage", permission: "builder.view" },
  { label: "Blog", href: "/content/blog/posts", permission: "blog.manage" },
];

/**
 * Added now that Content has its second resource (Homepage, alongside
 * Pages) — the same "tab-nav infrastructure only once 2+ tabs genuinely
 * exist" rule Purchasing/Reports already established.
 */
export default function ContentLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const { data: user } = useCurrentUser();

  const visibleTabs = TABS.filter((tab) => can(user, tab.permission));

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-page-title font-semibold text-text-primary">Content</h1>
        <p className="text-sm text-text-secondary">Manage static pages and the storefront homepage.</p>
      </div>

      {visibleTabs.length > 0 ? (
        <div className="border-b border-border">
          <nav className="flex gap-1">
            {visibleTabs.map((tab) => {
              const isActive = pathname.startsWith(tab.href);
              return (
                <Link
                  key={tab.href}
                  href={tab.href}
                  className={cn(
                    "border-b-2 px-3 py-2 text-sm font-medium transition-colors",
                    isActive
                      ? "border-primary text-primary"
                      : "border-transparent text-text-secondary hover:text-text-primary",
                  )}
                >
                  {tab.label}
                </Link>
              );
            })}
          </nav>
        </div>
      ) : null}

      {children}
    </div>
  );
}
