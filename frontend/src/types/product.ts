export interface ProductImage {
  id: number;
  path: string;
  url: string;
  alt_text: string | null;
  sort_order: number;
  is_primary: boolean;
}

export interface Product {
  id: number;
  uuid: string;
  store_id: number;
  category: { id: number; name: string } | null;
  brand: { id: number; name: string } | null;
  category_id: number | null;
  brand_id: number | null;

  name: string;
  slug: string;
  sku: string;
  barcode: string | null;
  type: string;

  description: string | null;
  short_description: string | null;

  currency_code: string;
  price: number;
  sale_price: number | null;
  cost_price: number | null;
  compare_at_price: number | null;

  weight: number | null;
  weight_unit: string | null;

  track_stock: boolean;
  low_stock_threshold: number | null;

  status: "draft" | "active" | "archived";
  featured: boolean;

  seo_title: string | null;
  seo_description: string | null;
  focus_keyword: string | null;

  images: ProductImage[];

  created_at: string;
  updated_at: string;
}
