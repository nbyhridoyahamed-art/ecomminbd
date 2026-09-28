import type { MetadataRoute } from "next";

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000";

/**
 * This one Next.js app serves both the storefront and the admin/account
 * areas on the same domain — admin/(auth)/account route groups use no URL
 * prefix of their own (see the app directory layout), so their real
 * top-level segments are disallowed explicitly here rather than via a
 * shared "/admin" prefix that doesn't actually exist.
 */
const DISALLOWED_PATHS = [
  "/dashboard",
  "/catalog",
  "/content",
  "/delivery",
  "/inventory",
  "/orders",
  "/purchasing",
  "/reports",
  "/settings",
  "/account",
  "/login",
  "/cart",
  "/checkout",
  "/order-confirmation",
];

export default function robots(): MetadataRoute.Robots {
  return {
    rules: {
      userAgent: "*",
      allow: "/",
      disallow: DISALLOWED_PATHS,
    },
    sitemap: `${SITE_URL}/sitemap.xml`,
  };
}
