import { ApiError, type ApiResponse, type PaginationMeta } from "@/types/api";

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1";

/**
 * A minimal, server-only fetch for the public (unauthenticated) storefront
 * API — used from Server Components, generateMetadata, and sitemap.ts/
 * robots.ts. These can't import src/lib/api.ts: it pulls in auth-token.ts,
 * a "use client" module, and the server can't call a function exported from
 * a Client Component module (only render it as a component).
 */
async function requestStorefront<T>(path: string): Promise<{ data: T; meta?: PaginationMeta }> {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const json = (await response.json().catch(() => null)) as ApiResponse<T> | null;

  if (!response.ok || !json || json.success === false) {
    throw new ApiError(
      json?.message ?? "Something went wrong.",
      response.status,
      json && json.success === false ? json.errors : undefined,
    );
  }

  return { data: json.data, meta: json.meta };
}

export const storefrontApi = {
  get: async <T>(path: string): Promise<T> => (await requestStorefront<T>(path)).data,
  getWithMeta: <T>(path: string) => requestStorefront<T>(path),
};
