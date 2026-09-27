"use client";

import { useState } from "react";
import { Ban, CheckCircle2, PackageCheck, RotateCcw, Truck } from "lucide-react";

import {
  useDeliveredShipment,
  useFailedShipment,
  useInTransitShipment,
  usePickedUpShipment,
  useReturnedShipment,
} from "@/hooks/use-shipments";
import { formatMoney } from "@/lib/money";
import { Badge, type BadgeProps } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { Shipment, ShipmentStatus } from "@/types/shipment";

type BadgeVariant = BadgeProps["variant"];

const STATUS_LABELS: Record<ShipmentStatus, string> = {
  pending_pickup: "Pending pickup",
  picked_up: "Picked up",
  in_transit: "In transit",
  delivered: "Delivered",
  failed_delivery: "Failed delivery",
  returned_to_seller: "Returned to seller",
};

const STATUS_VARIANTS: Record<ShipmentStatus, BadgeVariant> = {
  pending_pickup: "neutral",
  picked_up: "info",
  in_transit: "info",
  delivered: "success",
  failed_delivery: "danger",
  returned_to_seller: "warning",
};

interface ShipmentStatusCardProps {
  shipment: Shipment;
  canUpdate: boolean;
}

export function ShipmentStatusCard({ shipment, canUpdate }: ShipmentStatusCardProps) {
  const [deliverOpen, setDeliverOpen] = useState(false);
  const [failOpen, setFailOpen] = useState(false);
  const [returnOpen, setReturnOpen] = useState(false);
  const [codAmount, setCodAmount] = useState("");
  const [note, setNote] = useState("");

  const pickedUp = usePickedUpShipment(shipment.id);
  const inTransit = useInTransitShipment(shipment.id);
  const delivered = useDeliveredShipment(shipment.id);
  const failed = useFailedShipment(shipment.id);
  const returned = useReturnedShipment(shipment.id);

  const isCod = shipment.order.payment_method === "cod";

  return (
    <Card>
      <CardHeader className="flex flex-row items-start justify-between gap-4">
        <div>
          <CardTitle className="flex items-center gap-2">
            {shipment.tracking_number}
            <Badge variant={STATUS_VARIANTS[shipment.status]}>{STATUS_LABELS[shipment.status]}</Badge>
          </CardTitle>
        </div>
        {canUpdate ? (
          <div className="flex flex-wrap gap-2">
            {shipment.status === "pending_pickup" ? (
              <Button size="sm" onClick={() => pickedUp.mutate(undefined)} loading={pickedUp.isPending}>
                <PackageCheck />
                Mark picked up
              </Button>
            ) : null}
            {shipment.status === "picked_up" ? (
              <Button size="sm" onClick={() => inTransit.mutate(undefined)} loading={inTransit.isPending}>
                <Truck />
                Mark in transit
              </Button>
            ) : null}
            {(shipment.status === "picked_up" || shipment.status === "in_transit") ? (
              <>
                <Button size="sm" onClick={() => setDeliverOpen(true)}>
                  <CheckCircle2 />
                  Mark delivered
                </Button>
                <Button size="sm" variant="outline" onClick={() => setFailOpen(true)}>
                  <Ban />
                  Mark failed
                </Button>
              </>
            ) : null}
            {shipment.status === "failed_delivery" ? (
              <Button size="sm" variant="outline" onClick={() => setReturnOpen(true)}>
                <RotateCcw />
                Mark returned to seller
              </Button>
            ) : null}
          </div>
        ) : null}
      </CardHeader>
      <CardContent className="space-y-3 text-sm">
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <div>
            <p className="text-text-muted">Courier</p>
            <p className="font-medium text-text-primary">{shipment.courier.name}</p>
          </div>
          <div>
            <p className="text-text-muted">Delivery charge</p>
            <p className="font-medium text-text-primary">{formatMoney(shipment.delivery_charge, "BDT")}</p>
          </div>
          {shipment.cod_amount_collected !== null ? (
            <div>
              <p className="text-text-muted">COD collected</p>
              <p className="font-medium text-text-primary">
                {formatMoney(shipment.cod_amount_collected, "BDT")}
                {shipment.cod_settled ? <span className="ml-1 text-xs text-success">(settled)</span> : null}
              </p>
            </div>
          ) : null}
          {shipment.tracking_url ? (
            <div>
              <p className="text-text-muted">Track</p>
              <a
                href={shipment.tracking_url}
                target="_blank"
                rel="noreferrer"
                className="font-medium text-primary hover:underline"
              >
                Open tracking page
              </a>
            </div>
          ) : null}
        </div>
        {shipment.notes ? (
          <div>
            <p className="text-text-muted">Notes</p>
            <p className="text-text-primary">{shipment.notes}</p>
          </div>
        ) : null}
      </CardContent>

      <Dialog open={deliverOpen} onOpenChange={setDeliverOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Mark shipment delivered</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            {isCod ? (
              <div className="space-y-1.5">
                <Label htmlFor="cod-amount">COD amount collected</Label>
                <Input
                  id="cod-amount"
                  inputMode="decimal"
                  placeholder="Leave blank to use the order total"
                  value={codAmount}
                  onChange={(e) => setCodAmount(e.target.value)}
                />
              </div>
            ) : null}
            <div className="space-y-1.5">
              <Label htmlFor="deliver-note">Note (optional)</Label>
              <Textarea id="deliver-note" rows={2} value={note} onChange={(e) => setNote(e.target.value)} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDeliverOpen(false)}>
              Cancel
            </Button>
            <Button
              loading={delivered.isPending}
              onClick={() =>
                delivered.mutate(
                  { cod_amount_collected: isCod && codAmount ? codAmount : undefined, note: note || undefined },
                  { onSuccess: () => setDeliverOpen(false) },
                )
              }
            >
              Confirm delivered
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={failOpen} onOpenChange={setFailOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Mark as a failed delivery</DialogTitle>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor="fail-note">Reason (optional)</Label>
            <Textarea id="fail-note" rows={2} value={note} onChange={(e) => setNote(e.target.value)} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFailOpen(false)}>
              Cancel
            </Button>
            <Button
              variant="destructive"
              loading={failed.isPending}
              onClick={() => failed.mutate({ note: note || undefined }, { onSuccess: () => setFailOpen(false) })}
            >
              Confirm failed delivery
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={returnOpen} onOpenChange={setReturnOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Mark returned to seller</DialogTitle>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor="return-note">Note (optional)</Label>
            <Textarea id="return-note" rows={2} value={note} onChange={(e) => setNote(e.target.value)} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setReturnOpen(false)}>
              Cancel
            </Button>
            <Button
              loading={returned.isPending}
              onClick={() => returned.mutate({ note: note || undefined }, { onSuccess: () => setReturnOpen(false) })}
            >
              Confirm returned
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  );
}
