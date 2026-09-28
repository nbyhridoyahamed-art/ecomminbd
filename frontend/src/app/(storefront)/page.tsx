import type { Metadata } from "next";

import { JsonLd, organizationAndWebsiteJsonLd } from "@/lib/json-ld";
import { storefrontApi } from "@/lib/storefront-api";
import { buildStorefrontMetadata } from "@/lib/storefront-seo";
import type { StorefrontStore } from "@/types/storefront";
import { StorefrontHomeClient } from "./home-client";

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000";

/**
 * The homepage has no single SEO-bearing entity — it's site-wide. Unlike
 * every other storefront page.tsx, there's no 404/redirect concern here (a
 * homepage can't 404), so a failed store fetch falls back to generic
 * metadata instead of ever failing the whole page.
 */
async function getStore(): Promise<StorefrontStore | null> {
  try {
    return await storefrontApi.get<StorefrontStore>("/storefront/store");
  } catch {
    return null;
  }
}

export async function generateMetadata(): Promise<Metadata> {
  const store = await getStore();

  if (!store) {
    return { title: "Home" };
  }

  return buildStorefrontMetadata({
    seo: null,
    fallbackTitle: store.name,
    fallbackDescription: undefined,
    path: "/",
  });
}

export default async function StorefrontHomePage() {
  const store = await getStore();

  return (
    <>
      {store ? <JsonLd data={organizationAndWebsiteJsonLd({ name: store.name, url: SITE_URL })} /> : null}
      <StorefrontHomeClient />
    </>
  );
}
