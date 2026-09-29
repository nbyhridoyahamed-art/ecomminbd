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
import type { WarehouseFormValues } from "@/hooks/use-warehouses";
import type { Warehouse } from "@/types/warehouse";

const warehouseSchema = z.object({
  name: z.string().min(1, "Name is required."),
  code: z
    .string()
    .min(1, "Code is required.")
    .regex(/^[A-Z0-9-]+$/, "Code may only contain uppercase letters, numbers, and hyphens."),
  type: z.enum(["main", "branch", "pickup_point", "temporary"]),
  manager_name: z.string(),
  phone: z.string(),
  address_line: z.string(),
  status: z.enum(["active", "inactive"]),
});

type FormValues = z.infer<typeof warehouseSchema>;

const TYPE_LABELS: Record<string, string> = {
  main: "Main",
  branch: "Branch",
  pickup_point: "Pickup point",
  temporary: "Temporary",
};

const STATUS_LABELS: Record<string, string> = { active: "Active", inactive: "Inactive" };

interface WarehouseFormProps {
  storeId: number;
  defaultValues?: Warehouse;
  onSubmit: (values: WarehouseFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
  submitLabel: string;
}

export function WarehouseForm({
  storeId,
  defaultValues,
  onSubmit,
  isPending,
  serverError,
  submitLabel,
}: WarehouseFormProps) {
  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(warehouseSchema),
    defaultValues: {
      name: defaultValues?.name ?? "",
      code: defaultValues?.code ?? "",
      type: defaultValues?.type ?? "main",
      manager_name: defaultValues?.manager_name ?? "",
      phone: defaultValues?.phone ?? "",
      address_line: defaultValues?.address_line ?? "",
      status: defaultValues?.status ?? "active",
    },
  });

  const type = useWatch({ control, name: "type" });
  const status = useWatch({ control, name: "status" });
  const codeField = register("code");

  const submit = handleSubmit((values) => {
    onSubmit({
      store_id: storeId,
      name: values.name,
      code: values.code,
      type: values.type,
      manager_name: values.manager_name || null,
      phone: values.phone || null,
      address_line: values.address_line || null,
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
          <Label htmlFor="name">Name</Label>
          <Input id="name" error={Boolean(errors.name)} {...register("name")} />
          {errors.name ? <p className="text-xs text-danger">{errors.name.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="code">Code</Label>
          <Input
            id="code"
            error={Boolean(errors.code)}
            {...codeField}
            onChange={(event) => {
              event.target.value = event.target.value.toUpperCase();
              codeField.onChange(event);
            }}
          />
          {errors.code ? <p className="text-xs text-danger">{errors.code.message}</p> : null}
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="warehouse-form-type">Type</Label>
          <Select
            value={type}
            onValueChange={(value) => setValue("type", value as FormValues["type"], { shouldDirty: true })}
          >
            <SelectTrigger id="warehouse-form-type">
              <SelectValue placeholder="Select type">{type ? TYPE_LABELS[type] : undefined}</SelectValue>
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="main">Main</SelectItem>
              <SelectItem value="branch">Branch</SelectItem>
              <SelectItem value="pickup_point">Pickup point</SelectItem>
              <SelectItem value="temporary">Temporary</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="warehouse-form-status">Status</Label>
          <Select
            value={status}
            onValueChange={(value) => setValue("status", value as "active" | "inactive", { shouldDirty: true })}
          >
            <SelectTrigger id="warehouse-form-status">
              <SelectValue placeholder="Select status">{status ? STATUS_LABELS[status] : undefined}</SelectValue>
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="active">Active</SelectItem>
              <SelectItem value="inactive">Inactive</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="manager_name">Manager name</Label>
          <Input id="manager_name" {...register("manager_name")} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="phone">Phone</Label>
          <Input id="phone" {...register("phone")} />
        </div>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="address_line">Address</Label>
        <Textarea id="address_line" rows={2} {...register("address_line")} />
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
