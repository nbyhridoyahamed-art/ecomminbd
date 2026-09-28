import { ApiError, type ApiResponse, type PaginationMeta } from "@/types/api";
import { clearAuthToken, getAuthToken } from "@/lib/auth-token";

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1";

interface RequestOptions extends Omit<RequestInit, "body"> {
  body?: object | FormData;
}

async function requestEnvelope<T>(path: string, options: RequestOptions = {}): Promise<ApiSuccess<T>> {
  const token = getAuthToken();
  const isFormData = options.body instanceof FormData;

  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  // FormData must NOT get an explicit Content-Type — the browser sets its
  // own multipart boundary, and overriding it here would break the upload.
  if (options.body && !isFormData) headers.set("Content-Type", "application/json");
  if (token) headers.set("Authorization", `Bearer ${token}`);

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers,
    body: isFormData ? (options.body as FormData) : options.body ? JSON.stringify(options.body) : undefined,
  });

  const json = (await response.json().catch(() => null)) as ApiResponse<T> | null;

  if (!response.ok || !json || json.success === false) {
    if (response.status === 401) {
      clearAuthToken();
    }

    throw new ApiError(
      json?.message ?? "Something went wrong. Please try again.",
      response.status,
      json && json.success === false ? json.errors : undefined,
    );
  }

  return json;
}

interface ApiSuccess<T> {
  success: true;
  message: string;
  data: T;
  meta?: PaginationMeta;
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const json = await requestEnvelope<T>(path, options);
  return json.data;
}

async function requestWithMeta<T>(
  path: string,
  options: RequestOptions = {},
): Promise<{ data: T; meta?: PaginationMeta }> {
  const json = await requestEnvelope<T>(path, options);
  return { data: json.data, meta: json.meta };
}

/**
 * Downloads an authenticated file endpoint (e.g. a CSV export) that returns
 * a raw file body rather than the usual JSON envelope — fetched directly
 * (with the Bearer token) as a Blob, then saved via a throwaway object URL,
 * since a plain <a href> can't carry an Authorization header.
 */
async function download(path: string, filenameFallback: string): Promise<void> {
  const token = getAuthToken();
  const headers = new Headers();
  if (token) headers.set("Authorization", `Bearer ${token}`);

  const response = await fetch(`${API_BASE_URL}${path}`, { headers });

  if (!response.ok) {
    const json = (await response.json().catch(() => null)) as ApiResponse<unknown> | null;
    if (response.status === 401) clearAuthToken();
    throw new ApiError(
      json && json.success === false ? json.message : "Could not download the file.",
      response.status,
    );
  }

  const blob = await response.blob();
  const disposition = response.headers.get("Content-Disposition") ?? "";
  const filename = /filename="?([^"]+)"?/.exec(disposition)?.[1] ?? filenameFallback;

  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

export const api = {
  get: <T>(path: string, options?: RequestOptions) => request<T>(path, { ...options, method: "GET" }),
  getWithMeta: <T>(path: string, options?: RequestOptions) =>
    requestWithMeta<T>(path, { ...options, method: "GET" }),
  post: <T>(path: string, body?: object | FormData, options?: RequestOptions) =>
    request<T>(path, { ...options, method: "POST", body }),
  put: <T>(path: string, body?: object | FormData, options?: RequestOptions) =>
    request<T>(path, { ...options, method: "PUT", body }),
  delete: <T>(path: string, options?: RequestOptions) => request<T>(path, { ...options, method: "DELETE" }),
  download,
};
