import type { CustomCssSettings } from "@/types/homepage-block";

export function CustomCssBlock({ settings }: { settings: CustomCssSettings }) {
  if (!settings.css) return null;

  // staff-authored raw CSS (builder.edit only), same trust boundary as any other admin-authored content
  return <style dangerouslySetInnerHTML={{ __html: settings.css }} />;
}
