"use client";

import { useEffect } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useWatch } from "react-hook-form";
import { ShoppingCart } from "lucide-react";
import { z } from "zod";

import { trackEvent } from "@/lib/analytics";
import { formatMoney } from "@/lib/money";
import { ApiError } from "@/types/api";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { useStorefrontDistricts, useStorefrontDivisions, useStorefrontUpazilas } from "@/hooks/use-storefront-catalog";
import { useStorefrontCheckout } from "@/hooks/use-storefront-checkout";
import { useCartStore } from "@/stores/cart-store";

const checkoutSchema = z.object({
  customer_name: z.string().min(1, "Name is required."),
  customer_phone: z.string().min(1, "Phone number is required."),
  customer_email: z.string().email("Enter a valid email address.").or(z.literal("")),
  shipping_recipient_name: z.string().min(1, "Recipient name is required."),
  shipping_phone: z.string().min(1, "Phone number is required."),
  shipping_address_line: z.string().min(1, "Address is required."),
  shipping_bd_division_id: z.string(),
  shipping_bd_district_id: z.string(),
  shipping_bd_upazila_id: z.string(),
  notes: z.string(),
});

type CheckoutFormValues = z.infer<typeof checkoutSchema>;

export default function CheckoutPage() {
  const router = useRouter();
  const { items, clear } = useCartStore();
  const checkout = useStorefrontCheckout();

  // Mount-only: fires once per checkout page visit, reading the cart
  // imperatively (rather than depending on the reactive `items` above) so
  // later quantity/line changes never re-trigger it.
  useEffect(() => {
    if (useCartStore.getState().items.length > 0) {
      trackEvent("checkout_start");
    }
  }, []);

  const {
    register,
    handleSubmit,
    setValue,
    control,
    formState: { errors },
  } = useForm<CheckoutFormValues>({
    resolver: zodResolver(checkoutSchema),
    defaultValues: {
      customer_name: "",
      customer_phone: "",
      customer_email: "",
      shipping_recipient_name: "",
      shipping_phone: "",
      shipping_address_line: "",
      shipping_bd_division_id: "",
      shipping_bd_district_id: "",
      shipping_bd_upazila_id: "",
      notes: "",
    },
  });

  const divisionId = useWatch({ control, name: "shipping_bd_division_id" });
  const districtId = useWatch({ control, name: "shipping_bd_district_id" });
  const upazilaId = useWatch({ control, name: "shipping_bd_upazila_id" });

  const { data: divisions } = useStorefrontDivisions();
  const { data: districts } = useStorefrontDistricts(divisionId ? Number(divisionId) : null);
  const { data: upazilas } = useStorefrontUpazilas(districtId ? Number(districtId) : null);

  const subtotal = items.reduce((sum, item) => sum + item.unitPrice * item.quantity, 0);
  const currencyCode = items[0]?.currencyCode ?? "BDT";

  const onSubmit = (values: CheckoutFormValues) => {
    checkout.mutate(
      {
        customer_name: values.customer_name,
        customer_phone: values.customer_phone,
        customer_email: values.customer_email || undefined,
        shipping_recipient_name: values.shipping_recipient_name,
        shipping_phone: values.shipping_phone,
        shipping_address_line: values.shipping_address_line,
        shipping_bd_division_id: values.shipping_bd_division_id ? Number(values.shipping_bd_division_id) : undefined,
        shipping_bd_district_id: values.shipping_bd_district_id ? Number(values.shipping_bd_district_id) : undefined,
        shipping_bd_upazila_id: values.shipping_bd_upazila_id ? Number(values.shipping_bd_upazila_id) : undefined,
        notes: values.notes || undefined,
        items: items.map((item) => ({
          product_id: item.productId,
          product_variant_id: item.variantId ?? undefined,
          quantity: item.quantity,
        })),
      },
      {
        onSuccess: (order) => {
          clear();
          router.push(`/order-confirmation/${order.uuid}`);
        },
      },
    );
  };

  if (items.length === 0) {
    return (
      <div className="mx-auto max-w-[1400px] px-4 py-16">
        <EmptyState
          icon={<ShoppingCart />}
          title="Your cart is empty"
          description="Add something to your cart before checking out."
          action={
            <Button asChild>
              <Link href="/products">Browse products</Link>
            </Button>
          }
        />
      </div>
    );
  }

  const serverError = checkout.error instanceof ApiError ? checkout.error.message : null;

  return (
    <div className="mx-auto max-w-[1400px] px-4 py-8">
      <h1 className="mb-6 text-page-title font-semibold text-text-primary">Checkout</h1>

      <form onSubmit={handleSubmit(onSubmit)} className="grid gap-8 desktop:grid-cols-3">
        <div className="space-y-6 desktop:col-span-2">
          {serverError ? (
            <Alert variant="danger">
              <AlertDescription>{serverError}</AlertDescription>
            </Alert>
          ) : null}

          <div className="space-y-4 rounded-lg border border-border p-4">
            <h2 className="font-semibold text-text-primary">Contact Information</h2>
            <div className="grid gap-4 tablet:grid-cols-2">
              <div className="space-y-1.5">
                <Label htmlFor="customer_name">Full name</Label>
                <Input id="customer_name" error={Boolean(errors.customer_name)} {...register("customer_name")} />
                {errors.customer_name ? <p className="text-xs text-danger">{errors.customer_name.message}</p> : null}
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="customer_phone">Phone</Label>
                <Input id="customer_phone" error={Boolean(errors.customer_phone)} {...register("customer_phone")} />
                {errors.customer_phone ? (
                  <p className="text-xs text-danger">{errors.customer_phone.message}</p>
                ) : null}
              </div>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="customer_email">Email (optional)</Label>
              <Input
                id="customer_email"
                type="email"
                error={Boolean(errors.customer_email)}
                {...register("customer_email")}
              />
              {errors.customer_email ? <p className="text-xs text-danger">{errors.customer_email.message}</p> : null}
            </div>
          </div>

          <div className="space-y-4 rounded-lg border border-border p-4">
            <h2 className="font-semibold text-text-primary">Shipping Address</h2>
            <div className="grid gap-4 tablet:grid-cols-2">
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
                {errors.shipping_phone ? (
                  <p className="text-xs text-danger">{errors.shipping_phone.message}</p>
                ) : null}
              </div>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="shipping_address_line">Address</Label>
              <Textarea
                id="shipping_address_line"
                rows={2}
                error={Boolean(errors.shipping_address_line)}
                {...register("shipping_address_line")}
              />
              {errors.shipping_address_line ? (
                <p className="text-xs text-danger">{errors.shipping_address_line.message}</p>
              ) : null}
            </div>
            <div className="grid grid-cols-3 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="checkout-shipping-division">Division</Label>
                <Select
                  value={divisionId}
                  onValueChange={(v) => {
                    setValue("shipping_bd_division_id", v);
                    setValue("shipping_bd_district_id", "");
                    setValue("shipping_bd_upazila_id", "");
                  }}
                >
                  <SelectTrigger id="checkout-shipping-division">
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
                <Label htmlFor="checkout-shipping-district">District</Label>
                <Select
                  value={districtId}
                  onValueChange={(v) => {
                    setValue("shipping_bd_district_id", v);
                    setValue("shipping_bd_upazila_id", "");
                  }}
                >
                  <SelectTrigger id="checkout-shipping-district" disabled={!divisionId}>
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
                <Label htmlFor="checkout-shipping-upazila">Upazila</Label>
                <Select value={upazilaId} onValueChange={(v) => setValue("shipping_bd_upazila_id", v)}>
                  <SelectTrigger id="checkout-shipping-upazila" disabled={!districtId}>
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
            <div className="space-y-1.5">
              <Label htmlFor="notes">Order notes (optional)</Label>
              <Textarea id="notes" rows={2} {...register("notes")} />
            </div>
          </div>

          <div className="rounded-lg border border-border p-4">
            <h2 className="font-semibold text-text-primary">Payment Method</h2>
            <p className="mt-2 text-sm text-text-secondary">Cash on Delivery — pay when your order arrives.</p>
          </div>
        </div>

        <div className="space-y-4">
          <div className="space-y-3 rounded-lg border border-border p-4">
            <h2 className="font-semibold text-text-primary">Order Summary</h2>
            <div className="space-y-2">
              {items.map((item) => (
                <div key={`${item.productId}:${item.variantId ?? "0"}`} className="flex justify-between text-sm">
                  <span className="text-text-secondary">
                    {item.name}
                    {item.variantLabel ? ` (${item.variantLabel})` : ""} &times; {item.quantity}
                  </span>
                  <span className="text-text-primary">
                    {formatMoney(item.unitPrice * item.quantity, item.currencyCode)}
                  </span>
                </div>
              ))}
            </div>
            <div className="flex justify-between border-t border-border pt-2 font-semibold text-text-primary">
              <span>Total</span>
              <span>{formatMoney(subtotal, currencyCode)}</span>
            </div>
          </div>
          <Button type="submit" size="lg" className="w-full" loading={checkout.isPending}>
            Place Order
          </Button>
        </div>
      </form>
    </div>
  );
}
