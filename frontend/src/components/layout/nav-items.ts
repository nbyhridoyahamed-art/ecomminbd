import type { LucideIcon } from "lucide-react";
import { LayoutDashboard, Settings } from "lucide-react";

export interface NavItem {
  label: string;
  href: string;
  icon: LucideIcon;
  /** Shown to everyone when omitted; otherwise requires at least one of these permissions. */
  anyPermission?: string[];
}

/**
 * Only routes that are actually implemented belong here (spec rule 178:
 * no dead links). New entries are added the moment their phase ships —
 * see PAGE_INVENTORY.md for what's next.
 */
export const NAV_ITEMS: NavItem[] = [
  { label: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
  {
    label: "Settings",
    href: "/settings/general",
    icon: Settings,
    anyPermission: ["settings.manage", "users.view", "roles.view"],
  },
];
