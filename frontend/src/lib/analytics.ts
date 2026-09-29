"use client";

import type { TrackEventPayload, TrackEventType } from "@/types/analytics";

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1";
const SESSION_STORAGE_KEY = "eleventory_analytics_session";

/**
 * A per-browser, anonymous session id — a storefront visitor is tracked
 * without an account, the same way any analytics tool works. Wrapped in
 * try/catch: a private window, blocked storage, or an old browser without
 * `crypto.randomUUID` should degrade to "no tracking this page load," never
 * throw and break the page around it.
 */
function getSessionId(): string | null {
  try {
    const existing = window.localStorage.getItem(SESSION_STORAGE_KEY);
    if (existing) return existing;

    const id = typeof crypto.randomUUID === "function" ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    window.localStorage.setItem(SESSION_STORAGE_KEY, id);
    return id;
  } catch {
    return null;
  }
}

/**
 * Fire-and-forget — every call site (a cart-store action, a page-mount
 * effect) calls this and moves on; it never returns a promise the caller
 * needs to await and never throws.
 *
 * Deliberately NOT `navigator.sendBeacon`, despite that being the usual
 * textbook choice for "survives page unload": sendBeacon always sends with
 * credentials included, with no way for calling code to opt out, and its
 * return value only means "the browser queued it" — not "the server
 * accepted it." Against this API's CORS config (a wildcard
 * `Access-Control-Allow-Origin`, correct for the token-based, credential-
 * free auth every other endpoint here uses), a credentialed beacon request
 * is rejected by the browser's own CORS check *after* `sendBeacon()` has
 * already returned `true` — confirmed against a real browser, not assumed
 * — so every event would have silently vanished with no fallback ever
 * running. `fetch(..., { keepalive: true })` gives the identical
 * survives-navigation guarantee without forcing credentials, and is what
 * every event uses here.
 */
export function trackEvent(eventType: TrackEventType, payload: TrackEventPayload = {}): void {
  try {
    const sessionId = getSessionId();
    if (!sessionId) return;

    const body = JSON.stringify({ session_id: sessionId, event_type: eventType, ...payload });
    const url = `${API_BASE_URL}/storefront/analytics/events`;

    void fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body,
      keepalive: true,
    }).catch(() => undefined);
  } catch {
    // Never let a tracking call break the page it's tracking.
  }
}
