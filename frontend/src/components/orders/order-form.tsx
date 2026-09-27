"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useFieldArray, useForm, useWatch } from "react-hook-form";
import { Plus, Trash2 } from "lucide-react";
import { z } from "zod";

import { useCustomer } from "@/hooks/use-customers";
import { useDistricts, useDivisions, useUpazilas } from "@/hooks/use-locations";
import type { OrderFormValues } from "@/hooks/use-orders";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { Customer } from "@/types/customer";
import type { PaymentMethod } from "@/types/order";
import type { Product } from "@/types/product";
import type { Warehouse } from "@/types/warehouse";

const moneyField = z.string().refine((v) => v === "" || /^\d+(\.\d{1,2})?$/.test(v), "Enter a valid amount, e.g. 199.99");

const PAYMENT_METHODS: { value: PaymentMethod; label: string }[] = [
  { value: "cod", label: "Cash on delivery" },
  { value: "bkash", label: "bKash" },
  { value: "nagad", label: "Nagad" },
  { value: "rocket", label: "Rocket" },
  { value: "card", label: "Card" },
  { value: "bank_transfer", label: "Bank transfer" },
];

const orderSchema = z
  .object({
    customer_id: z.string().min(1, "Select a customer."),
    warehouse_id: z.string().min(1, "Select a warehouse."),
    payment_method: z.enum(["cod", "bkash", "nagad", "rocket", "card", "bank_transfer"]),
    customer_address_id: z.string(),
    shipping_recipient_name: z.string(),
    shipping_phone: z.string(),
    shipping_address_line: z.string(),
    shipping_bd_division_id: z.string(),
    shipping_bd_district_id: z.string(),
    shipping_bd_upazila_id: z.string(),
    shipping_amount: moneyField,
    discount_amount: moneyField,
    notes: z.string(),
    items: z
      .array(
        z.object({
          product_id: z.string().min(1, "Select a product."),
          quantity: z
            .string()
            .min(1, "Required.")
            .refine((v) => Number.isInteger(Number(v)) && Number(v) >= 1, "Enter a quantity of at least 1."),
          unit_price: moneyField.refine((v) => v !== "", "Required."),
        }),
      )
      .min(1, "Add at least one product."),
  })
  .superRefine((data, ctx) => {
    if (data.customer_address_id !== "manual") return;

    if (!data.shipping_recipient_name) {
      ctx.addIssue({ code: "custom", path: ["shipping_recipient_name"], message: "Required." });
    }
    if (!data.shipping_phone) {
      ctx.addIssue({ code: "custom", path: ["shipping_phone"], message: "Required." });
    }
    if (!data.shipping_address_line) {
      ctx.addIssue({ code: "custom", path: ["shipping_address_line"], message: "Required." });
    }
  });

type FormValues = z.infer<typeof orderSchema>;

interface OrderFormProps {
  storeId: number;
  customers: Customer[];
  warehouses: Warehouse[];
  products: Product[];
  onSubmit: (values: OrderFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
  submitLabel: string;
}

export function OrderForm({
  storeId,
  customers,
  warehouses,
  products,
  onSubmit,
  isPending,
  serverError,
  submitLabel,
}: OrderFormProps) {
  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(orderSchema),
    defaultValues: {
      customer_id: "",
      warehouse_id: "",
      payment_method: "cod",
      customer_address_id: "manual",
      shipping_recipient_name: "",
      shipping_phone: "",
      shipping_address_line: "",
      shipping_bd_division_id: "",
      shipping_bd_district_id: "",
      shipping_bd_upazila_id: "",
      shipping_amount: "",
      discount_amount: "",
      notes: "",
      items: [{ product_id: "", quantity: "", unit_price: "" }],
    },
  });

  const { fields, append, remove } = useFieldArray({ control, name: "items" });
  const customerId = useWatch({ control, name: "customer_id" });
  const warehouseId = useWatch({ control, name: "warehouse_id" });
  const paymentMethod = useWatch({ control, name: "payment_method" });
  const customerAddressId = useWatch({ control, name: "customer_address_id" });
  const divisionId = useWatch({ control, name: "shipping_bd_division_id" });
  const districtId = useWatch({ control, name: "shipping_bd_district_id" });
  const upazilaId = useWatch({ control, name: "shipping_bd_upazila_id" });
  const items = useWatch({ control, name: "items" });

  const { data: selectedCustomer } = useCustomer(customerId ? Number(customerId) : null);
  const addresses = selectedCustomer?.addresses ?? [];

  const { data: divisions } = useDivisions();
  const { data: districts } = useDistricts(divisionId ? Number(divisionId) : null);
  const { data: upazilas } = useUpazilas(districtId ? Number(districtId) : null);

  const submit = handleSubmit((values) => {
    const usingSavedAddress = values.customer_address_id !== "manual";

    onSubmit({
      store_id: storeId,
      customer_id: Number(values.customer_id),
      warehouse_id: Number(values.warehouse_id),
      payment_method: values.payment_method,
      shipping_amount: values.shipping_amount || null,
      discount_amount: values.discount_amount || null,
      notes: values.notes || null,
      customer_address_id: usingSavedAddress ? Number(values.customer_address_id) : null,
      ...(usingSavedAddress
        ? {}
        : {
            shipping_recipient_name: values.shipping_recipient_name,
            shipping_phone: values.shipping_phone,
            shipping_address_line: values.shipping_address_line,
            shipping_bd_division_id: values.shipping_bd_division_id ? Number(values.shipping_bd_division_id) : null,
            shipping_bd_district_id: values.shipping_bd_district_id ? Number(values.shipping_bd_district_id) : null,
            shipping_bd_upazila_id: values.shipping_bd_upazila_id ? Number(values.shipping_bd_upazila_id) : null,
          }),
      items: values.items.map((item) => ({
        product_id: Number(item.product_id),
        quantity: Number(item.quantity),
        unit_price: item.unit_price,
      })),
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
          <Label>Customer</Label>
          <Select
            value={customerId}
            onValueChange={(v) => {
              setValue("customer_id", v, { shouldValidate: true });
              setValue("customer_address_id", "manual");
            }}
          >
            <SelectTrigger>
              <SelectValue placeholder="Select customer">
                {customers.find((c) => String(c.id) === customerId)?.name}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {customers.map((c) => (
                <SelectItem key={c.id} value={String(c.id)}>
                  {c.name} ({c.phone})
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.customer_id ? <p className="text-xs text-danger">{errors.customer_id.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <Label>Fulfilling warehouse</Label>
          <Select value={warehouseId} onValueChange={(v) => setValue("warehouse_id", v, { shouldValidate: true })}>
            <SelectTrigger>
              <SelectValue placeholder="Select warehouse">
                {warehouses.find((w) => String(w.id) === warehouseId)?.name}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {warehouses.map((w) => (
                <SelectItem key={w.id} value={String(w.id)}>
                  {w.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.warehouse_id ? <p className="text-xs text-danger">{errors.warehouse_id.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <Label>Payment method</Label>
          <Select
            value={paymentMethod}
            onValueChange={(v) => setValue("payment_method", v as PaymentMethod, { shouldValidate: true })}
          >
            <SelectTrigger>
              <SelectValue placeholder="Select method">
                {PAYMENT_METHODS.find((m) => m.value === paymentMethod)?.label}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {PAYMENT_METHODS.map((m) => (
                <SelectItem key={m.value} value={m.value}>
                  {m.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="space-y-3 rounded-lg border border-border p-4">
        <Label>Shipping address</Label>

        {addresses.length > 0 ? (
          <div className="space-y-1.5">
            <Select
              value={customerAddressId}
              onValueChange={(v) => setValue("customer_address_id", v)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Enter address manually">
                  {addresses.find((a) => String(a.id) === customerAddressId)?.recipient_name}
                </SelectValue>
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="manual">Enter address manually</SelectItem>
                {addresses.map((a) => (
                  <SelectItem key={a.id} value={String(a.id)}>
                    {a.recipient_name} — {a.address_line}
                    {a.is_default ? " (default)" : ""}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        ) : null}

        {customerAddressId === "manual" ? (
          <div className="space-y-3">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="shipping_recipient_name">Recipient name</Label>
                <Input
                  id="shipping_recipient_name"
                  error={Boolean(errors.shipping_recipient_name)}
                  {...register("shipping_recipient_name")}
                />
                {errors.shipping_recipient_name ? (
                  <p className="text-xs text-danger">{errors.shipping_recipient_name.message}</p>
                ) : null}
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="shipping_phone">Phone</Label>
                <Input id="shipping_phone" error={Boolean(errors.shipping_phone)} {...register("shipping_phone")} />
                {errors.shipping_phone ? <p className="text-xs text-danger">{errors.shipping_phone.message}</p> : null}
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="shipping_address_line">Address</Label>
              <Input
                id="shipping_address_line"
                error={Boolean(errors.shipping_address_line)}
                {...register("shipping_address_line")}
              />
              {errors.shipping_address_line ? (
                <p className="text-xs text-danger">{errors.shipping_address_line.message}</p>
              ) : null}
            </div>

            <div className="grid grid-cols-3 gap-4">
              <div className="space-y-1.5">
                <Label>Division</Label>
                <Select
                  value={divisionId}
                  onValueChange={(v) => {
                    setValue("shipping_bd_division_id", v);
                    setValue("shipping_bd_district_id", "");
                    setValue("shipping_bd_upazila_id", "");
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
                    setValue("shipping_bd_district_id", v);
                    setValue("shipping_bd_upazila_id", "");
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
                <Select value={upazilaId} onValueChange={(v) => setValue("shipping_bd_upazila_id", v)}>
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
          </div>
        ) : null}
      </div>

      <div className="space-y-2">
        <Label>Items</Label>
        <div className="space-y-2">
          {fields.map((field, index) => {
            const itemErrors = errors.items?.[index];
            const selectedProductId = items?.[index]?.product_id;
            return (
              <div key={field.id} className="flex items-start gap-2">
                <div className="flex-1 space-y-1">
                  <Select
                    value={selectedProductId}
                    onValueChange={(v) => setValue(`items.${index}.product_id`, v, { shouldValidate: true })}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select product">
                        {products.find((p) => String(p.id) === selectedProductId)?.name}
                      </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                      {products.map((p) => (
                        <SelectItem key={p.id} value={String(p.id)}>
                          {p.name} ({p.sku})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {itemErrors?.product_id ? <p className="text-xs text-danger">{itemErrors.product_id.message}</p> : null}
                </div>
                <div className="w-24 space-y-1">
                  <Input
                    type="number"
                    min={1}
                    inputMode="numeric"
                    placeholder="Qty"
                    error={Boolean(itemErrors?.quantity)}
                    {...register(`items.${index}.quantity`)}
                  />
                  {itemErrors?.quantity ? <p className="text-xs text-danger">{itemErrors.quantity.message}</p> : null}
                </div>
                <div className="w-32 space-y-1">
                  <Input
                    inputMode="decimal"
                    placeholder="Unit price"
                    error={Boolean(itemErrors?.unit_price)}
                    {...register(`items.${index}.unit_price`)}
                  />
                  {itemErrors?.unit_price ? <p className="text-xs text-danger">{itemErrors.unit_price.message}</p> : null}
                </div>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  aria-label="Remove item"
                  disabled={fields.length === 1}
                  onClick={() => remove(index)}
                >
                  <Trash2 className="text-danger" />
                </Button>
              </div>
            );
          })}
        </div>
        {errors.items?.root || errors.items?.message ? (
          <p className="text-xs text-danger">{errors.items.root?.message ?? errors.items.message}</p>
        ) : null}
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => append({ product_id: "", quantity: "", unit_price: "" })}
        >
          <Plus />
          Add item
        </Button>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="shipping_amount">Shipping charge (optional)</Label>
          <Input
            id="shipping_amount"
            inputMode="decimal"
            error={Boolean(errors.shipping_amount)}
            {...register("shipping_amount")}
          />
          {errors.shipping_amount ? <p className="text-xs text-danger">{errors.shipping_amount.message}</p> : null}
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="discount_amount">Discount (optional)</Label>
          <Input
            id="discount_amount"
            inputMode="decimal"
            error={Boolean(errors.discount_amount)}
            {...register("discount_amount")}
          />
          {errors.discount_amount ? <p className="text-xs text-danger">{errors.discount_amount.message}</p> : null}
        </div>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="notes">Notes (optional)</Label>
        <Textarea id="notes" rows={2} {...register("notes")} />
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
