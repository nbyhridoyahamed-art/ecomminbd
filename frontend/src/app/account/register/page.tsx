"use client";

import Link from "next/link";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";

import { ApiError } from "@/types/api";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useCustomerRegister } from "@/hooks/use-customer-auth";

const registerSchema = z
  .object({
    name: z.string().min(1, "Name is required."),
    phone: z.string().min(1, "Phone number is required."),
    email: z.string().email("Enter a valid email address.").or(z.literal("")),
    password: z.string().min(8, "Password must be at least 8 characters."),
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: "Passwords do not match.",
    path: ["password_confirmation"],
  });

type RegisterFormValues = z.infer<typeof registerSchema>;

export default function AccountRegisterPage() {
  const register_ = useCustomerRegister();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema),
  });

  const serverError = register_.error instanceof ApiError ? register_.error.message : null;

  const onSubmit = (values: RegisterFormValues) => {
    register_.mutate({ ...values, email: values.email || undefined });
  };

  return (
    <div className="mx-auto flex min-h-[70vh] max-w-sm flex-col justify-center px-4 py-12">
      <div className="space-y-1 text-center">
        <h1 className="text-page-title font-semibold text-text-primary">Create an account</h1>
        <p className="text-sm text-text-secondary">
          Already ordered as a guest? Use the same phone number to link your past orders automatically.
        </p>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="mt-6 space-y-4">
        {serverError ? (
          <Alert variant="danger">
            <AlertDescription>{serverError}</AlertDescription>
          </Alert>
        ) : null}

        <div className="space-y-1.5">
          <Label htmlFor="name">Full name</Label>
          <Input id="name" autoComplete="name" error={Boolean(errors.name)} {...register("name")} />
          {errors.name ? <p className="text-xs text-danger">{errors.name.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="phone">Phone number</Label>
          <Input
            id="phone"
            type="tel"
            autoComplete="tel"
            placeholder="01712345678"
            error={Boolean(errors.phone)}
            {...register("phone")}
          />
          {errors.phone ? <p className="text-xs text-danger">{errors.phone.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="email">Email (optional)</Label>
          <Input id="email" type="email" autoComplete="email" error={Boolean(errors.email)} {...register("email")} />
          {errors.email ? <p className="text-xs text-danger">{errors.email.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="password">Password</Label>
          <Input
            id="password"
            type="password"
            autoComplete="new-password"
            error={Boolean(errors.password)}
            {...register("password")}
          />
          {errors.password ? <p className="text-xs text-danger">{errors.password.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="password_confirmation">Confirm password</Label>
          <Input
            id="password_confirmation"
            type="password"
            autoComplete="new-password"
            error={Boolean(errors.password_confirmation)}
            {...register("password_confirmation")}
          />
          {errors.password_confirmation ? (
            <p className="text-xs text-danger">{errors.password_confirmation.message}</p>
          ) : null}
        </div>

        <Button type="submit" className="w-full" loading={register_.isPending}>
          Create account
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-text-secondary">
        Already have an account?{" "}
        <Link href="/account/login" className="font-medium text-primary hover:underline">
          Sign in
        </Link>
      </p>
    </div>
  );
}
