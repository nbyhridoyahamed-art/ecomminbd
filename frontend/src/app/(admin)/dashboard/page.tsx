"use client";

import { useQuery } from "@tanstack/react-query";
import {
  AlertTriangle,
  ClipboardList,
  Package,
  ShieldCheck,
  ShoppingCart,
  Store as StoreIcon,
  Truck,
  Undo2,
  Users,
  Warehouse as WarehouseIcon,
} from "lucide-react";

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
  const products = useQuery<number>({
    queryKey: ["total", "/products", user?.current_store_id],
    queryFn: async () => {
      const { meta } = await api.getWithMeta<unknown[]>(`/products?store_id=${user?.current_store_id}&per_page=1`);
      return meta?.total ?? 0;
    },
    enabled: Boolean(user?.current_store_id),
  });
  const lowStock = useQuery<number>({
    queryKey: ["total", "/stock-levels/low-stock-count", user?.current_store_id],
    queryFn: async () => {
      const count = await api.get<{ count: number }>(`/stock-levels/low-stock-count?store_id=${user?.current_store_id}`);
      return count.count;
    },
    enabled: Boolean(user?.current_store_id),
  });
  const openPurchaseOrders = useQuery<number>({
    queryKey: ["total", "/purchase-orders", "open", user?.current_store_id],
    queryFn: async () => {
      const { meta } = await api.getWithMeta<unknown[]>(
        `/purchase-orders?store_id=${user?.current_store_id}&open=1&per_page=1`,
      );
      return meta?.total ?? 0;
    },
    enabled: Boolean(user?.current_store_id),
  });
  const pendingOrders = useQuery<number>({
    queryKey: ["total", "/orders", "open", user?.current_store_id],
    queryFn: async () => {
      const { meta } = await api.getWithMeta<unknown[]>(`/orders?store_id=${user?.current_store_id}&open=1&per_page=1`);
      return meta?.total ?? 0;
    },
    enabled: Boolean(user?.current_store_id),
  });
  const shipmentsInTransit = useQuery<number>({
    queryKey: ["total", "/shipments", "in_transit", user?.current_store_id],
    queryFn: async () => {
      const { meta } = await api.getWithMeta<unknown[]>(
        `/shipments?store_id=${user?.current_store_id}&status=in_transit&per_page=1`,
      );
      return meta?.total ?? 0;
    },
    enabled: Boolean(user?.current_store_id),
  });

  const loading =
    stores.isLoading ||
    warehouses.isLoading ||
    users.isLoading ||
    roles.isLoading ||
    products.isLoading ||
    lowStock.isLoading ||
    openPurchaseOrders.isLoading ||
    pendingOrders.isLoading ||
    shipmentsInTransit.isLoading;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-page-title font-semibold text-text-primary">
          {user ? `Welcome back, ${user.name.split(" ")[0]}` : "Welcome back"}
        </h1>
        <p className="text-sm text-text-secondary">
          Here&apos;s the current state of your store.
        </p>
      </div>

      {loading ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-9">
          {Array.from({ length: 9 }).map((_, i) => (
            <Skeleton key={i} className="h-24 w-full" />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-9">
          <StatCard label="Products" value={products.data ?? 0} icon={<Package />} />
          <StatCard
            label="Low stock alerts"
            value={lowStock.data ?? 0}
            icon={<AlertTriangle />}
            tone={lowStock.data && lowStock.data > 0 ? "danger" : undefined}
          />
          <StatCard label="Open purchase orders" value={openPurchaseOrders.data ?? 0} icon={<ClipboardList />} />
          <StatCard label="Pending orders" value={pendingOrders.data ?? 0} icon={<ShoppingCart />} />
          <StatCard label="Shipments in transit" value={shipmentsInTransit.data ?? 0} icon={<Truck />} />
          <StatCard label="Stores" value={stores.data ?? 0} icon={<StoreIcon />} />
          <StatCard label="Warehouses" value={warehouses.data ?? 0} icon={<WarehouseIcon />} />
          <StatCard label="Staff users" value={users.data ?? 0} icon={<Users />} />
          <StatCard label="Roles" value={roles.data ?? 0} icon={<ShieldCheck />} />
        </div>
      )}

      <EmptyState
        icon={<Undo2 />}
        title="Returns aren't enabled yet"
        description="Phase 10 (Returns) hasn't shipped in this build. Once it does, this dashboard will show return/refund KPIs here — see DEVELOPMENT_ROADMAP.md for the plan."
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
