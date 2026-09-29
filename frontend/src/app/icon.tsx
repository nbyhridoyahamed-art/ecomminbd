import { ImageResponse } from "next/og";

export const size = { width: 32, height: 32 };
export const contentType = "image/png";

// Same hexagon silhouette as components/shared/logo.tsx's LogoMark, as
// clip-path percentages — Satori (which renders this route) can't render
// raw SVG <text>/<path>, only the HTML+CSS subset it documents.
const HEX_CLIP_PATH = "polygon(50% 3.75%, 92.8% 26.875%, 92.8% 73.125%, 50% 96.25%, 7.19% 73.125%, 7.19% 26.875%)";

export default function Icon() {
  return new ImageResponse(
    (
      <div
        style={{
          width: "100%",
          height: "100%",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          background: "linear-gradient(135deg, #3b82f6, #1d4ed8)",
          clipPath: HEX_CLIP_PATH,
          color: "#ffffff",
          fontSize: 20,
          fontWeight: 800,
          fontFamily: "sans-serif",
        }}
      >
        e
      </div>
    ),
    { ...size },
  );
}
