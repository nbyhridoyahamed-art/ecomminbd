"use client";

import { useSyncExternalStore } from "react";

// A deliberately separate key/event from auth-token.ts's admin token — a
// staff member and a customer can be signed in on the same browser at
// once (admin in one tab, storefront/account in another), and sharing one
// key would let either session silently clobber the other's token.
const TOKEN_KEY = "nby_customer_auth_token";
const TOKEN_CHANGE_EVENT = "nby-customer-auth-token-change";

export function getCustomerAuthToken(): string | null {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(TOKEN_KEY);
}

export function setCustomerAuthToken(token: string): void {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(TOKEN_KEY, token);
  window.dispatchEvent(new Event(TOKEN_CHANGE_EVENT));
}

export function clearCustomerAuthToken(): void {
  if (typeof window === "undefined") return;
  window.localStorage.removeItem(TOKEN_KEY);
  window.dispatchEvent(new Event(TOKEN_CHANGE_EVENT));
}

function subscribe(callback: () => void) {
  window.addEventListener("storage", callback);
  window.addEventListener(TOKEN_CHANGE_EVENT, callback);
  return () => {
    window.removeEventListener("storage", callback);
    window.removeEventListener(TOKEN_CHANGE_EVENT, callback);
  };
}

export function useCustomerAuthToken(): string | null {
  return useSyncExternalStore(subscribe, getCustomerAuthToken, () => null);
}
