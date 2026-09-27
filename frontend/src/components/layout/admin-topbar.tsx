"use client";

import { useState } from "react";
import { usePathname } from "next/navigation";
import { useTheme } from "next-themes";
import { Bell, LogOut, Menu, Moon, Sun, User as UserIcon } from "lucide-react";

import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet";
import { SidebarNav } from "@/components/layout/sidebar-nav";
import { useLogout } from "@/hooks/use-auth";
import { NAV_ITEMS } from "@/components/layout/nav-items";
import type { User } from "@/types/auth";

function initials(name: string) {
  return name
    .split(" ")
    .map((part) => part[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();
}

const SEGMENT_LABELS: Record<string, string> = {
  general: "General",
  users: "Users",
  roles: "Roles",
  products: "Products",
  categories: "Categories",
  brands: "Brands",
  new: "New",
  "stock-levels": "Stock Levels",
  movements: "Movements",
  transfers: "Transfers",
  warehouses: "Warehouses",
  "purchase-orders": "Purchase Orders",
  suppliers: "Suppliers",
};

function getBreadcrumb(pathname: string): string[] {
  const segments = pathname.split("/").filter(Boolean);
  if (segments.length === 0) return ["Dashboard"];

  const [root, ...rest] = segments;
  const rootItem = NAV_ITEMS.find((item) => item.href.split("/")[1] === root);
  const crumbs = [rootItem?.label ?? root];

  for (const segment of rest) {
    if (/^\d+$/.test(segment)) {
      crumbs.push("Edit");
    } else {
      crumbs.push(SEGMENT_LABELS[segment] ?? segment);
    }
  }

  return crumbs;
}

export function AdminTopbar({ user }: { user?: User }) {
  const pathname = usePathname();
  const { theme, setTheme } = useTheme();
  const logout = useLogout();
  const [mobileNavOpen, setMobileNavOpen] = useState(false);

  const breadcrumb = getBreadcrumb(pathname);

  return (
    <header className="flex h-14 shrink-0 items-center justify-between border-b border-border bg-surface px-4">
      <div className="flex items-center gap-3">
        <Sheet open={mobileNavOpen} onOpenChange={setMobileNavOpen}>
          <SheetTrigger asChild>
            <Button variant="ghost" size="icon" className="md:hidden" aria-label="Open menu">
              <Menu />
            </Button>
          </SheetTrigger>
          <SheetContent side="left" className="w-64 p-0">
            <SheetHeader className="border-b border-border p-4">
              <SheetTitle>NBY Commerce</SheetTitle>
            </SheetHeader>
            <div className="py-4">
              <SidebarNav onNavigate={() => setMobileNavOpen(false)} />
            </div>
          </SheetContent>
        </Sheet>

        <nav aria-label="Breadcrumb" className="flex items-center gap-1.5 text-sm text-text-secondary">
          {breadcrumb.map((crumb, index) => (
            <span key={index} className="flex items-center gap-1.5">
              {index > 0 ? <span className="text-text-muted">/</span> : null}
              <span
                className={index === breadcrumb.length - 1 ? "font-medium text-text-primary" : undefined}
              >
                {crumb}
              </span>
            </span>
          ))}
        </nav>
      </div>

      <div className="flex items-center gap-2">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" aria-label="Notifications">
              <Bell />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-72">
            <DropdownMenuLabel>Notifications</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <p className="px-2 py-6 text-center text-sm text-text-muted">
              No notifications yet.
            </p>
          </DropdownMenuContent>
        </DropdownMenu>

        <Button
          variant="ghost"
          size="icon"
          aria-label="Toggle theme"
          onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
        >
          {theme === "dark" ? <Sun /> : <Moon />}
        </Button>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" aria-label="Profile menu" className="rounded-full">
              <Avatar>
                <AvatarFallback>{user ? initials(user.name) : <UserIcon className="size-4" />}</AvatarFallback>
              </Avatar>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-56">
            <DropdownMenuLabel className="flex flex-col gap-0.5">
              <span className="text-sm font-medium text-text-primary">{user?.name}</span>
              <span className="text-xs text-text-muted">{user?.email}</span>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem onSelect={() => logout.mutate()} className="text-danger">
              <LogOut className="size-4" />
              Log out
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  );
}
