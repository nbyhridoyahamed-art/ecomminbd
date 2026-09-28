"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useNewsletterSubscribe } from "@/hooks/use-storefront-homepage";
import type { NewsletterSettings } from "@/types/homepage-block";

export function NewsletterBlock({ settings }: { settings: NewsletterSettings }) {
  const [email, setEmail] = useState("");
  const mutation = useNewsletterSubscribe();

  return (
    <section className="mx-auto max-w-xl space-y-4 rounded-lg border border-border bg-surface px-6 py-10 text-center">
      <div className="space-y-2">
        <h2 className="text-section font-semibold text-text-primary">{settings.heading}</h2>
        {settings.subheading ? <p className="text-text-secondary">{settings.subheading}</p> : null}
      </div>
      <form
        className="flex flex-col gap-2 tablet:flex-row"
        onSubmit={(event) => {
          event.preventDefault();
          mutation.mutate(email, { onSuccess: () => setEmail("") });
        }}
      >
        <Input
          type="email"
          required
          aria-label="Email address"
          placeholder="you@example.com"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          className="flex-1"
        />
        <Button type="submit" loading={mutation.isPending}>
          {settings.cta_label}
        </Button>
      </form>
    </section>
  );
}
