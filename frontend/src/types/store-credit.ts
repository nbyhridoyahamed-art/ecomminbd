export interface CustomerStoreCredit {
  id: number;
  /** Derived from the ledger entry's own sign — see CustomerStoreCreditResource. */
  type: "issued" | "redeemed";
  amount: number;
  note: string | null;
  created_by: string | null;
  created_at: string;
}
