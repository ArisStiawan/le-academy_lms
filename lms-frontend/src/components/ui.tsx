import type { ButtonHTMLAttributes, InputHTMLAttributes, ReactNode } from 'react';

function cx(...parts: (string | false | null | undefined)[]) {
  return parts.filter(Boolean).join(' ');
}

/* ───────────────────────── Button ───────────────────────── */
/* Gaya "chunky" ala Duolingo: sudut membulat + shadow bawah, turun saat ditekan. */

type ButtonVariant = 'primary' | 'outline' | 'ghost' | 'danger' | 'success' | 'sun';
type ButtonSize = 'sm' | 'md' | 'lg';

const BUTTON_VARIANTS: Record<ButtonVariant, string> = {
  primary:
    'bg-brand text-brand-fg shadow-[0_4px_0_var(--brand-dark)] hover:brightness-105 active:translate-y-[3px] active:shadow-[0_1px_0_var(--brand-dark)]',
  success:
    'bg-mint text-white shadow-[0_4px_0_var(--mint-dark)] hover:brightness-105 active:translate-y-[3px] active:shadow-[0_1px_0_var(--mint-dark)]',
  sun:
    'bg-sun text-[#6a4b00] shadow-[0_4px_0_var(--sun-dark)] hover:brightness-105 active:translate-y-[3px] active:shadow-[0_1px_0_var(--sun-dark)]',
  outline:
    'border-2 border-border bg-card text-foreground shadow-[0_4px_0_var(--border)] hover:bg-brand-soft active:translate-y-[3px] active:shadow-[0_1px_0_var(--border)]',
  ghost: 'text-foreground hover:bg-brand-soft',
  danger:
    'bg-coral text-white shadow-[0_4px_0_var(--coral-dark)] hover:brightness-105 active:translate-y-[3px] active:shadow-[0_1px_0_var(--coral-dark)]',
};

const BUTTON_SIZES: Record<ButtonSize, string> = {
  sm: 'h-9 px-4 text-sm rounded-[12px]',
  md: 'h-11 px-5 text-sm rounded-[14px]',
  lg: 'h-12 px-7 text-base rounded-[16px]',
};

export function Button({
  variant = 'primary',
  size = 'md',
  className,
  loading,
  children,
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: ButtonVariant;
  size?: ButtonSize;
  loading?: boolean;
}) {
  return (
    <button
      {...props}
      disabled={props.disabled || loading}
      className={cx(
        'inline-flex select-none items-center justify-center gap-2 font-extrabold transition-[transform,box-shadow,filter] duration-100',
        'disabled:cursor-not-allowed disabled:translate-y-0 disabled:opacity-60 disabled:shadow-none',
        BUTTON_VARIANTS[variant],
        BUTTON_SIZES[size],
        className,
      )}
    >
      {loading && <Spinner className="h-4 w-4" />}
      {children}
    </button>
  );
}

/* ───────────────────────── Card ───────────────────────── */

export function Card({ className, children }: { className?: string; children: ReactNode }) {
  return (
    <div
      className={cx(
        'rounded-[22px] border border-border bg-card p-5 shadow-[0_10px_30px_-18px_rgba(35,37,77,0.35)]',
        className,
      )}
    >
      {children}
    </div>
  );
}

export function CardHeader({
  title,
  subtitle,
  action,
}: {
  title: ReactNode;
  subtitle?: ReactNode;
  action?: ReactNode;
}) {
  return (
    <div className="mb-3 flex items-start justify-between gap-3">
      <div>
        <h3 className="font-display text-lg font-extrabold">{title}</h3>
        {subtitle && <p className="mt-0.5 text-sm font-semibold text-muted">{subtitle}</p>}
      </div>
      {action}
    </div>
  );
}

/* ───────────────────────── Badge ───────────────────────── */

type BadgeTone = 'neutral' | 'brand' | 'success' | 'warning' | 'danger' | 'info';

const TONE_TILE: Record<BadgeTone, string> = {
  neutral: 'bg-[#eef0f9] text-muted',
  brand: 'bg-brand-soft text-brand-dark',
  success: 'bg-mint-soft text-mint-dark',
  warning: 'bg-sun-soft text-[#8a6200]',
  danger: 'bg-coral-soft text-coral-dark',
  info: 'bg-sky-soft text-sky-dark',
};

export function Badge({
  tone = 'neutral',
  className,
  children,
}: {
  tone?: BadgeTone;
  className?: string;
  children: ReactNode;
}) {
  return (
    <span
      className={cx(
        'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-extrabold',
        TONE_TILE[tone],
        className,
      )}
    >
      {children}
    </span>
  );
}

/* ───────────────────────── Input ───────────────────────── */

export function Input({ className, label, ...props }: InputHTMLAttributes<HTMLInputElement> & { label?: string }) {
  return (
    <label className="block">
      {label && <span className="mb-1.5 block text-sm font-extrabold">{label}</span>}
      <input
        {...props}
        className={cx(
          'h-12 w-full rounded-[14px] border-2 border-border bg-background px-4 text-sm font-semibold text-foreground outline-none transition',
          'placeholder:text-muted/70 focus:border-brand focus:bg-card focus:ring-4 focus:ring-brand-soft',
          className,
        )}
      />
    </label>
  );
}

/* ───────────────────────── Spinner ───────────────────────── */

export function Spinner({ className }: { className?: string }) {
  return (
    <svg className={cx('animate-spin', className ?? 'h-5 w-5')} viewBox="0 0 24 24" fill="none">
      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
    </svg>
  );
}

/* ───────────────────────── Stat Card ───────────────────────── */

export function StatCard({
  label,
  value,
  hint,
  tone = 'brand',
  icon,
}: {
  label: string;
  value: ReactNode;
  hint?: ReactNode;
  tone?: BadgeTone;
  icon?: ReactNode;
}) {
  return (
    <Card className="flex items-center gap-4">
      {icon && (
        <span className={cx('grid h-12 w-12 shrink-0 place-items-center rounded-2xl', TONE_TILE[tone])}>
          {icon}
        </span>
      )}
      <div className="min-w-0">
        <p className="text-xs font-extrabold uppercase tracking-wide text-muted">{label}</p>
        <p className="font-display text-2xl font-extrabold leading-tight">{value}</p>
        {hint && (
          <div className="mt-1">
            <Badge tone={tone}>{hint}</Badge>
          </div>
        )}
      </div>
    </Card>
  );
}

/* ───────────────────────── Empty & Error ───────────────────────── */

export function EmptyState({ title, description }: { title: string; description?: string }) {
  return (
    <div className="rounded-[22px] border-2 border-dashed border-border bg-card/60 p-10 text-center">
      <div className="mx-auto mb-3 grid h-14 w-14 place-items-center rounded-2xl bg-brand-soft">
        <span className="text-2xl">✨</span>
      </div>
      <p className="font-display text-lg font-extrabold">{title}</p>
      {description && <p className="mt-1 text-sm text-muted">{description}</p>}
    </div>
  );
}

export function ErrorNote({ children }: { children: ReactNode }) {
  return (
    <div className="rounded-[14px] border-2 border-coral/30 bg-coral-soft px-4 py-3 text-sm font-semibold text-coral-dark">
      {children}
    </div>
  );
}

/* ───────────────────────── Page Header ───────────────────────── */

export function PageHeader({
  title,
  description,
  action,
}: {
  title: string;
  description?: string;
  action?: ReactNode;
}) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 className="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">{title}</h1>
        {description && <p className="mt-1 text-sm font-semibold text-muted">{description}</p>}
      </div>
      {action}
    </div>
  );
}
