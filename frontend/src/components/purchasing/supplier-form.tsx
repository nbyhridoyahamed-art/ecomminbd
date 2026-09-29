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
  payment_terms: z.enum(["", "due_on_receipt", "net_15", "net_30", "net_60"]),
});

type FormValues = z.infer<typeof supplierSchema>;

const STATUS_LABELS: Record<string, string> = { active: "Active", inactive: "Inactive" };

const PAYMENT_TERMS_LABELS: Record<string, string> = {
  due_on_receipt: "Due on receipt",
  net_15: "Net 15",
  net_30: "Net 30",
  net_60: "Net 60",
};

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
      payment_terms: defaultValues?.payment_terms ?? "",
    },
  });

  const status = useWatch({ control, name: "status" });
  const paymentTerms = useWatch({ control, name: "payment_terms" });

  const submit = handleSubmit((values) => {
    onSubmit({
      store_id: storeId,
      name: values.name,
      contact_name: values.contact_name || null,
      email: values.email || null,
      phone: values.phone || null,
      address: values.address || null,
      status: values.status,
      payment_terms: values.payment_terms || null,
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

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="supplier-form-status">Status</Label>
          <Select
            value={status}
            onValueChange={(value) => setValue("status", value as "active" | "inactive", { shouldDirty: true })}
          >
            <SelectTrigger id="supplier-form-status">
              <SelectValue placeholder="Select status">{status ? STATUS_LABELS[status] : undefined}</SelectValue>
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="active">Active</SelectItem>
              <SelectItem value="inactive">Inactive</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="supplier-form-payment-terms">Payment terms (optional)</Label>
          <Select
            value={paymentTerms}
            onValueChange={(value) => setValue("payment_terms", value as FormValues["payment_terms"], { shouldDirty: true })}
          >
            <SelectTrigger id="supplier-form-payment-terms">
              <SelectValue placeholder="Not set">
                {paymentTerms ? PAYMENT_TERMS_LABELS[paymentTerms] : undefined}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="due_on_receipt">Due on receipt</SelectItem>
              <SelectItem value="net_15">Net 15</SelectItem>
              <SelectItem value="net_30">Net 30</SelectItem>
              <SelectItem value="net_60">Net 60</SelectItem>
            </SelectContent>
          </Select>
        </div>
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
