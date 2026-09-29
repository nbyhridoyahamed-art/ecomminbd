"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Logo } from "@/components/shared/logo";
import { useLogin } from "@/hooks/use-auth";
import { ApiError } from "@/types/api";

const loginSchema = z.object({
  email: z.string().min(1, "Email is required").email("Enter a valid email address."),
  password: z.string().min(1, "Password is required."),
});

type LoginFormValues = z.infer<typeof loginSchema>;

export default function LoginPage() {
  const login = useLogin();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
  });

  const onSubmit = (values: LoginFormValues) => {
    login.mutate(values);
  };

  const serverError = login.error instanceof ApiError ? login.error.message : null;

  return (
    <div className="flex min-h-screen items-center justify-center bg-background px-4">
      <div className="w-full max-w-sm space-y-6">
        <div className="space-y-3 text-center">
          <Logo className="justify-center" iconSize={40} textClassName="text-display font-semibold" />
          <h1 className="text-page-title font-semibold text-text-primary">Sign in to your store</h1>
          <p className="text-sm text-text-secondary">Manage your ecommerce operations from one place.</p>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          {serverError ? (
            <Alert variant="danger">
              <AlertDescription>{serverError}</AlertDescription>
            </Alert>
          ) : null}

          <div className="space-y-1.5">
            <Label htmlFor="email">Email</Label>
            <Input
              id="email"
              type="email"
              autoComplete="email"
              placeholder="you@example.com"
              error={Boolean(errors.email)}
              {...register("email")}
            />
            {errors.email ? <p className="text-xs text-danger">{errors.email.message}</p> : null}
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
      </div>
    </div>
  );
}
