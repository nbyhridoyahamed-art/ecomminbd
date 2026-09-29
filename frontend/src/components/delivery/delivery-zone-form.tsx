"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useFieldArray, useForm, useWatch } from "react-hook-form";
import { Plus, Trash2 } from "lucide-react";
import { z } from "zod";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useDistricts, useDivisions } from "@/hooks/use-locations";
import type { DeliveryZoneFormValues } from "@/hooks/use-delivery-zones";
import type { DeliveryZone } from "@/types/delivery-zone";

const amountField = z.string().refine((v) => /^\d+(\.\d{1,2})?$/.test(v), "Enter a valid amount, e.g. 60 or 0");

const zoneSchema = z
  .object({
    name: z.string().min(1, "Name is required."),
    bd_division_id: z.string(),
    bd_district_id: z.string(),
    status: z.enum(["active", "inactive"]),
    rates: z.array(z.object({ min_order_subtotal: amountField, rate_amount: amountField })).min(1),
  })
  .superRefine((data, ctx) => {
    const subtotals = data.rates.map((r) => Number(r.min_order_subtotal));

    if (!subtotals.includes(0)) {
      ctx.addIssue({ code: "custom", path: ["rates"], message: "One tier must start at a minimum order amount of 0." });
    }

    if (new Set(subtotals).size !== subtotals.length) {
      ctx.addIssue({ code: "custom", path: ["rates"], message: "Each tier must start at a different minimum order amount." });
    }
  });

type FormValues = z.infer<typeof zoneSchema>;

const STATUS_LABELS: Record<string, string> = { active: "Active", inactive: "Inactive" };

interface DeliveryZoneFormProps {
  storeId: number;
  defaultValues?: DeliveryZone;
  onSubmit: (values: DeliveryZoneFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
  submitLabel: string;
}

export function DeliveryZoneForm({ storeId, defaultValues, onSubmit, isPending, serverError, submitLabel }: DeliveryZoneFormProps) {
  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(zoneSchema),
    defaultValues: {
      name: defaultValues?.name ?? "",
      bd_division_id: defaultValues?.bd_division_id ? String(defaultValues.bd_division_id) : "none",
      bd_district_id: defaultValues?.bd_district_id ? String(defaultValues.bd_district_id) : "none",
      status: defaultValues?.status ?? "active",
      rates: defaultValues?.rates.length
        ? defaultValues.rates.map((r) => ({ min_order_subtotal: String(r.min_order_subtotal), rate_amount: String(r.rate_amount) }))
        : [{ min_order_subtotal: "0", rate_amount: "" }],
    },
  });

  const { fields, append, remove } = useFieldArray({ control, name: "rates" });

  const divisionId = useWatch({ control, name: "bd_division_id" });
  const districtId = useWatch({ control, name: "bd_district_id" });
  const status = useWatch({ control, name: "status" });

  const { data: divisions } = useDivisions();
  const { data: districts } = useDistricts(divisionId !== "none" ? Number(divisionId) : null);

  const submit = handleSubmit((values) => {
    onSubmit({
      store_id: storeId,
      name: values.name,
      bd_division_id: values.bd_division_id !== "none" ? Number(values.bd_division_id) : null,
      bd_district_id: values.bd_district_id !== "none" ? Number(values.bd_district_id) : null,
      status: values.status,
      rates: values.rates,
    });
  });

  return (
    <form onSubmit={submit} className="space-y-4">
      {serverError ? (
        <Alert variant="danger">
          <AlertDescription>{serverError}</AlertDescription>
        </Alert>
      ) : null}

      <div className="space-y-1.5">
        <Label htmlFor="zone-name">Name</Label>
        <Input id="zone-name" placeholder="e.g. Inside Dhaka" error={Boolean(errors.name)} {...register("name")} />
        {errors.name ? <p className="text-xs text-danger">{errors.name.message}</p> : null}
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="zone-division">Division</Label>
          <Select
            value={divisionId}
            onValueChange={(v) => {
              setValue("bd_division_id", v, { shouldDirty: true });
              setValue("bd_district_id", "none");
            }}
          >
            <SelectTrigger id="zone-division">
              <SelectValue placeholder="Select">
                {divisionId === "none" ? "None (store default)" : divisions?.find((d) => String(d.id) === divisionId)?.name_en}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="none">None (store default)</SelectItem>
              {divisions?.map((d) => (
                <SelectItem key={d.id} value={String(d.id)}>
                  {d.name_en}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <p className="text-xs text-text-secondary">Leave unset to make this the store&apos;s fallback zone.</p>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="zone-district">District</Label>
          <Select value={districtId} onValueChange={(v) => setValue("bd_district_id", v, { shouldDirty: true })}>
            <SelectTrigger id="zone-district" disabled={divisionId === "none"}>
              <SelectValue placeholder="Select">
                {districtId === "none" ? "None (whole division)" : districts?.find((d) => String(d.id) === districtId)?.name_en}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="none">None (whole division)</SelectItem>
              {districts?.map((d) => (
                <SelectItem key={d.id} value={String(d.id)}>
                  {d.name_en}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="zone-status">Status</Label>
          <Select value={status} onValueChange={(v) => setValue("status", v as "active" | "inactive", { shouldDirty: true })}>
            <SelectTrigger id="zone-status">
              <SelectValue placeholder="Select status">{STATUS_LABELS[status]}</SelectValue>
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="active">Active</SelectItem>
              <SelectItem value="inactive">Inactive</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="space-y-2">
        <Label>Rate tiers</Label>
        <p className="text-xs text-text-secondary">
          The tier with the highest minimum an order&apos;s subtotal still meets is the one charged — add a second tier at 0 rate to
          offer free shipping above a threshold.
        </p>

        <div className="space-y-2">
          {fields.map((field, index) => (
            <div key={field.id} className="flex items-end gap-2">
              <div className="flex-1 space-y-1.5">
                <Label htmlFor={`zone-rate-min-${index}`} className="text-xs">
                  Minimum order subtotal
                </Label>
                <Input
                  id={`zone-rate-min-${index}`}
                  inputMode="decimal"
                  error={Boolean(errors.rates?.[index]?.min_order_subtotal)}
                  {...register(`rates.${index}.min_order_subtotal` as const)}
                />
              </div>
              <div className="flex-1 space-y-1.5">
                <Label htmlFor={`zone-rate-amount-${index}`} className="text-xs">
                  Shipping charge
                </Label>
                <Input
                  id={`zone-rate-amount-${index}`}
                  inputMode="decimal"
                  error={Boolean(errors.rates?.[index]?.rate_amount)}
                  {...register(`rates.${index}.rate_amount` as const)}
                />
              </div>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Remove tier"
                disabled={fields.length === 1}
                onClick={() => remove(index)}
              >
                <Trash2 className="text-danger" />
              </Button>
            </div>
          ))}
        </div>

        {errors.rates?.message || errors.rates?.root?.message ? (
          <p className="text-xs text-danger">{errors.rates?.message ?? errors.rates?.root?.message}</p>
        ) : null}

        <Button type="button" variant="outline" size="sm" onClick={() => append({ min_order_subtotal: "", rate_amount: "" })}>
          <Plus />
          Add tier
        </Button>
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
