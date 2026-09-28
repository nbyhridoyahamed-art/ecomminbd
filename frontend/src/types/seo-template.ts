export interface SeoTemplate {
  id: number;
  store_id: number;
  entity_type: string;
  title_template: string | null;
  description_template: string | null;
  created_at: string;
  updated_at: string;
}

/** The 7 FQCN strings the backend validates entity_type against via Rule::in — kept in sync with it. */
export const ENTITY_TYPE_OPTIONS = [
  { value: "App\\Models\\Product", label: "Products" },
  { value: "App\\Models\\Category", label: "Categories" },
  { value: "App\\Models\\Brand", label: "Brands" },
  { value: "App\\Models\\Page", label: "Pages" },
  { value: "App\\Models\\BlogPost", label: "Blog Posts" },
  { value: "App\\Models\\BlogCategory", label: "Blog Categories" },
  { value: "App\\Models\\BlogTag", label: "Blog Tags" },
] as const;

export const ENTITY_TYPE_LABELS: Record<string, string> = Object.fromEntries(
  ENTITY_TYPE_OPTIONS.map((option) => [option.value, option.label]),
);
