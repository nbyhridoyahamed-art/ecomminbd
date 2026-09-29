"use client";

import { useState } from "react";
import { Ban, CheckCircle2, Truck } from "lucide-react";

import { useCancelStockTransfer, useReceiveStockTransfer, useShipStockTransfer } from "@/hooks/use-inventory";
import { Badge, type BadgeProps } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { StockTransfer, StockTransferStatus } from "@/types/inventory";

type BadgeVariant = BadgeProps["variant"];

const STATUS_LABELS: Record<StockTransferStatus, string> = {
  pending: "Pending",
  in_transit: "In transit",
  received: "Received",
  cancelled: "Cancelled",
};

const STATUS_VARIANTS: Record<StockTransferStatus, BadgeVariant> = {
  pending: "neutral",
  in_transit: "info",
  received: "success",
  cancelled: "danger",
};

interface StockTransferStatusCardProps {
  transfer: StockTransfer;
  canUpdate: boolean;
}

export function StockTransferStatusCard({ transfer, canUpdate }: StockTransferStatusCardProps) {
  const [cancelOpen, setCancelOpen] = useState(false);
  const [note, setNote] = useState("");

  const ship = useShipStockTransfer();
  const receive = useReceiveStockTransfer();
  const cancel = useCancelStockTransfer();

  return (
    <Card>
      <CardHeader className="flex flex-row items-start justify-between gap-4">
        <CardTitle className="flex items-center gap-2">
          {transfer.transfer_number}
          <Badge variant={STATUS_VARIANTS[transfer.status]}>{STATUS_LABELS[transfer.status]}</Badge>
        </CardTitle>
        {canUpdate ? (
          <div className="flex flex-wrap gap-2">
            {transfer.status === "pending" ? (
              <>
                <Button size="sm" onClick={() => ship.mutate(transfer.id)} loading={ship.isPending}>
                  <Truck />
                  Mark shipped
                </Button>
                <Button size="sm" variant="outline" onClick={() => setCancelOpen(true)}>
                  <Ban />
                  Cancel
                </Button>
              </>
            ) : null}
            {transfer.status === "in_transit" ? (
              <Button size="sm" onClick={() => receive.mutate(transfer.id)} loading={receive.isPending}>
                <CheckCircle2 />
                Mark received
              </Button>
            ) : null}
          </div>
        ) : null}
      </CardHeader>
      <CardContent className="grid grid-cols-2 gap-4 text-sm">
        <div>
          <p className="text-text-muted">From</p>
          <p className="font-medium text-text-primary">{transfer.from_warehouse.name}</p>
        </div>
        <div>
          <p className="text-text-muted">To</p>
          <p className="font-medium text-text-primary">{transfer.to_warehouse.name}</p>
        </div>
        <div>
          <p className="text-text-muted">Created by</p>
          <p className="font-medium text-text-primary">{transfer.created_by ?? "—"}</p>
        </div>
        <div>
          <p className="text-text-muted">Date</p>
          <p className="font-medium text-text-primary">{new Date(transfer.created_at).toLocaleString()}</p>
        </div>
        {transfer.note ? (
          <div className="col-span-2">
            <p className="text-text-muted">Note</p>
            <p className="text-text-primary">{transfer.note}</p>
          </div>
        ) : null}
      </CardContent>

      <Dialog open={cancelOpen} onOpenChange={setCancelOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Cancel this transfer</DialogTitle>
            <DialogDescription>
              Nothing has shipped yet, so this has no stock impact. This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor="cancel-note">Reason (optional)</Label>
            <Textarea id="cancel-note" rows={2} value={note} onChange={(e) => setNote(e.target.value)} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setCancelOpen(false)}>
              Keep transfer
            </Button>
            <Button
              variant="destructive"
              loading={cancel.isPending}
              onClick={() =>
                cancel.mutate(
                  { id: transfer.id, note: note || undefined },
                  { onSuccess: () => setCancelOpen(false) },
                )
              }
            >
              Confirm cancel
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  );
}
