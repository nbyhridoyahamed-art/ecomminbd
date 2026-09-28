import type { VideoSettings } from "@/types/homepage-block";

export function VideoBlock({ settings }: { settings: VideoSettings }) {
  return (
    <section className="space-y-4">
      {settings.heading ? <h2 className="text-section font-semibold text-text-primary">{settings.heading}</h2> : null}
      <video
        src={settings.video_url}
        poster={settings.poster_image_url ?? undefined}
        controls
        className="w-full rounded-lg"
        {...(settings.autoplay ? { autoPlay: true, muted: true, loop: true, playsInline: true } : {})}
      />
    </section>
  );
}
