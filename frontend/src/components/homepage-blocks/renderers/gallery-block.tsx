import type { GallerySettings } from "@/types/homepage-block";

export function GalleryBlock({ settings }: { settings: GallerySettings }) {
  if (settings.images.length === 0) return null;

  return (
    <section className="space-y-4">
      {settings.heading ? <h2 className="text-section font-semibold text-text-primary">{settings.heading}</h2> : null}
      <div className="grid grid-cols-2 gap-3 tablet:grid-cols-4">
        {settings.images.map((image, index) => (
          // eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset
          <img key={index} src={image.image_url} alt={image.alt_text ?? ""} className="aspect-square w-full rounded-lg object-cover" />
        ))}
      </div>
    </section>
  );
}
