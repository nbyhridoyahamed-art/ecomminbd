import { ApiError, type ApiResponse, type PaginationMeta } from "@/types/api";
import { clearCustomerAuthToken, getCustomerAuthToken } from "@/lib/customer-auth-token";

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1";

/**
 * A separate, minimal twin of lib/api.ts for the customer-account
 * surface — same envelope handling, but always sends the customer token
 * (never the admin one), and has no FormData/download support since
 * nothing under /account needs a file upload yet.
 */
interface ApiSuccess<T> {
  success: true;
  message: string;
  data: T;
  meta?: PaginationMeta;
}

interface RequestOptions extends Omit<RequestInit, "body"> {
  body?: object;
}

async function requestEnvelope<T>(path: string, options: RequestOptions = {}): Promise<ApiSuccess<T>> {
  const token = getCustomerAuthToken();

  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  if (options.body) headers.set("Content-Type", "application/json");
  if (token) headers.set("Authorization", `Bearer ${token}`);

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  const json = (await response.json().catch(() => null)) as ApiResponse<T> | null;

  if (!response.ok || !json || json.success === false) {
    if (response.status === 401) clearCustomerAuthToken();

    throw new ApiError(
      json?.message ?? "Something went wrong. Please try again.",
      response.status,
      json && json.success === false ? json.errors : undefined,
    );
  }

  return json;
}

async function request<T>(path: string, options?: RequestOptions): Promise<T> {
  const json = await requestEnvelope<T>(path, options);
  return json.data;
}

async function requestWithMeta<T>(path: string, options?: RequestOptions): Promise<{ data: T; meta?: PaginationMeta }> {
  const json = await requestEnvelope<T>(path, options);
  return { data: json.data, meta: json.meta };
}

export const accountApi = {
  get: <T>(path: string) => request<T>(path, { method: "GET" }),
  getWithMeta: <T>(path: string) => requestWithMeta<T>(path, { method: "GET" }),
  post: <T>(path: string, body?: object) => request<T>(path, { method: "POST", body }),
  put: <T>(path: string, body?: object) => request<T>(path, { method: "PUT", body }),
  delete: <T>(path: string) => request<T>(path, { method: "DELETE" }),
};
