export interface ProductAttributeValue {
  id: number;
  value: string;
  slug: string;
  sort_order: number;
  created_at: string;
  updated_at: string;
}

export interface ProductAttribute {
  id: number;
  uuid: string;
  store_id: number;
  name: string;
  slug: string;
  values: ProductAttributeValue[];
  created_at: string;
  updated_at: string;
}
