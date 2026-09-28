"use client";

import { Monitor, Smartphone, Tablet } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/lib/utils";
import type { Breakpoint, BreakpointStyle, HomepageBlockResponsive, HomepageBlockVisibility } from "@/types/homepage-block";

const BREAKPOINTS: { key: Breakpoint; label: string; icon: typeof Monitor }[] = [
  { key: "desktop", label: "Desktop", icon: Monitor },
  { key: "tablet", label: "Tablet", icon: Tablet },
  { key: "mobile", label: "Mobile", icon: Smartphone },
];

interface LayoutPanelProps {
  responsive: HomepageBlockResponsive;
  visibility: HomepageBlockVisibility;
  onResponsiveChange: (value: HomepageBlockResponsive) => void;
  onVisibilityChange: (value: HomepageBlockVisibility) => void;
  activeBreakpoint: Breakpoint;
  onBreakpointChange: (breakpoint: Breakpoint) => void;
}

/**
 * The combined "Spacing" + "Responsive" + per-breakpoint visibility tabs
 * (spec section 58/61) — one breakpoint-aware panel rather than three
 * overlapping ones, since they all edit the same underlying per-breakpoint
 * object. The active breakpoint is shared with the canvas's own
 * Desktop/Tablet/Mobile toolbar switcher, so picking a device there also
 * points this panel at the matching fields.
 */
export function LayoutPanel({ responsive, visibility, onResponsiveChange, onVisibilityChange, activeBreakpoint, onBreakpointChange }: LayoutPanelProps) {
  const current: BreakpointStyle = responsive[activeBreakpoint] ?? {};

  function updateField(field: keyof BreakpointStyle, fieldValue: string | number | null) {
    onResponsiveChange({ ...responsive, [activeBreakpoint]: { ...current, [field]: fieldValue } });
  }

  return (
    <div className="space-y-4">
      <div className="flex gap-1 rounded-md border border-border p-1">
        {BREAKPOINTS.map(({ key, label, icon: Icon }) => (
          <Button
            key={key}
            type="button"
            size="sm"
            variant={activeBreakpoint === key ? "secondary" : "ghost"}
            className="flex-1"
            onClick={() => onBreakpointChange(key)}
          >
            <Icon className="size-4" />
            {label}
          </Button>
        ))}
      </div>

      <label className="flex items-center gap-2 text-sm">
        <Checkbox
          checked={visibility[activeBreakpoint] !== false}
          onCheckedChange={(checked) => onVisibilityChange({ ...visibility, [activeBreakpoint]: checked === true })}
        />
        Visible on {activeBreakpoint}
      </label>

      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label htmlFor="layout-width">Width</Label>
          <Input id="layout-width" placeholder="e.g. 100%" value={current.width ?? ""} onChange={(event) => updateField("width", event.target.value || null)} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="layout-height">Height</Label>
          <Input id="layout-height" placeholder="auto" value={current.height ?? ""} onChange={(event) => updateField("height", event.target.value || null)} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="layout-padding">Padding</Label>
          <Input id="layout-padding" placeholder="e.g. 24px" value={current.padding ?? ""} onChange={(event) => updateField("padding", event.target.value || null)} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="layout-margin">Margin</Label>
          <Input id="layout-margin" placeholder="e.g. 0" value={current.margin ?? ""} onChange={(event) => updateField("margin", event.target.value || null)} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="layout-font-size">Font size</Label>
          <Input id="layout-font-size" placeholder="e.g. 16px" value={current.font_size ?? ""} onChange={(event) => updateField("font_size", event.target.value || null)} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="layout-gap">Gap</Label>
          <Input id="layout-gap" placeholder="e.g. 16px" value={current.gap ?? ""} onChange={(event) => updateField("gap", event.target.value || null)} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="layout-columns">Columns</Label>
          <Input
            id="layout-columns"
            type="number"
            min={1}
            max={12}
            value={current.columns ?? ""}
            onChange={(event) => updateField("columns", event.target.value ? Number(event.target.value) : null)}
          />
        </div>
        <div className="space-y-1.5">
          <Label>Alignment</Label>
          <Select value={current.alignment ?? "__unset"} onValueChange={(v) => updateField("alignment", v === "__unset" ? null : v)}>
            <SelectTrigger>
              <SelectValue placeholder="Default" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__unset">Default</SelectItem>
              <SelectItem value="left">Left</SelectItem>
              <SelectItem value="center">Center</SelectItem>
              <SelectItem value="right">Right</SelectItem>
              <SelectItem value="stretch">Stretch</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="space-y-1.5">
        <Label>Display</Label>
        <div className="flex gap-1">
          {(["block", "flex", "grid", "none"] as const).map((option) => (
            <Button
              key={option}
              type="button"
              size="sm"
              variant={current.display === option ? "secondary" : "outline"}
              className={cn("flex-1 capitalize")}
              onClick={() => updateField("display", current.display === option ? null : option)}
            >
              {option}
            </Button>
          ))}
        </div>
      </div>
    </div>
  );
}
