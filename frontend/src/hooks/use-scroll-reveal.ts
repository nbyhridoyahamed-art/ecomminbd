"use client";

import { useEffect, useRef, useState } from "react";

/** Adds the `hb-anim-in` class once the element scrolls into view — the trigger for globals.css's `.hb-anim-*` mount animations. */
export function useScrollReveal<T extends HTMLElement>() {
  const ref = useRef<T>(null);
  const [revealed, setRevealed] = useState(() => typeof IntersectionObserver === "undefined");

  useEffect(() => {
    const node = ref.current;
    if (!node || revealed) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setRevealed(true);
          observer.disconnect();
        }
      },
      { threshold: 0.15 },
    );
    observer.observe(node);

    return () => observer.disconnect();
  }, [revealed]);

  return { ref, revealed };
}
