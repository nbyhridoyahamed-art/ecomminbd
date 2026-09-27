"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useDistricts, useDivisions, useUpazilas } from "@/hooks/use-locations";
import type { CustomerAddressFormValues } from "@/hooks/use-customers";
import type { CustomerAddress } from "@/types/customer";

const addressSchema = z.object({
  label: z.string(),
  recipient_name: z.string().min(1, "Recipient name is required."),
  phone: z.string().min(1, "Phone is required."),
  address_line: z.string().min(1, "Address is required."),
  bd_division_id: z.string(),
  bd_district_id: z.string(),
  bd_upazila_id: z.string(),
  is_default: z.boolean(),
});

type FormValues = z.infer<typeof addressSchema>;

interface CustomerAddressFormProps {
  defaultValues?: CustomerAddress;
  onSubmit: (values: CustomerAddressFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
  submitLabel: string;
}

export function CustomerAddressForm({
  defaultValues,
  onSubmit,
  isPending,
  serverError,
  submitLabel,
}: CustomerAddressFormProps) {
  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(addressSchema),
    defaultValues: {
      label: defaultValues?.label ?? "",
      recipient_name: defaultValues?.recipient_name ?? "",
      phone: defaultValues?.phone ?? "",
      address_line: defaultValues?.address_line ?? "",
      bd_division_id: defaultValues?.bd_division_id ? String(defaultValues.bd_division_id) : "",
      bd_district_id: defaultValues?.bd_district_id ? String(defaultValues.bd_district_id) : "",
      bd_upazila_id: defaultValues?.bd_upazila_id ? String(defaultValues.bd_upazila_id) : "",
      is_default: defaultValues?.is_default ?? false,
    },
  });

  const divisionId = useWatch({ control, name: "bd_division_id" });
  const districtId = useWatch({ control, name: "bd_district_id" });
  const upazilaId = useWatch({ control, name: "bd_upazila_id" });
  const isDefault = useWatch({ control, name: "is_default" });

  const { data: divisions } = useDivisions();
  const { data: districts } = useDistricts(divisionId ? Number(divisionId) : null);
  const { data: upazilas } = useUpazilas(districtId ? Number(districtId) : null);

  const submit = handleSubmit((values) => {
    onSubmit({
      label: values.label || null,
      recipient_name: values.recipient_name,
      phone: values.phone,
      address_line: values.address_line,
      bd_division_id: values.bd_division_id ? Number(values.bd_division_id) : null,
      bd_district_id: values.bd_district_id ? Number(values.bd_district_id) : null,
      bd_upazila_id: values.bd_upazila_id ? Number(values.bd_upazila_id) : null,
      is_default: values.is_default,
    });
  });

  return (
    <form onSubmit={submit} className="space-y-4">
      {serverError ? (
        <Alert variant="danger">
          <AlertDescription>{serverError}</AlertDescription>
        </Alert>
      ) : null}

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="address_recipient_name">Recipient name</Label>
          <Input
            id="address_recipient_name"
            error={Boolean(errors.recipient_name)}
            {...register("recipient_name")}
          />
          {errors.recipient_name ? <p className="text-xs text-danger">{errors.recipient_name.message}</p> : null}
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="address_phone">Phone</Label>
          <Input id="address_phone" error={Boolean(errors.phone)} {...register("phone")} />
          {errors.phone ? <p className="text-xs text-danger">{errors.phone.message}</p> : null}
        </div>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="address_label">Label (optional)</Label>
        <Input id="address_label" placeholder="Home, Office, ..." {...register("label")} />
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="address_line">Address</Label>
        <Input id="address_line" error={Boolean(errors.address_line)} {...register("address_line")} />
        {errors.address_line ? <p className="text-xs text-danger">{errors.address_line.message}</p> : null}
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="space-y-1.5">
          <Label>Division</Label>
          <Select
            value={divisionId}
            onValueChange={(v) => {
              setValue("bd_division_id", v);
              setValue("bd_district_id", "");
              setValue("bd_upazila_id", "");
            }}
          >
            <SelectTrigger>
              <SelectValue placeholder="Select">
                {divisions?.find((d) => String(d.id) === divisionId)?.name_en}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {divisions?.map((d) => (
                <SelectItem key={d.id} value={String(d.id)}>
                  {d.name_en}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label>District</Label>
          <Select
            value={districtId}
            onValueChange={(v) => {
              setValue("bd_district_id", v);
              setValue("bd_upazila_id", "");
            }}
          >
            <SelectTrigger disabled={!divisionId}>
              <SelectValue placeholder="Select">
                {districts?.find((d) => String(d.id) === districtId)?.name_en}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {districts?.map((d) => (
                <SelectItem key={d.id} value={String(d.id)}>
                  {d.name_en}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label>Upazila</Label>
          <Select value={upazilaId} onValueChange={(v) => setValue("bd_upazila_id", v)}>
            <SelectTrigger disabled={!districtId}>
              <SelectValue placeholder="Select">
                {upazilas?.find((u) => String(u.id) === upazilaId)?.name_en}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {upazilas?.map((u) => (
                <SelectItem key={u.id} value={String(u.id)}>
                  {u.name_en}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="flex items-center gap-2">
        <Checkbox
          id="is_default"
          checked={isDefault}
          onCheckedChange={(checked) => setValue("is_default", checked === true)}
        />
        <Label htmlFor="is_default" className="font-normal">
          Set as default address
        </Label>
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
