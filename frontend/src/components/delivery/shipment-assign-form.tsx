"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";

import { useAllCouriers } from "@/hooks/use-couriers";
import type { ShipmentFormValues } from "@/hooks/use-shipments";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

const moneyField = z.string().refine((v) => v === "" || /^\d+(\.\d{1,2})?$/.test(v), "Enter a valid amount, e.g. 60.00");

const shipmentSchema = z.object({
  courier_id: z.string().min(1, "Select a courier."),
  tracking_number: z.string().min(1, "Tracking number is required."),
  delivery_charge: moneyField,
});

type FormValues = z.infer<typeof shipmentSchema>;

interface ShipmentAssignFormProps {
  storeId: number;
  onSubmit: (values: ShipmentFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
}

export function ShipmentAssignForm({ storeId, onSubmit, isPending, serverError }: ShipmentAssignFormProps) {
  const { data: couriersData } = useAllCouriers(storeId);
  const couriers = couriersData?.data ?? [];

  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(shipmentSchema),
    defaultValues: { courier_id: "", tracking_number: "", delivery_charge: "" },
  });

  const courierId = useWatch({ control, name: "courier_id" });

  const submit = handleSubmit((values) => {
    onSubmit({
      courier_id: Number(values.courier_id),
      tracking_number: values.tracking_number,
      delivery_charge: values.delivery_charge || null,
    });
  });

  return (
    <form onSubmit={submit} className="space-y-4">
      {serverError ? (
        <Alert variant="danger">
          <AlertDescription>{serverError}</AlertDescription>
        </Alert>
      ) : null}

      <div className="grid grid-cols-3 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="shipment-assign-form-courier">Courier</Label>
          <Select value={courierId} onValueChange={(v) => setValue("courier_id", v, { shouldValidate: true })}>
            <SelectTrigger id="shipment-assign-form-courier">
              <SelectValue placeholder="Select courier">
                {couriers.find((c) => String(c.id) === courierId)?.name}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {couriers.map((c) => (
                <SelectItem key={c.id} value={String(c.id)}>
                  {c.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.courier_id ? <p className="text-xs text-danger">{errors.courier_id.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="tracking_number">Tracking number</Label>
          <Input id="tracking_number" error={Boolean(errors.tracking_number)} {...register("tracking_number")} />
          {errors.tracking_number ? <p className="text-xs text-danger">{errors.tracking_number.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="delivery_charge">Delivery charge (optional)</Label>
          <Input
            id="delivery_charge"
            inputMode="decimal"
            placeholder="Defaults to the order's shipping charge"
            error={Boolean(errors.delivery_charge)}
            {...register("delivery_charge")}
          />
          {errors.delivery_charge ? <p className="text-xs text-danger">{errors.delivery_charge.message}</p> : null}
        </div>
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          Assign courier
        </Button>
      </div>
    </form>
  );
}
