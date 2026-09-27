"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { CourierFormValues } from "@/hooks/use-couriers";
import type { Courier } from "@/types/courier";

const courierSchema = z.object({
  name: z.string().min(1, "Name is required."),
  contact_name: z.string(),
  email: z.string().refine((v) => v === "" || z.string().email().safeParse(v).success, "Enter a valid email address."),
  phone: z.string(),
  tracking_url_template: z.string(),
  status: z.enum(["active", "inactive"]),
});

type FormValues = z.infer<typeof courierSchema>;

const STATUS_LABELS: Record<string, string> = { active: "Active", inactive: "Inactive" };

interface CourierFormProps {
  storeId: number;
  defaultValues?: Courier;
  onSubmit: (values: CourierFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
  submitLabel: string;
}

export function CourierForm({ storeId, defaultValues, onSubmit, isPending, serverError, submitLabel }: CourierFormProps) {
  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(courierSchema),
    defaultValues: {
      name: defaultValues?.name ?? "",
      contact_name: defaultValues?.contact_name ?? "",
      email: defaultValues?.email ?? "",
      phone: defaultValues?.phone ?? "",
      tracking_url_template: defaultValues?.tracking_url_template ?? "",
      status: defaultValues?.status ?? "active",
    },
  });

  const status = useWatch({ control, name: "status" });

  const submit = handleSubmit((values) => {
    onSubmit({
      store_id: storeId,
      name: values.name,
      contact_name: values.contact_name || null,
      email: values.email || null,
      phone: values.phone || null,
      tracking_url_template: values.tracking_url_template || null,
      status: values.status,
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
          <Label htmlFor="name">Courier name</Label>
          <Input id="name" error={Boolean(errors.name)} {...register("name")} />
          {errors.name ? <p className="text-xs text-danger">{errors.name.message}</p> : null}
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="contact_name">Contact person</Label>
          <Input id="contact_name" {...register("contact_name")} />
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="email">Email</Label>
          <Input id="email" type="email" error={Boolean(errors.email)} {...register("email")} />
          {errors.email ? <p className="text-xs text-danger">{errors.email.message}</p> : null}
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="phone">Phone</Label>
          <Input id="phone" {...register("phone")} />
        </div>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="tracking_url_template">Tracking URL template (optional)</Label>
        <Input
          id="tracking_url_template"
          placeholder="https://courier.example.com/track/{tracking_number}"
          {...register("tracking_url_template")}
        />
        <p className="text-xs text-text-muted">
          {"{tracking_number}"} is replaced with the shipment&apos;s tracking number.
        </p>
      </div>

      <div className="max-w-48 space-y-1.5">
        <Label>Status</Label>
        <Select
          value={status}
          onValueChange={(value) => setValue("status", value as "active" | "inactive", { shouldDirty: true })}
        >
          <SelectTrigger>
            <SelectValue placeholder="Select status">{status ? STATUS_LABELS[status] : undefined}</SelectValue>
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="inactive">Inactive</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
