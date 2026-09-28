"use client";

import { CheckCircle2, Circle } from "lucide-react";

import { cn } from "@/lib/utils";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { Seo } from "@/types/seo";

export interface SeoFieldsValue {
  title: string;
  description: string;
  focus_keyword: string;
  og_title: string;
  og_description: string;
  og_image: string;
  twitter_title: string;
  twitter_description: string;
  twitter_image: string;
  canonical_url: string;
  robots: string;
}

export const EMPTY_SEO_FIELDS: SeoFieldsValue = {
  title: "",
  description: "",
  focus_keyword: "",
  og_title: "",
  og_description: "",
  og_image: "",
  twitter_title: "",
  twitter_description: "",
  twitter_image: "",
  canonical_url: "",
  robots: "",
};

/** `undefined`/`null` fields (or no `seo` at all, e.g. a new entity) fall back to an empty, editable form. */
export function seoToFieldsValue(seo: Seo | null | undefined): SeoFieldsValue {
  return {
    title: seo?.title ?? "",
    description: seo?.description ?? "",
    focus_keyword: seo?.focus_keyword ?? "",
    og_title: seo?.og_title ?? "",
    og_description: seo?.og_description ?? "",
    og_image: seo?.og_image ?? "",
    twitter_title: seo?.twitter_title ?? "",
    twitter_description: seo?.twitter_description ?? "",
    twitter_image: seo?.twitter_image ?? "",
    canonical_url: seo?.canonical_url ?? "",
    robots: seo?.robots ?? "",
  };
}

/** Blank fields are sent as `null` (clears any existing override) rather than omitted. */
export function seoFieldsToPayload(value: SeoFieldsValue): Record<string, string | null> {
  return Object.fromEntries(Object.entries(value).map(([key, fieldValue]) => [key, fieldValue === "" ? null : fieldValue]));
}

interface ChecklistRule {
  label: string;
  passed: boolean;
}

/**
 * Deterministic, rule-based — never a fabricated AI-style "SEO score". Each
 * rule is a plain length or substring check a user can verify themselves.
 */
function buildChecklist(value: SeoFieldsValue): ChecklistRule[] {
  const title = value.title.trim();
  const description = value.description.trim();
  const keyword = value.focus_keyword.trim().toLowerCase();

  return [
    { label: `Title length is 30–60 characters (currently ${title.length})`, passed: title.length >= 30 && title.length <= 60 },
    {
      label: `Meta description length is 120–160 characters (currently ${description.length})`,
      passed: description.length >= 120 && description.length <= 160,
    },
    { label: "A focus keyword is set", passed: keyword.length > 0 },
    { label: "Focus keyword appears in the title", passed: keyword.length > 0 && title.toLowerCase().includes(keyword) },
    {
      label: "Focus keyword appears in the meta description",
      passed: keyword.length > 0 && description.toLowerCase().includes(keyword),
    },
  ];
}

function CharCount({ value, target }: { value: string; target: [number, number] }) {
  const length = value.trim().length;
  const inRange = length >= target[0] && length <= target[1];

  return (
    <span className={cn("text-xs", length === 0 ? "text-text-muted" : inRange ? "text-success" : "text-warning")}>
      {length} characters (aim for {target[0]}–{target[1]})
    </span>
  );
}

interface SeoFieldsProps {
  value: SeoFieldsValue;
  onChange: (value: SeoFieldsValue) => void;
  /** Falls back to this when Title/Description are left blank — e.g. the entity's own name and excerpt. */
  titlePlaceholder?: string;
  descriptionPlaceholder?: string;
}

export function SeoFields({ value, onChange, titlePlaceholder, descriptionPlaceholder }: SeoFieldsProps) {
  const set = <K extends keyof SeoFieldsValue>(key: K, fieldValue: string) => onChange({ ...value, [key]: fieldValue });
  const checklist = buildChecklist(value);
  const passedCount = checklist.filter((rule) => rule.passed).length;

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="seo-title">SEO title</Label>
        <Input
          id="seo-title"
          value={value.title}
          placeholder={titlePlaceholder}
          onChange={(event) => set("title", event.target.value)}
        />
        <CharCount value={value.title} target={[30, 60]} />
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="seo-description">Meta description</Label>
        <Textarea
          id="seo-description"
          rows={3}
          value={value.description}
          placeholder={descriptionPlaceholder}
          onChange={(event) => set("description", event.target.value)}
        />
        <CharCount value={value.description} target={[120, 160]} />
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="seo-focus-keyword">Focus keyword</Label>
        <Input id="seo-focus-keyword" value={value.focus_keyword} onChange={(event) => set("focus_keyword", event.target.value)} />
      </div>

      <div className="rounded-md border border-border p-3">
        <p className="mb-2 text-sm font-medium text-text-primary">SEO checklist ({passedCount}/{checklist.length})</p>
        <ul className="space-y-1.5">
          {checklist.map((rule) => (
            <li key={rule.label} className="flex items-start gap-2 text-sm">
              {rule.passed ? (
                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-success" />
              ) : (
                <Circle className="mt-0.5 size-4 shrink-0 text-text-muted" />
              )}
              <span className={rule.passed ? "text-text-secondary" : "text-text-muted"}>{rule.label}</span>
            </li>
          ))}
        </ul>
      </div>

      <Accordion type="multiple">
        <AccordionItem value="opengraph">
          <AccordionTrigger>Social sharing (Open Graph)</AccordionTrigger>
          <AccordionContent className="space-y-3">
            <div className="space-y-1.5">
              <Label htmlFor="seo-og-title">OG title</Label>
              <Input id="seo-og-title" value={value.og_title} onChange={(event) => set("og_title", event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="seo-og-description">OG description</Label>
              <Textarea
                id="seo-og-description"
                rows={2}
                value={value.og_description}
                onChange={(event) => set("og_description", event.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="seo-og-image">OG image URL</Label>
              <Input id="seo-og-image" value={value.og_image} onChange={(event) => set("og_image", event.target.value)} />
            </div>
          </AccordionContent>
        </AccordionItem>

        <AccordionItem value="twitter">
          <AccordionTrigger>Twitter card</AccordionTrigger>
          <AccordionContent className="space-y-3">
            <div className="space-y-1.5">
              <Label htmlFor="seo-twitter-title">Twitter title</Label>
              <Input
                id="seo-twitter-title"
                value={value.twitter_title}
                onChange={(event) => set("twitter_title", event.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="seo-twitter-description">Twitter description</Label>
              <Textarea
                id="seo-twitter-description"
                rows={2}
                value={value.twitter_description}
                onChange={(event) => set("twitter_description", event.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="seo-twitter-image">Twitter image URL</Label>
              <Input
                id="seo-twitter-image"
                value={value.twitter_image}
                onChange={(event) => set("twitter_image", event.target.value)}
              />
            </div>
          </AccordionContent>
        </AccordionItem>

        <AccordionItem value="advanced">
          <AccordionTrigger>Advanced</AccordionTrigger>
          <AccordionContent className="space-y-3">
            <div className="space-y-1.5">
              <Label htmlFor="seo-canonical-url">Canonical URL</Label>
              <Input
                id="seo-canonical-url"
                placeholder="https://example.com/preferred-url"
                value={value.canonical_url}
                onChange={(event) => set("canonical_url", event.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="seo-robots">Robots directive</Label>
              <Input
                id="seo-robots"
                placeholder="index, follow"
                value={value.robots}
                onChange={(event) => set("robots", event.target.value)}
              />
            </div>
          </AccordionContent>
        </AccordionItem>
      </Accordion>
    </div>
  );
}
