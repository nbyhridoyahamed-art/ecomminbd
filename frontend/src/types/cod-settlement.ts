export interface CodSettlementShipment {
  id: number;
  tracking_number: string;
  order_number: string;
  cod_amount_collected: number;
}

export interface CodSettlement {
  id: number;
  uuid: string;
  settlement_number: string;
  amount_expected: number;
  amount_received: number;
  note: string | null;
  courier: { id: number; name: string };
  shipments: CodSettlementShipment[];
  created_by: string | null;
  created_at: string;
}
