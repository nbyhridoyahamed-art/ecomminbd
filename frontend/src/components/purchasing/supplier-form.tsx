"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { SupplierFormValues } from "@/hooks/use-suppliers";
import type { Supplier } from "@/types/supplier";

const supplierSchema = z.object({
  name: z.string().min(1, "Name is required."),
  contact_name: z.string(),
  email: z.string().refine((v) => v === "" || z.string().email().safeParse(v).success, "Enter a valid email address."),
  phone: z.string(),
  address: z.string(),
  status: z.enum(["active", "inactive"]),
});

type FormValues = z.infer<typeof supplierSchema>;

const STATUS_LABELS: Record<string, string> = { active: "Active", inactive: "Inactive" };

interface SupplierFormProps {
  storeId: number;
  defaultValues?: Supplier;
  onSubmit: (values: SupplierFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
  submitLabel: string;
}

export function SupplierForm({ storeId, defaultValues, onSubmit, isPending, serverError, submitLabel }: SupplierFormProps) {
  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(supplierSchema),
    defaultValues: {
      name: defaultValues?.name ?? "",
      contact_name: defaultValues?.contact_name ?? "",
      email: defaultValues?.email ?? "",
      phone: defaultValues?.phone ?? "",
      address: defaultValues?.address ?? "",
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
      address: values.address || null,
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
          <Label htmlFor="name">Supplier name</Label>
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

      <div className="space-y-1.5">
        <Label htmlFor="address">Address</Label>
        <Textarea id="address" rows={2} {...register("address")} />
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
