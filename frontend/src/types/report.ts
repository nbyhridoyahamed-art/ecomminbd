export type ReportGranularity = "day" | "week" | "month";

export interface SalesReportTotals {
  revenue_amount: number;
  orders_count: number;
  average_order_value: number;
}

export interface SalesReportPeriod {
  date: string;
  orders_count: number;
  revenue_amount: number;
}

export interface SalesReportPaymentMethod {
  payment_method: string;
  orders_count: number;
  revenue_amount: number;
}

export interface SalesReportCourier {
  courier_id: number;
  courier_name: string;
  orders_count: number;
  revenue_amount: number;
}

export interface SalesReport {
  totals: SalesReportTotals;
  by_period: SalesReportPeriod[];
  by_payment_method: SalesReportPaymentMethod[];
  by_courier: SalesReportCourier[];
}

export interface ProductPerformanceRow {
  product_id: number;
  name: string;
  sku: string;
  units_sold: number;
  revenue_amount: number;
}

export interface LowStockReportRow {
  product_id: number;
  name: string;
  sku: string;
  total_quantity: number;
  total_reserved: number;
  total_available: number;
  low_stock_threshold: number;
}
