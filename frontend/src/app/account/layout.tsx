import { CartDrawer } from "@/components/storefront/cart-drawer";
import { MobileBottomNav } from "@/components/storefront/mobile-bottom-nav";
import { StorefrontFooter } from "@/components/storefront/storefront-footer";
import { StorefrontHeader } from "@/components/storefront/storefront-header";

// Reuses the storefront's own chrome rather than the admin sidebar, per
// UI_UX_ARCHITECTURE.md: "Customer Account — a lighter authenticated
// shell under /account/*, reusing storefront chrome". No auth gate at
// this level — /account/login and /account/register live here too and
// must stay reachable while signed out; the gate is one level down, in
// the (dashboard) route group.
export default function AccountLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen flex-col bg-background">
      <StorefrontHeader />
      <main className="flex-1">{children}</main>
      <StorefrontFooter />
      <MobileBottomNav />
      <CartDrawer />
    </div>
  );
}
