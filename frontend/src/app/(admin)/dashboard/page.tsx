"use client";

import { useQuery } from "@tanstack/react-query";
import { Building2, ShieldCheck, Store as StoreIcon, Users, Warehouse as WarehouseIcon } from "lucide-react";

import { api } from "@/lib/api";
import { useCurrentUser } from "@/hooks/use-auth";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Skeleton } from "@/components/ui/skeleton";
import { StatCard } from "@/components/ui/stat-card";
import { EmptyState } from "@/components/ui/empty-state";

/** Fetches just enough of a list endpoint to read its total count. */
function useTotal(path: string) {
  return useQuery<number>({
    queryKey: ["total", path],
    queryFn: async () => {
      const { data, meta } = await api.getWithMeta<unknown[]>(`${path}?per_page=1`);
      return meta?.total ?? (Array.isArray(data) ? data.length : 0);
    },
  });
}

export default function DashboardPage() {
  const { data: user } = useCurrentUser();
  const stores = useTotal("/stores");
  const warehouses = useTotal("/warehouses");
  const users = useTotal("/users");
  const roles = useTotal("/roles");

  const loading = stores.isLoading || warehouses.isLoading || users.isLoading || roles.isLoading;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-page-title font-semibold text-text-primary">
          {user ? `Welcome back, ${user.name.split(" ")[0]}` : "Welcome back"}
        </h1>
        <p className="text-sm text-text-secondary">
          Here&apos;s the current state of your store foundation.
        </p>
      </div>

      {loading ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-24 w-full" />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard label="Stores" value={stores.data ?? 0} icon={<StoreIcon />} />
          <StatCard label="Warehouses" value={warehouses.data ?? 0} icon={<WarehouseIcon />} />
          <StatCard label="Staff users" value={users.data ?? 0} icon={<Users />} />
          <StatCard label="Roles" value={roles.data ?? 0} icon={<ShieldCheck />} />
        </div>
      )}

      <EmptyState
        icon={<Building2 />}
        title="Catalog, orders, and inventory aren't enabled yet"
        description="Phases 5–10 (Catalog, Inventory, Purchasing, Orders, Delivery, Returns) haven't shipped in this build. Once they do, this dashboard will show revenue, pending orders, COD, and low-stock KPIs here — see DEVELOPMENT_ROADMAP.md for the plan."
      />

      {user && user.permissions.length === 0 ? (
        <Alert variant="warning">
          <AlertDescription>
            Your account has no role assigned yet, so most actions will be blocked until a
            Super Admin assigns one.
          </AlertDescription>
        </Alert>
      ) : null}
    </div>
  );
}
