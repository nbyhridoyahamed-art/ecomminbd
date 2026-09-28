import { CartDrawer } from "@/components/storefront/cart-drawer";
import { MobileBottomNav } from "@/components/storefront/mobile-bottom-nav";
import { StorefrontFooter } from "@/components/storefront/storefront-footer";
import { StorefrontHeader } from "@/components/storefront/storefront-header";

export default function StorefrontLayout({ children }: { children: React.ReactNode }) {
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
