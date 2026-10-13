import type { SVGProps } from 'react';

/** Maskot ramah (sudut membulat) - dipakai di halaman masuk, landing, & dasbor. */
export function Mascot({ className, ...props }: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 120 120" fill="none" className={className} aria-hidden="true" {...props}>
      <ellipse cx="60" cy="112" rx="34" ry="7" fill="#000" opacity=".12" />
      <circle cx="60" cy="58" r="40" fill="#FFD79A" />
      <path d="M20 58a40 40 0 0 1 80 0c0-26-16-44-40-44S20 32 20 58z" fill="#4B3BD4" />
      <circle cx="46" cy="60" r="6" fill="#23254D" />
      <circle cx="74" cy="60" r="6" fill="#23254D" />
      <circle cx="48" cy="58" r="2" fill="#fff" />
      <circle cx="76" cy="58" r="2" fill="#fff" />
      <path d="M50 74c4 5 16 5 20 0" stroke="#23254D" strokeWidth="4" strokeLinecap="round" />
      <circle cx="34" cy="70" r="6" fill="#FF8A8A" opacity=".55" />
      <circle cx="86" cy="70" r="6" fill="#FF8A8A" opacity=".55" />
    </svg>
  );
}
