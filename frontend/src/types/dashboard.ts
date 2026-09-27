export interface SalesTrendPoint {
  date: string;
  orders_count: number;
  revenue_amount: number;
}

export interface OrderStatusBreakdown {
  pending: number;
  processing: number;
  shipped: number;
  delivered: number;
  cancelled: number;
}
