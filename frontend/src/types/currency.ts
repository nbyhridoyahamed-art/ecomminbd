export interface Currency {
  id: number;
  code: string;
  symbol: string;
  name: string;
  decimal_places: number;
  is_default: boolean;
}
