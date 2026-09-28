"use client";

import { useState } from "react";
import { Ban, CheckCircle2, CreditCard, Truck } from "lucide-react";

import {
  useApprovePurchaseReturn,
  useCreditPurchaseReturn,
  useRejectPurchaseReturn,
  useShipBackPurchaseReturn,
} from "@/hooks/use-purchase-returns";
import { formatMoney } from "@/lib/money";
import { Badge, type BadgeProps } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { PurchaseReturn, PurchaseReturnStatus } from "@/types/purchase-return";

type BadgeVariant = BadgeProps["variant"];

const STATUS_LABELS: Record<PurchaseReturnStatus, string> = {
  requested: "Requested",
  approved: "Approved",
  rejected: "Rejected",
  shipped_back: "Shipped back",
  credited: "Credited",
};

const STATUS_VARIANTS: Record<PurchaseReturnStatus, BadgeVariant> = {
  requested: "neutral",
  approved: "info",
  rejected: "danger",
  shipped_back: "warning",
  credited: "success",
};

interface PurchaseReturnStatusCardProps {
  purchaseReturn: PurchaseReturn;
  currencyCode: string;
  canUpdate: boolean;
}

export function PurchaseReturnStatusCard({ purchaseReturn, currencyCode, canUpdate }: PurchaseReturnStatusCardProps) {
  const [rejectOpen, setRejectOpen] = useState(false);
  const [creditOpen, setCreditOpen] = useState(false);
  const [note, setNote] = useState("");
  const [creditAmount, setCreditAmount] = useState("");

  const approve = useApprovePurchaseReturn(purchaseReturn.id);
  const reject = useRejectPurchaseReturn(purchaseReturn.id);
  const shipBack = useShipBackPurchaseReturn(purchaseReturn.id);
  const credit = useCreditPurchaseReturn(purchaseReturn.id);

  const suggestedCredit = purchaseReturn.items.reduce((sum, item) => sum + item.line_total, 0);

  const openCreditDialog = () => {
    setCreditAmount(suggestedCredit.toFixed(2));
    setCreditOpen(true);
  };

  return (
    <Card>
      <CardHeader className="flex flex-row items-start justify-between gap-4">
        <div>
          <CardTitle className="flex items-center gap-2">
            {purchaseReturn.return_number}
            <Badge variant={STATUS_VARIANTS[purchaseReturn.status]}>{STATUS_LABELS[purchaseReturn.status]}</Badge>
          </CardTitle>
        </div>
        {canUpdate ? (
          <div className="flex flex-wrap gap-2">
            {purchaseReturn.status === "requested" ? (
              <Button size="sm" onClick={() => approve.mutate(undefined)} loading={approve.isPending}>
                <CheckCircle2 />
                Approve
              </Button>
            ) : null}
            {purchaseReturn.status === "requested" || purchaseReturn.status === "approved" ? (
              <Button size="sm" variant="outline" onClick={() => setRejectOpen(true)}>
                <Ban />
                Reject
              </Button>
            ) : null}
            {purchaseReturn.status === "approved" ? (
              <Button size="sm" onClick={() => shipBack.mutate(undefined)} loading={shipBack.isPending}>
                <Truck />
                Ship back
              </Button>
            ) : null}
            {purchaseReturn.status === "shipped_back" ? (
              <Button size="sm" onClick={openCreditDialog}>
                <CreditCard />
                Credit
              </Button>
            ) : null}
          </div>
        ) : null}
      </CardHeader>
      <CardContent className="space-y-3 text-sm">
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <div>
            <p className="text-text-muted">Reason</p>
            <p className="font-medium text-text-primary">{purchaseReturn.reason ?? "—"}</p>
          </div>
          {purchaseReturn.credit_amount !== null ? (
            <div>
              <p className="text-text-muted">Credit amount</p>
              <p className="font-medium text-text-primary">{formatMoney(purchaseReturn.credit_amount, currencyCode)}</p>
            </div>
          ) : null}
        </div>
        {purchaseReturn.note ? (
          <div>
            <p className="text-text-muted">Note</p>
            <p className="text-text-primary">{purchaseReturn.note}</p>
          </div>
        ) : null}
      </CardContent>

      <Dialog open={rejectOpen} onOpenChange={setRejectOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Reject return</DialogTitle>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor="reject-note">Reason (optional)</Label>
            <Textarea id="reject-note" rows={2} value={note} onChange={(e) => setNote(e.target.value)} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setRejectOpen(false)}>
              Cancel
            </Button>
            <Button
              variant="destructive"
              loading={reject.isPending}
              onClick={() => reject.mutate({ note: note || undefined }, { onSuccess: () => setRejectOpen(false) })}
            >
              Confirm reject
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={creditOpen} onOpenChange={setCreditOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Credit return</DialogTitle>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor="credit-amount">Credit amount</Label>
            <Input
              id="credit-amount"
              inputMode="decimal"
              value={creditAmount}
              onChange={(e) => setCreditAmount(e.target.value)}
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setCreditOpen(false)}>
              Cancel
            </Button>
            <Button
              loading={credit.isPending}
              onClick={() =>
                credit.mutate(
                  { credit_amount: creditAmount || undefined },
                  { onSuccess: () => setCreditOpen(false) },
                )
              }
            >
              Confirm credit
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  );
}
