"use client";

import { useEffect } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";

import { useCurrentCustomer } from "@/hooks/use-customer-auth";
import { useUpdateAccountProfile } from "@/hooks/use-account";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/types/api";

const profileSchema = z.object({
  name: z.string().min(1, "Name is required."),
  email: z.string().email("Enter a valid email address.").or(z.literal("")),
});

type ProfileFormValues = z.infer<typeof profileSchema>;

export default function AccountProfilePage() {
  const { data: customer, isLoading } = useCurrentCustomer();
  const updateProfile = useUpdateAccountProfile();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isDirty },
  } = useForm<ProfileFormValues>({
    resolver: zodResolver(profileSchema),
    defaultValues: { name: "", email: "" },
  });

  useEffect(() => {
    if (customer) {
      reset({ name: customer.name, email: customer.email ?? "" });
    }
  }, [customer, reset]);

  if (isLoading || !customer) {
    return (
      <Card className="max-w-lg">
        <CardContent className="space-y-3 p-4">
          <Skeleton className="h-9 w-full" />
          <Skeleton className="h-9 w-full" />
        </CardContent>
      </Card>
    );
  }

  const serverError = updateProfile.error instanceof ApiError ? updateProfile.error.message : null;

  const onSubmit = (values: ProfileFormValues) => {
    updateProfile.mutate({ name: values.name, email: values.email || undefined });
  };

  return (
    <Card className="max-w-lg">
      <CardHeader>
        <CardTitle>Profile</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          {serverError ? (
            <Alert variant="danger">
              <AlertDescription>{serverError}</AlertDescription>
            </Alert>
          ) : null}

          <div className="space-y-1.5">
            <Label htmlFor="name">Full name</Label>
            <Input id="name" error={Boolean(errors.name)} {...register("name")} />
            {errors.name ? <p className="text-xs text-danger">{errors.name.message}</p> : null}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="email">Email</Label>
            <Input id="email" type="email" error={Boolean(errors.email)} {...register("email")} />
            {errors.email ? <p className="text-xs text-danger">{errors.email.message}</p> : null}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="phone">Phone number</Label>
            <Input id="phone" value={customer.phone} disabled />
            <p className="text-xs text-text-muted">Your phone number is used to sign in and can&apos;t be changed here.</p>
          </div>

          <div className="flex justify-end">
            <Button type="submit" loading={updateProfile.isPending} disabled={!isDirty}>
              Save changes
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}
