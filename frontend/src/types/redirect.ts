export interface Redirect {
  id: number;
  store_id: number;
  from_path: string;
  to_path: string;
  status_code: number;
  hits_count: number;
  created_at: string;
  updated_at: string;
}
