export interface Testimonial {
  id: number;
  store_id: number;
  name: string;
  role: string | null;
  quote: string;
  avatar_url: string | null;
  rating: number | null;
  sort_order: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}
