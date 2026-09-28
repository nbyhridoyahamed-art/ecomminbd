export interface StorefrontStore {
  name: string;
  slug: string;
  currency_code: string;
}

export interface StorefrontProduct {
  id: number;
  name: string;
  slug: string;
  type: "simple" | "variable" | "bundle";
  currency_code: string;
  price: number;
  sale_price: number | null;
  compare_at_price: number | null;
  primary_image_url: string | null;
  featured: boolean;
  in_stock: boolean;
}

export interface StorefrontAttributeValue {
  attribute_name: string;
  value: string;
}

export interface StorefrontVariant {
  id: number;
  sku: string;
  price: number | null;
  sale_price: number | null;
  attribute_values: StorefrontAttributeValue[];
  in_stock: boolean;
}

export interface StorefrontBundleComponent {
  product_name: string;
  product_slug: string;
  quantity: number;
}

export interface StorefrontProductDetail {
  id: number;
  name: string;
  slug: string;
  sku: string;
  type: "simple" | "variable" | "bundle";
  description: string | null;
  short_description: string | null;
  currency_code: string;
  price: number;
  sale_price: number | null;
  compare_at_price: number | null;
  weight: number | null;
  weight_unit: string | null;
  category: { id: number; name: string; slug: string } | null;
  brand: { id: number; name: string; slug: string } | null;
  images: { id: number; url: string; alt_text: string | null; is_primary: boolean }[];
  variants: StorefrontVariant[];
  components: StorefrontBundleComponent[] | null;
  bundle_availability: { total_available: number } | null;
  in_stock: boolean;
  seo_title: string | null;
  seo_description: string | null;
}

export interface StorefrontCategory {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  image_url: string | null;
  children: StorefrontCategory[];
}

export interface StorefrontBrand {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  logo_url: string | null;
}

export interface BdLocation {
  id: number;
  name_en: string;
  name_bn: string;
  code: string;
}

export interface BdDistrict extends BdLocation {
  bd_division_id: number;
}

export interface BdUpazila extends BdLocation {
  bd_district_id: number;
}

export interface StorefrontOrderItem {
  product_name: string;
  product_slug: string;
  product_variant: { sku: string; attribute_values: StorefrontAttributeValue[] } | null;
  quantity: number;
  unit_price: number;
  line_total: number;
}

export interface StorefrontOrder {
  uuid: string;
  order_number: string;
  status: string;
  payment_method: string;
  payment_status: string;
  currency_code: string;
  notes: string | null;
  shipping: {
    recipient_name: string;
    phone: string;
    address_line: string;
    division: string | null;
    district: string | null;
    upazila: string | null;
  };
  items: StorefrontOrderItem[];
  shipping_amount: number;
  discount_amount: number;
  subtotal_amount: number;
  total_amount: number;
  created_at: string;
}

export interface CheckoutItemPayload {
  product_id: number;
  product_variant_id?: number;
  quantity: number;
}

export interface CheckoutPayload {
  customer_name: string;
  customer_phone: string;
  customer_email?: string;
  shipping_recipient_name: string;
  shipping_phone: string;
  shipping_address_line: string;
  shipping_bd_division_id?: number;
  shipping_bd_district_id?: number;
  shipping_bd_upazila_id?: number;
  notes?: string;
  items: CheckoutItemPayload[];
}
