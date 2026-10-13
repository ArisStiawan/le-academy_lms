'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { useAuth } from '@/lib/auth-context';
import { NAV_ITEMS, ROLE_LABEL, dashboardFor, primaryRole, type Role } from '@/lib/roles';
import { Badge, Button, Spinner } from '@/components/ui';
import { Ic, NAV_ICON } from '@/components/icons';
import api from '@/lib/api';

const ROLE_ACCENT: Record<Role, string> = {
  admin: 'text-coral-dark',
  mentor: 'text-brand-dark',
  siswa: 'text-sun-dark',
  karyawan: 'text-mint-dark',
  lembaga: 'text-sky-dark',
};

export function DashboardShell({ children }: { children: React.ReactNode }) {
  const { user, roles, logout, loading } = useAuth();
  const router = useRouter();
  const pathname = usePathname();
  const [unread, setUnread] = useState<number>(0);
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const role: Role = primaryRole(roles);
  const navItems = NAV_ITEMS[role] ?? [];

  useEffect(() => {
    if (!loading && !user) {
      router.replace('/login');
    }
  }, [loading, user, router]);

  useEffect(() => {
    if (!user) return;
    api
      .get<{ data: { data: { is_read: boolean }[] } }>('/notifications')
      .then((res) => {
        const items = res.data?.data?.data ?? [];
        setUnread(items.filter((n) => !n.is_read).length);
      })
      .catch(() => setUnread(0));
  }, [user, pathname]);

  if (!user) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <Spinner className="h-8 w-8 text-brand" />
      </div>
    );
  }

  async function onLogout() {
    await logout();
    router.replace('/login');
  }

  return (
    <div className="flex min-h-screen">
      {/* Sidebar */}
      <aside
        className={`fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col border-r border-border bg-card transition-transform lg:static lg:translate-x-0 ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="flex h-16 items-center gap-2.5 border-b border-border px-5">
          <div className="grid h-10 w-10 place-items-center rounded-[14px] bg-brand font-display text-lg font-extrabold text-brand-fg shadow-[0_3px_0_var(--brand-dark)]">
            LE
          </div>
          <div>
            <p className="font-display text-[15px] font-extrabold leading-tight">LE Academy</p>
            <p className={`text-xs font-extrabold ${ROLE_ACCENT[role]}`}>{ROLE_LABEL[role]}</p>
          </div>
        </div>

        <nav className="flex-1 space-y-1.5 overflow-y-auto p-3">
          {navItems.map((item) => {
            const active =
              pathname === item.href || (item.href !== '/dashboard' && pathname.startsWith(item.href));
            const Ico = NAV_ICON[item.icon];
            return (
              <Link
                key={item.href}
                href={item.href}
                onClick={() => setSidebarOpen(false)}
                className={`flex items-center gap-3 rounded-[14px] px-3 py-2.5 text-sm font-extrabold transition ${
                  active
                    ? 'bg-brand text-brand-fg shadow-[0_3px_0_var(--brand-dark)]'
                    : 'text-muted hover:bg-brand-soft hover:text-brand-dark'
                }`}
              >
                {Ico && <Ico className="h-5 w-5 shrink-0" />}
                <span>{item.label}</span>
              </Link>
            );
          })}
        </nav>

        <div className="border-t border-border p-3">
          <Link
            href="/notifications"
            className="flex items-center justify-between rounded-[14px] px-3 py-2.5 text-sm font-extrabold text-muted transition hover:bg-brand-soft hover:text-brand-dark"
          >
            <span className="flex items-center gap-3">
              <Ic.bell className="h-5 w-5" />
              Notifikasi
            </span>
            {unread > 0 && <Badge tone="danger">{unread}</Badge>}
          </Link>
        </div>
      </aside>

      {sidebarOpen && (
        <div
          className="fixed inset-0 z-30 bg-black/40 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Main */}
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-border bg-card/85 px-4 backdrop-blur lg:px-8">
          <button
            className="grid h-10 w-10 place-items-center rounded-[14px] text-foreground transition hover:bg-brand-soft lg:hidden"
            onClick={() => setSidebarOpen(true)}
            aria-label="Buka menu"
          >
            <Ic.menu className="h-5 w-5" />
          </button>

          <div className="flex-1" />

          <Link
            href="/notifications"
            className="relative grid h-10 w-10 place-items-center rounded-[14px] text-foreground transition hover:bg-brand-soft"
            aria-label="Notifikasi"
          >
            <Ic.bell className="h-5 w-5" />
            {unread > 0 && (
              <span className="absolute -right-0.5 -top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-coral px-1 text-[10px] font-extrabold text-white">
                {unread}
              </span>
            )}
          </Link>

          <div className="flex items-center gap-3">
            <div className="hidden items-center gap-3 sm:flex">
              <div className="text-right">
                <p className="text-sm font-extrabold leading-tight">{user.name}</p>
                <p className="text-xs font-semibold text-muted">{user.email}</p>
              </div>
              <div className="grid h-10 w-10 place-items-center rounded-full bg-gradient-to-br from-sun to-coral font-display font-extrabold text-white">
                {(user.name ?? '?').charAt(0).toUpperCase()}
              </div>
            </div>
            <Button variant="outline" size="sm" onClick={onLogout}>
              Keluar
            </Button>
          </div>
        </header>

        <main className="flex-1 p-4 lg:p-8">{children}</main>
      </div>
    </div>
  );
}
