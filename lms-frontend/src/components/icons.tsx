import type { ReactNode, SVGProps } from "react";

type P = SVGProps<SVGSVGElement>;

/* Ikon garis ekspresif: tebal, ujung membulat, warna mengikuti currentColor. */
function S({ children, ...p }: P & { children: ReactNode }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={2.4}
      strokeLinecap="round"
      strokeLinejoin="round"
      {...p}
    >
      {children}
    </svg>
  );
}

export const Ic = {
  home: (p: P) => (
    <S {...p}>
      <path d="M3 10.7 12 3.3l9 7.4" />
      <path d="M5.4 9.6V20h13.2V9.6" />
      <path d="M9.6 20v-5.6h4.8V20" />
    </S>
  ),
  users: (p: P) => (
    <S {...p}>
      <circle cx="9" cy="8" r="3.4" />
      <path d="M3.2 20c.6-3.5 3-5.4 5.8-5.4S14.2 16.5 14.8 20" />
      <path d="M16 5.4a3 3 0 0 1 0 5.6M17.4 14.9c2 .6 3.2 2.3 3.6 5.1" />
    </S>
  ),
  book: (p: P) => (
    <S {...p}>
      <path d="M4 5.5A2 2 0 0 1 6 3.5h5.5V20H6a2 2 0 0 1-2-2z" />
      <path d="M20 5.5a2 2 0 0 0-2-2h-5.5V20H18a2 2 0 0 0 2-2z" />
    </S>
  ),
  box: (p: P) => (
    <S {...p}>
      <path d="M12 3 4 7v10l8 4 8-4V7z" />
      <path d="m4 7 8 4 8-4M12 11v10" />
    </S>
  ),
  cart: (p: P) => (
    <S {...p}>
      <path d="M3 4h2.2l2 11h11l2-8H6.4" />
      <circle cx="9" cy="19.5" r="1.6" />
      <circle cx="17" cy="19.5" r="1.6" />
    </S>
  ),
  award: (p: P) => (
    <S {...p}>
      <circle cx="12" cy="9" r="5.2" />
      <path d="m8.6 13.4-1.4 7 4.8-2.4 4.8 2.4-1.4-7" />
    </S>
  ),
  calendar: (p: P) => (
    <S {...p}>
      <rect x="3.5" y="5" width="17" height="15.5" rx="3" />
      <path d="M3.5 9.5h17M8 3v4M16 3v4" />
      <circle cx="8.5" cy="14" r="1.1" fill="currentColor" stroke="none" />
      <circle cx="12" cy="14" r="1.1" fill="currentColor" stroke="none" />
    </S>
  ),
  chat: (p: P) => (
    <S {...p}>
      <path d="M4 5.5h16v10H9l-5 4z" />
      <path d="M8.5 10.5h7" />
    </S>
  ),
  trophy: (p: P) => (
    <S {...p}>
      <path d="M7 4h10v3.6a5 5 0 0 1-10 0z" />
      <path d="M7 5.6H4.6a2.4 2.4 0 0 0 2.6 3.4M17 5.6h2.4a2.4 2.4 0 0 1-2.6 3.4" />
      <path d="M12 12.6V16m-3.4 4h6.8" />
    </S>
  ),
  wallet: (p: P) => (
    <S {...p}>
      <rect x="3.5" y="6" width="17" height="13" rx="3.2" />
      <path d="M3.5 10h17" />
      <circle cx="16.5" cy="14.5" r="1.2" fill="currentColor" stroke="none" />
    </S>
  ),
  grad: (p: P) => (
    <S {...p}>
      <path d="M2.6 8.6 12 4.4l9.4 4.2L12 12.8z" />
      <path d="M6.4 10.6v4.6c0 1.5 2.5 2.6 5.6 2.6s5.6-1.1 5.6-2.6v-4.6M20.6 9v4.6" />
    </S>
  ),
  bell: (p: P) => (
    <S {...p}>
      <path d="M18 8.5c0-3.3-2.7-6-6-6s-6 2.7-6 6c0 6-2.4 8-2.4 8h16.8s-2.4-2-2.4-8" />
      <path d="M13.7 20.5a2 2 0 0 1-3.4 0" />
    </S>
  ),
  search: (p: P) => (
    <S {...p}>
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-3.4-3.4" />
    </S>
  ),
  bolt: (p: P) => (
    <svg viewBox="0 0 24 24" fill="currentColor" {...p}>
      <path d="M13.2 2.4 4.6 13.6h5.6l-1.4 8 8.6-11.2h-5.6z" />
    </svg>
  ),
  flame: (p: P) => (
    <svg viewBox="0 0 24 24" fill="currentColor" {...p}>
      <path d="M13.4 2.2c.4 3-1.3 4.4-2.8 5.7C8.9 9.4 7.4 10.8 7.4 13.6a4.6 4.6 0 0 0 9.2.3c.7 1.2.9 2.3.7 3.2 2.1-1.5 3.1-3.6 3.1-5.7 0-3.7-3-6.2-7-9.2z" />
    </svg>
  ),
  check: (p: P) => (
    <S {...p}>
      <path d="m4.5 12.5 5 5 10-11" />
    </S>
  ),
  chevron: (p: P) => (
    <S {...p}>
      <path d="m9 6 6 6-6 6" />
    </S>
  ),
  logout: (p: P) => (
    <S {...p}>
      <path d="M9 4H6.5A2.5 2.5 0 0 0 4 6.5v11A2.5 2.5 0 0 0 6.5 20H9" />
      <path d="M15 8l4 4-4 4M9 12h10" />
    </S>
  ),
  menu: (p: P) => (
    <S {...p}>
      <path d="M4 7h16M4 12h16M4 17h16" />
    </S>
  ),
  sparkle: (p: P) => (
    <svg viewBox="0 0 24 24" fill="currentColor" {...p}>
      <path d="M12 2.5l2.3 5.2 5.2 2.3-5.2 2.3L12 17.5l-2.3-5.2L4.5 10l5.2-2.3z" />
      <path d="M18.6 15.4l.9 2 2 .9-2 .9-.9 2-.9-2-2-.9 2-.9z" />
    </svg>
  ),
  rocket: (p: P) => (
    <S {...p}>
      <path d="M12.5 3.2c3 0 5.6 2.2 6.3 5.2.9 3.9-1 7.4-3.6 9.6l-2.7-2.7-2.7-2.7c2.2-2.6 5.7-4.5 9.6-3.6" />
      <path d="M9.5 14.5 6 18M6.5 12 4 12.5l1.5-3.5M12 17.5l-.5 2.5 3.5-1.5" />
      <circle cx="14.5" cy="9.5" r="1.5" />
    </S>
  ),
  clipboard: (p: P) => (
    <S {...p}>
      <path d="M9 4.2H7.5A2.5 2.5 0 0 0 5 6.7v11.8A2.5 2.5 0 0 0 7.5 21h9a2.5 2.5 0 0 0 2.5-2.5V6.7a2.5 2.5 0 0 0-2.5-2.5H15" />
      <path d="M9 2.6h6v3.2H9z" />
      <path d="M9 12h6M9 16h4" />
    </S>
  ),
};

export type IconName = keyof typeof Ic;

/** Peta nama ikon (dipakai data nav di lib/roles.ts) → komponen ikon. */
export const NAV_ICON: Record<string, (p: P) => ReactNode> = {
  home: Ic.home,
  users: Ic.users,
  book: Ic.book,
  box: Ic.box,
  cart: Ic.cart,
  award: Ic.award,
  calendar: Ic.calendar,
  chat: Ic.chat,
  trophy: Ic.trophy,
  wallet: Ic.wallet,
  grad: Ic.grad,
  clipboard: Ic.clipboard,
};
