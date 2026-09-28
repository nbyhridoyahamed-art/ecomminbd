"use client";

import { EditorContent, useEditor } from "@tiptap/react";
import StarterKit from "@tiptap/starter-kit";
import { Bold, Heading2, Italic, Link as LinkIcon, List, ListOrdered, Quote } from "lucide-react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

interface RichTextEditorProps {
  value: string;
  onChange: (html: string) => void;
  placeholder?: string;
}

/**
 * The shared TipTap editor (spec sections 3, 58, 68) — first real use of
 * the "professional rich editor" the tech stack calls for, scoped to the
 * homepage builder's Rich Text block. Phase 14's blog editor is expected
 * to reuse this same component rather than introduce its own.
 */
export function RichTextEditor({ value, onChange, placeholder }: RichTextEditorProps) {
  const editor = useEditor({
    extensions: [StarterKit],
    content: value,
    immediatelyRender: false,
    editorProps: {
      attributes: {
        class: "rich-text-content min-h-32 px-3 py-2 focus:outline-none",
      },
    },
    onUpdate: ({ editor }) => onChange(editor.getHTML()),
  });

  if (!editor) {
    return <div className="min-h-32 rounded-md border border-border bg-surface-secondary" />;
  }

  const buttons: { icon: React.ReactNode; label: string; active: boolean; onClick: () => void }[] = [
    { icon: <Bold className="size-4" />, label: "Bold", active: editor.isActive("bold"), onClick: () => editor.chain().focus().toggleBold().run() },
    { icon: <Italic className="size-4" />, label: "Italic", active: editor.isActive("italic"), onClick: () => editor.chain().focus().toggleItalic().run() },
    { icon: <Heading2 className="size-4" />, label: "Heading", active: editor.isActive("heading", { level: 2 }), onClick: () => editor.chain().focus().toggleHeading({ level: 2 }).run() },
    { icon: <List className="size-4" />, label: "Bullet list", active: editor.isActive("bulletList"), onClick: () => editor.chain().focus().toggleBulletList().run() },
    { icon: <ListOrdered className="size-4" />, label: "Numbered list", active: editor.isActive("orderedList"), onClick: () => editor.chain().focus().toggleOrderedList().run() },
    { icon: <Quote className="size-4" />, label: "Quote", active: editor.isActive("blockquote"), onClick: () => editor.chain().focus().toggleBlockquote().run() },
    {
      icon: <LinkIcon className="size-4" />,
      label: "Link",
      active: editor.isActive("link"),
      onClick: () => {
        const url = window.prompt("Link URL");
        if (url) editor.chain().focus().setLink({ href: url }).run();
        else if (url === "") editor.chain().focus().unsetLink().run();
      },
    },
  ];

  return (
    <div className="rounded-md border border-border">
      <div className="flex flex-wrap gap-1 border-b border-border bg-surface-secondary p-1.5">
        {buttons.map((button) => (
          <Button
            key={button.label}
            type="button"
            variant={button.active ? "secondary" : "ghost"}
            size="icon"
            className="size-8"
            aria-label={button.label}
            onClick={button.onClick}
          >
            {button.icon}
          </Button>
        ))}
      </div>
      <EditorContent editor={editor} className={cn(!value && "text-text-muted")} placeholder={placeholder} />
    </div>
  );
}
