import type { CustomHtmlSettings } from "@/types/homepage-block";

export function CustomHtmlBlock({ settings }: { settings: CustomHtmlSettings }) {
  if (!settings.html) return null;

  // staff-authored raw HTML (builder.edit only), same trust boundary as any other admin-authored content
  return <div dangerouslySetInnerHTML={{ __html: settings.html }} />;
}
