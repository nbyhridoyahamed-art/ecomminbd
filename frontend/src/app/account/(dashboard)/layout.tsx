"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";

import { cn } from "@/lib/utils";
import { useCustomerAuthToken } from "@/lib/customer-auth-token";
import { Skeleton } from "@/components/ui/skeleton";
import { useCurrentCustomer } from "@/hooks/use-customer-auth";

const NAV_ITEMS = [
  { href: "/account", label: "Overview" },
  { href: "/account/orders", label: "Orders" },
  { href: "/account/addresses", label: "Addresses" },
  { href: "/account/profile", label: "Profile" },
] as const;

function AuthGateSkeleton() {
  return (
    <div className="mx-auto max-w-[1400px] space-y-3 px-4 py-8">
      <Skeleton className="h-8 w-1/3" />
      <Skeleton className="h-32 w-full" />
    </div>
  );
}

export default function AccountDashboardLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const [mounted, setMounted] = useState(false);
  const token = useCustomerAuthToken();
  const { data: customer, isLoading, isError } = useCurrentCustomer();

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted && (!token || isError)) {
      router.replace("/account/login");
    }
  }, [mounted, token, isError, router]);

  if (!mounted || !token || isLoading) {
    return <AuthGateSkeleton />;
  }

  return (
    <div className="mx-auto max-w-[1400px] px-4 py-8">
      <div className="mb-6 space-y-1">
        <h1 className="text-page-title font-semibold text-text-primary">My Account</h1>
        {customer ? <p className="text-sm text-text-secondary">Signed in as {customer.name}</p> : null}
      </div>

      <nav className="mb-6 flex gap-1 border-b border-border">
        {NAV_ITEMS.map((item) => {
          const active = item.href === "/account" ? pathname === "/account" : pathname.startsWith(item.href);
          return (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                "border-b-2 px-3 py-2 text-sm font-medium",
                active
                  ? "border-primary text-primary"
                  : "border-transparent text-text-secondary hover:text-text-primary",
              )}
            >
              {item.label}
            </Link>
          );
        })}
      </nav>

      {children}
    </div>
  );
}
