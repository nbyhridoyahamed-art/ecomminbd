export interface AnalyticsTotals {
  page_views: number;
  unique_sessions: number;
  product_views: number;
  searches: number;
  add_to_cart: number;
  checkout_starts: number;
  purchases: number;
  conversion_rate: number;
}

export interface AnalyticsTrafficPoint {
  date: string;
  page_views: number;
  unique_sessions: number;
}

export interface AnalyticsOverview {
  totals: AnalyticsTotals;
  by_period: AnalyticsTrafficPoint[];
  comparison: {
    date_from: string;
    date_to: string;
    totals: AnalyticsTotals;
  };
}

export interface AnalyticsProductRow {
  product_id: number;
  name: string;
  sku: string;
  view_count: number;
  add_to_cart_count: number;
  view_to_cart_rate: number;
}

export interface AnalyticsSearchRow {
  query: string;
  search_count: number;
  avg_results_count: number | null;
  zero_results: boolean;
}

export type AnalyticsFunnelStageName = "product_view" | "add_to_cart" | "checkout_start" | "purchase";

export interface AnalyticsFunnelStage {
  stage: AnalyticsFunnelStageName;
  sessions: number;
  conversion_from_previous: number | null;
}

export interface AnalyticsFunnel {
  stages: AnalyticsFunnelStage[];
}

export interface AnalyticsCustomerPoint {
  date: string;
  new: number;
  returning: number;
}

export interface AnalyticsCustomers {
  totals: {
    new_customers: number;
    returning_customers: number;
    repeat_purchase_rate: number;
  };
  by_period: AnalyticsCustomerPoint[];
}

/** Mirrors TrackEventRequest::EVENT_TYPES on the backend. */
export type TrackEventType =
  | "page_view"
  | "product_view"
  | "category_view"
  | "search"
  | "add_to_cart"
  | "remove_from_cart"
  | "checkout_start"
  | "purchase";

export interface TrackEventPayload {
  path?: string;
  product_id?: number;
  category_id?: number;
  query?: string;
  results_count?: number;
  order_uuid?: string;
}
