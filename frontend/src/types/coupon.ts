export type CouponDiscountType = "percentage" | "fixed";

export type CouponStatus = "active" | "inactive";

export interface Coupon {
  id: number;
  code: string;
  description: string | null;
  discount_type: CouponDiscountType;
  percentage_value: number | null;
  fixed_amount: number | null;
  currency_code: string;
  minimum_order_amount: number;
  usage_limit: number | null;
  used_count: number;
  per_customer_limit: number | null;
  starts_at: string | null;
  expires_at: string | null;
  status: CouponStatus;
  created_at: string;
}
