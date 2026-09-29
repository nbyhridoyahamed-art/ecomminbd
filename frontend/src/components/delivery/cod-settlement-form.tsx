"use client";

import { useMemo, useState } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";

import { useAllCouriers } from "@/hooks/use-couriers";
import { useShipments } from "@/hooks/use-shipments";
import type { CodSettlementFormValues } from "@/hooks/use-cod-settlements";
import { formatMoney } from "@/lib/money";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";

const moneyField = z.string().refine((v) => /^\d+(\.\d{1,2})?$/.test(v), "Enter a valid amount, e.g. 240.00");

const settlementSchema = z.object({
  amount_received: moneyField,
  note: z.string(),
});

type FormValues = z.infer<typeof settlementSchema>;

interface CodSettlementFormProps {
  storeId: number;
  onSubmit: (values: CodSettlementFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
}

export function CodSettlementForm({ storeId, onSubmit, isPending, serverError }: CodSettlementFormProps) {
  const [courierId, setCourierId] = useState("");
  const [selectedShipmentIds, setSelectedShipmentIds] = useState<number[]>([]);

  const { data: couriersData } = useAllCouriers(storeId);
  const couriers = couriersData?.data ?? [];

  const { data: shipmentsData, isLoading: shipmentsLoading } = useShipments(storeId, {
    page: 1,
    status: "delivered",
    courierId: courierId ? Number(courierId) : null,
  });

  const eligibleShipments = useMemo(
    () => (shipmentsData?.data ?? []).filter((s) => s.order.payment_method === "cod" && !s.cod_settled),
    [shipmentsData],
  );

  const amountExpectedMinor = eligibleShipments
    .filter((s) => selectedShipmentIds.includes(s.id))
    .reduce((sum, s) => sum + (s.cod_amount_collected ?? 0), 0);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(settlementSchema),
    defaultValues: { amount_received: "", note: "" },
  });

  const toggleShipment = (id: number) => {
    setSelectedShipmentIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  };

  const submit = handleSubmit((values) => {
    onSubmit({
      store_id: storeId,
      courier_id: Number(courierId),
      shipment_ids: selectedShipmentIds,
      amount_received: values.amount_received,
      note: values.note || null,
    });
  });

  return (
    <form onSubmit={submit} className="space-y-4">
      {serverError ? (
        <Alert variant="danger">
          <AlertDescription>{serverError}</AlertDescription>
        </Alert>
      ) : null}

      <div className="max-w-xs space-y-1.5">
        <Label htmlFor="cod-settlement-form-courier">Courier</Label>
        <Select
          value={courierId}
          onValueChange={(v) => {
            setCourierId(v);
            setSelectedShipmentIds([]);
          }}
        >
          <SelectTrigger id="cod-settlement-form-courier">
            <SelectValue placeholder="Select courier">{couriers.find((c) => String(c.id) === courierId)?.name}</SelectValue>
          </SelectTrigger>
          <SelectContent>
            {couriers.map((c) => (
              <SelectItem key={c.id} value={String(c.id)}>
                {c.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {courierId ? (
        <div className="space-y-2">
          <Label>Unsettled COD shipments</Label>
          {shipmentsLoading ? (
            <p className="text-sm text-text-muted">Loading...</p>
          ) : eligibleShipments.length === 0 ? (
            <EmptyState title="No unsettled COD shipments" description="This courier has nothing pending settlement." />
          ) : (
            <div className="space-y-1 rounded-lg border border-border p-2">
              {eligibleShipments.map((shipment) => (
                <label key={shipment.id} className="flex items-center justify-between gap-2 rounded px-2 py-1.5 text-sm hover:bg-border/20">
                  <span className="flex items-center gap-2">
                    <Checkbox
                      checked={selectedShipmentIds.includes(shipment.id)}
                      onCheckedChange={() => toggleShipment(shipment.id)}
                    />
                    {shipment.order.order_number} — {shipment.tracking_number}
                  </span>
                  <span className="font-medium text-text-primary">
                    {formatMoney(shipment.cod_amount_collected ?? 0, "BDT")}
                  </span>
                </label>
              ))}
            </div>
          )}
          {selectedShipmentIds.length > 0 ? (
            <p className="text-right text-sm font-medium text-text-primary">
              Expected total: {formatMoney(amountExpectedMinor, "BDT")}
            </p>
          ) : null}
        </div>
      ) : null}

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="amount_received">Amount received</Label>
          <Input id="amount_received" inputMode="decimal" error={Boolean(errors.amount_received)} {...register("amount_received")} />
          {errors.amount_received ? <p className="text-xs text-danger">{errors.amount_received.message}</p> : null}
        </div>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="note">Note (optional)</Label>
        <Textarea id="note" rows={2} {...register("note")} />
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending} disabled={!courierId || selectedShipmentIds.length === 0}>
          Record settlement
        </Button>
      </div>
    </form>
  );
}
