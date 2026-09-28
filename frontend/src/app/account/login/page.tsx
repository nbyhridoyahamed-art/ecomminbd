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
import { useCustomerLogin } from "@/hooks/use-customer-auth";

const loginSchema = z.object({
  phone: z.string().min(1, "Phone number is required."),
  password: z.string().min(1, "Password is required."),
});

type LoginFormValues = z.infer<typeof loginSchema>;

export default function AccountLoginPage() {
  const login = useCustomerLogin();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
  });

  const serverError = login.error instanceof ApiError ? login.error.message : null;

  return (
    <div className="mx-auto flex min-h-[70vh] max-w-sm flex-col justify-center px-4 py-12">
      <div className="space-y-1 text-center">
        <h1 className="text-page-title font-semibold text-text-primary">Sign in</h1>
        <p className="text-sm text-text-secondary">Track orders, save addresses, and check out faster.</p>
      </div>

      <form onSubmit={handleSubmit((values) => login.mutate(values))} className="mt-6 space-y-4">
        {serverError ? (
          <Alert variant="danger">
            <AlertDescription>{serverError}</AlertDescription>
          </Alert>
        ) : null}

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
          <Label htmlFor="password">Password</Label>
          <Input
            id="password"
            type="password"
            autoComplete="current-password"
            placeholder="••••••••"
            error={Boolean(errors.password)}
            {...register("password")}
          />
          {errors.password ? <p className="text-xs text-danger">{errors.password.message}</p> : null}
        </div>

        <Button type="submit" className="w-full" loading={login.isPending}>
          Sign in
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-text-secondary">
        Don&apos;t have an account?{" "}
        <Link href="/account/register" className="font-medium text-primary hover:underline">
          Create one
        </Link>
      </p>
    </div>
  );
}
