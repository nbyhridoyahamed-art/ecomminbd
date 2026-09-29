"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { CouponFormValues } from "@/hooks/use-coupons";
import type { Coupon } from "@/types/coupon";

const moneyField = z.string().refine((v) => v === "" || /^\d+(\.\d{1,2})?$/.test(v), "Enter a valid amount, e.g. 199.99");
const intField = z.string().refine((v) => v === "" || (Number.isInteger(Number(v)) && Number(v) >= 1), "Enter a whole number of at least 1.");

const couponSchema = z
  .object({
    code: z.string().min(1, "Code is required."),
    description: z.string(),
    discount_type: z.enum(["percentage", "fixed"]),
    percentage_value: z.string(),
    fixed_amount: moneyField,
    minimum_order_amount: moneyField,
    usage_limit: intField,
    per_customer_limit: intField,
    starts_at: z.string(),
    expires_at: z.string(),
    status: z.enum(["active", "inactive"]),
  })
  .superRefine((data, ctx) => {
    if (data.discount_type === "percentage") {
      const value = Number(data.percentage_value);
      if (!data.percentage_value || !Number.isInteger(value) || value < 1 || value > 100) {
        ctx.addIssue({ code: "custom", path: ["percentage_value"], message: "Enter a percentage from 1 to 100." });
      }
    } else if (!data.fixed_amount) {
      ctx.addIssue({ code: "custom", path: ["fixed_amount"], message: "Enter a fixed discount amount." });
    }
  });

type FormValues = z.infer<typeof couponSchema>;

const STATUS_LABELS: Record<string, string> = { active: "Active", inactive: "Inactive" };
const DISCOUNT_TYPE_LABELS: Record<string, string> = { percentage: "Percentage off", fixed: "Fixed amount off" };

interface CouponFormProps {
  storeId: number;
  defaultValues?: Coupon;
  onSubmit: (values: CouponFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
  submitLabel: string;
}

export function CouponForm({ storeId, defaultValues, onSubmit, isPending, serverError, submitLabel }: CouponFormProps) {
  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(couponSchema),
    defaultValues: {
      code: defaultValues?.code ?? "",
      description: defaultValues?.description ?? "",
      discount_type: defaultValues?.discount_type ?? "percentage",
      percentage_value: defaultValues?.percentage_value ? String(defaultValues.percentage_value) : "",
      fixed_amount: defaultValues?.fixed_amount ? String(defaultValues.fixed_amount) : "",
      minimum_order_amount: defaultValues?.minimum_order_amount ? String(defaultValues.minimum_order_amount) : "",
      usage_limit: defaultValues?.usage_limit ? String(defaultValues.usage_limit) : "",
      per_customer_limit: defaultValues?.per_customer_limit ? String(defaultValues.per_customer_limit) : "",
      starts_at: defaultValues?.starts_at ? defaultValues.starts_at.slice(0, 10) : "",
      expires_at: defaultValues?.expires_at ? defaultValues.expires_at.slice(0, 10) : "",
      status: defaultValues?.status ?? "active",
    },
  });

  const discountType = useWatch({ control, name: "discount_type" });
  const status = useWatch({ control, name: "status" });

  const submit = handleSubmit((values) => {
    onSubmit({
      store_id: storeId,
      code: values.code,
      description: values.description || null,
      discount_type: values.discount_type,
      percentage_value: values.discount_type === "percentage" ? Number(values.percentage_value) : null,
      fixed_amount: values.discount_type === "fixed" ? values.fixed_amount : null,
      minimum_order_amount: values.minimum_order_amount || null,
      usage_limit: values.usage_limit ? Number(values.usage_limit) : null,
      per_customer_limit: values.per_customer_limit ? Number(values.per_customer_limit) : null,
      starts_at: values.starts_at || null,
      expires_at: values.expires_at || null,
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
          <Label htmlFor="coupon-code">Code</Label>
          <Input id="coupon-code" placeholder="e.g. SAVE10" error={Boolean(errors.code)} {...register("code")} />
          {errors.code ? <p className="text-xs text-danger">{errors.code.message}</p> : null}
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="coupon-form-status">Status</Label>
          <Select value={status} onValueChange={(v) => setValue("status", v as "active" | "inactive", { shouldDirty: true })}>
            <SelectTrigger id="coupon-form-status">
              <SelectValue placeholder="Select status">{status ? STATUS_LABELS[status] : undefined}</SelectValue>
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="active">Active</SelectItem>
              <SelectItem value="inactive">Inactive</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="coupon-description">Description (optional, internal use)</Label>
        <Input id="coupon-description" placeholder="e.g. Eid campaign" {...register("description")} />
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="coupon-form-discount-type">Discount type</Label>
          <Select
            value={discountType}
            onValueChange={(v) => setValue("discount_type", v as "percentage" | "fixed", { shouldDirty: true })}
          >
            <SelectTrigger id="coupon-form-discount-type">
              <SelectValue placeholder="Select type">{DISCOUNT_TYPE_LABELS[discountType]}</SelectValue>
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="percentage">Percentage off</SelectItem>
              <SelectItem value="fixed">Fixed amount off</SelectItem>
            </SelectContent>
          </Select>
        </div>
        {discountType === "percentage" ? (
          <div className="space-y-1.5">
            <Label htmlFor="coupon-percentage-value">Percentage (1-100)</Label>
            <Input
              id="coupon-percentage-value"
              type="number"
              min={1}
              max={100}
              inputMode="numeric"
              error={Boolean(errors.percentage_value)}
              {...register("percentage_value")}
            />
            {errors.percentage_value ? <p className="text-xs text-danger">{errors.percentage_value.message}</p> : null}
          </div>
        ) : (
          <div className="space-y-1.5">
            <Label htmlFor="coupon-fixed-amount">Fixed discount amount</Label>
            <Input id="coupon-fixed-amount" inputMode="decimal" error={Boolean(errors.fixed_amount)} {...register("fixed_amount")} />
            {errors.fixed_amount ? <p className="text-xs text-danger">{errors.fixed_amount.message}</p> : null}
          </div>
        )}
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="coupon-minimum-order">Minimum order amount (optional)</Label>
        <Input
          id="coupon-minimum-order"
          inputMode="decimal"
          error={Boolean(errors.minimum_order_amount)}
          {...register("minimum_order_amount")}
        />
        {errors.minimum_order_amount ? <p className="text-xs text-danger">{errors.minimum_order_amount.message}</p> : null}
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="coupon-usage-limit">Total usage limit (optional)</Label>
          <Input
            id="coupon-usage-limit"
            type="number"
            min={1}
            inputMode="numeric"
            placeholder="Unlimited"
            error={Boolean(errors.usage_limit)}
            {...register("usage_limit")}
          />
          {errors.usage_limit ? <p className="text-xs text-danger">{errors.usage_limit.message}</p> : null}
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="coupon-per-customer-limit">Per-customer limit (optional)</Label>
          <Input
            id="coupon-per-customer-limit"
            type="number"
            min={1}
            inputMode="numeric"
            placeholder="Unlimited"
            error={Boolean(errors.per_customer_limit)}
            {...register("per_customer_limit")}
          />
          {errors.per_customer_limit ? <p className="text-xs text-danger">{errors.per_customer_limit.message}</p> : null}
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="coupon-starts-at">Starts (optional)</Label>
          <Input id="coupon-starts-at" type="date" {...register("starts_at")} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="coupon-expires-at">Expires (optional)</Label>
          <Input id="coupon-expires-at" type="date" {...register("expires_at")} />
        </div>
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
