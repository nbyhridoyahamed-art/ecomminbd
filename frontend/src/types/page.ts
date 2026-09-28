export interface Page {
  id: number;
  uuid: string;
  store_id: number;
  title: string;
  slug: string;
  content: string | null;
  meta_title: string | null;
  meta_description: string | null;
  status: "draft" | "published";
  created_by?: string | null;
  created_at: string;
  updated_at: string;
}
