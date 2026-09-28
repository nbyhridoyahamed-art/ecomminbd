"use client";

import { useEffect, useState } from "react";

export interface CountdownValue {
  days: number;
  hours: number;
  minutes: number;
  seconds: number;
  expired: boolean;
}

function computeCountdown(endsAt: string): CountdownValue {
  const remainingMs = new Date(endsAt).getTime() - Date.now();

  if (remainingMs <= 0) {
    return { days: 0, hours: 0, minutes: 0, seconds: 0, expired: true };
  }

  const totalSeconds = Math.floor(remainingMs / 1000);

  return {
    days: Math.floor(totalSeconds / 86400),
    hours: Math.floor((totalSeconds % 86400) / 3600),
    minutes: Math.floor((totalSeconds % 3600) / 60),
    seconds: totalSeconds % 60,
    expired: false,
  };
}

/**
 * Ticks every second toward `endsAt`, clamped to zero once it has passed —
 * the sole home of the countdown interval. countdown-block.tsx and
 * flash-sale-block.tsx both consume this instead of running their own
 * `setInterval`.
 */
export function useCountdown(endsAt: string): CountdownValue {
  const [value, setValue] = useState(() => computeCountdown(endsAt));

  useEffect(() => {
    setValue(computeCountdown(endsAt));

    const interval = setInterval(() => {
      setValue(computeCountdown(endsAt));
    }, 1000);

    return () => clearInterval(interval);
  }, [endsAt]);

  return value;
}
