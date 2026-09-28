"use client";

import { useEffect, useRef, useState } from "react";

import { useUpdateHomepageBlock } from "@/hooks/use-homepage-blocks";
import type {
  HomepageBlockAnimation,
  HomepageBlockResponsive,
  HomepageBlockStyles,
  HomepageBlockVisibility,
} from "@/types/homepage-block";

interface BlockDraft {
  settings: Record<string, unknown>;
  styles: HomepageBlockStyles;
  responsive: HomepageBlockResponsive;
  visibility: HomepageBlockVisibility;
  animation: HomepageBlockAnimation;
}

/** The minimal shape this hook needs — satisfied by both the admin HomepageBlock and the resolved HomepageBlockPreview, so the canvas's already-fetched preview data can be edited directly with no redundant refetch. */
export interface EditableBlockSource {
  id: number;
  settings: Record<string, unknown>;
  styles?: HomepageBlockStyles;
  responsive?: HomepageBlockResponsive;
  visibility?: HomepageBlockVisibility;
  animation?: HomepageBlockAnimation;
}

function toDraft(block: EditableBlockSource): BlockDraft {
  return {
    settings: block.settings as Record<string, unknown>,
    styles: block.styles ?? {},
    responsive: block.responsive ?? {},
    visibility: block.visibility ?? {},
    animation: block.animation ?? null,
  };
}

export type SaveStatus = "saved" | "saving" | "unsaved";

const AUTOSAVE_DELAY_MS = 1200;

/**
 * Backs one open block's Content/Design/Layout/Animation panels: a local
 * undo/redo history over in-progress edits, debounced-autosaved to the
 * server. This is deliberately separate from the server-side
 * revisions/restore system (HomepageBlockRevision) — undo/redo here steps
 * through *this editing session's* not-yet-settled keystrokes, the way
 * Ctrl+Z does in any editor; the Revisions panel is the durable, posted-
 * commit history an already-saved change gets restored from. See
 * DEVELOPMENT_ROADMAP.md's Phase 13 scope note for why these are kept
 * distinct rather than one trying to do both jobs.
 */
export function useEditableHomepageBlock(block: EditableBlockSource) {
  const updateMutation = useUpdateHomepageBlock(block.id, { silent: true });
  const [trackedBlockId, setTrackedBlockId] = useState(block.id);
  const [history, setHistory] = useState<BlockDraft[]>(() => [toDraft(block)]);
  const [index, setIndex] = useState(0);
  const [status, setStatus] = useState<SaveStatus>("saved");
  const saveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Reset the local edit session when a different block is opened. This runs
  // during render (React's documented pattern for adjusting state when a prop
  // changes) rather than in an effect, so the reset lands before this block's
  // stale draft ever paints instead of flashing it for one frame first.
  if (block.id !== trackedBlockId) {
    setTrackedBlockId(block.id);
    setHistory([toDraft(block)]);
    setIndex(0);
    setStatus("saved");
  }

  // Refs aren't safe to read during render, so the pending-timer clear lives
  // here instead of in the block above — this cancels a stale autosave from
  // the previous block whenever `block.id` changes, and on unmount.
  useEffect(() => {
    return () => {
      if (saveTimer.current) clearTimeout(saveTimer.current);
    };
  }, [block.id]);

  const draft = history[index];

  // BlockDraft's fields are concrete per-type interfaces (HeroSettings, etc)
  // without index signatures, so TypeScript won't assign them to the
  // update API's `Record<string, unknown>` payload shape without this cast
  // — the runtime value is already exactly right, only the static type is too narrow.
  function toPayload(source: BlockDraft) {
    return {
      settings: source.settings,
      styles: source.styles as Record<string, unknown>,
      responsive: source.responsive as Record<string, unknown>,
      visibility: source.visibility as Record<string, unknown>,
      animation: source.animation,
    };
  }

  function scheduleSave(next: BlockDraft) {
    setStatus("unsaved");
    if (saveTimer.current) clearTimeout(saveTimer.current);
    saveTimer.current = setTimeout(() => {
      setStatus("saving");
      updateMutation.mutate(toPayload(next), {
        onSuccess: () => setStatus("saved"),
        onError: () => setStatus("unsaved"),
      });
    }, AUTOSAVE_DELAY_MS);
  }

  function commit(next: BlockDraft) {
    const truncated = history.slice(0, index + 1);
    const nextHistory = [...truncated, next];
    setHistory(nextHistory);
    setIndex(nextHistory.length - 1);
    scheduleSave(next);
  }

  function undo() {
    if (index === 0) return;
    setIndex(index - 1);
    scheduleSave(history[index - 1]);
  }

  function redo() {
    if (index === history.length - 1) return;
    setIndex(index + 1);
    scheduleSave(history[index + 1]);
  }

  function saveNow() {
    if (saveTimer.current) clearTimeout(saveTimer.current);
    setStatus("saving");
    updateMutation.mutate(toPayload(draft), { onSuccess: () => setStatus("saved"), onError: () => setStatus("unsaved") });
  }

  return {
    draft,
    setSettings: (settings: Record<string, unknown>) => commit({ ...draft, settings }),
    setStyles: (styles: HomepageBlockStyles) => commit({ ...draft, styles }),
    setResponsive: (responsive: HomepageBlockResponsive) => commit({ ...draft, responsive }),
    setVisibility: (visibility: HomepageBlockVisibility) => commit({ ...draft, visibility }),
    setAnimation: (animation: HomepageBlockAnimation) => commit({ ...draft, animation }),
    undo,
    redo,
    saveNow,
    canUndo: index > 0,
    canRedo: index < history.length - 1,
    status,
  };
}
