export interface Media {
  id: number;
  uuid: string;
  path: string;
  url: string;
  filename: string;
  mime_type: string;
  size: number;
  alt_text: string | null;
  uploaded_by?: string | null;
  created_at: string;
}
