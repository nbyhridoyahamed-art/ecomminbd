import type { LucideIcon } from "lucide-react";
import { Boxes, ClipboardList, LayoutDashboard, Package, Settings, ShoppingCart } from "lucide-react";

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
    label: "Catalog",
    href: "/catalog/products",
    icon: Package,
    anyPermission: ["products.view", "categories.view", "brands.view"],
  },
  {
    label: "Inventory",
    href: "/inventory/stock-levels",
    icon: Boxes,
    anyPermission: ["inventory.view", "inventory.transfer", "warehouses.view"],
  },
  {
    label: "Purchasing",
    href: "/purchasing/purchase-orders",
    icon: ClipboardList,
    anyPermission: ["purchase_orders.view", "suppliers.view"],
  },
  {
    label: "Orders",
    href: "/orders/orders",
    icon: ShoppingCart,
    anyPermission: ["orders.view", "customers.view"],
  },
  {
    label: "Settings",
    href: "/settings/general",
    icon: Settings,
    anyPermission: ["settings.manage", "users.view", "roles.view"],
  },
];
