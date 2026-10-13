export type Role = 'admin' | 'mentor' | 'siswa' | 'karyawan' | 'lembaga';

/** Base path dasbor masing-masing role (role-based routing). */
export const ROLE_DASHBOARD: Record<Role, string> = {
  admin: '/dashboard/admin',
  mentor: '/dashboard/mentor',
  siswa: '/dashboard/siswa',
  karyawan: '/dashboard/karyawan',
  lembaga: '/dashboard/lembaga',
};

/** Label tampilan per role. */
export const ROLE_LABEL: Record<Role, string> = {
  admin: 'Administrator',
  mentor: 'Mentor',
  siswa: 'Siswa',
  karyawan: 'Karyawan',
  lembaga: 'Lembaga Bimbingan',
};

/** Navigasi sidebar per role (ikon dipetakan di @/components/icons → NAV_ICON). */
export const NAV_ITEMS: Record<Role, { href: string; label: string; icon: string }[]> = {
  admin: [
    { href: '/dashboard/admin', label: 'Ringkasan', icon: 'home' },
    { href: '/dashboard/admin/users', label: 'Pengguna', icon: 'users' },
    { href: '/dashboard/admin/curricula', label: 'Kurikulum', icon: 'book' },
    { href: '/dashboard/admin/packages', label: 'Paket', icon: 'box' },
    { href: '/dashboard/admin/orders', label: 'Penjualan', icon: 'cart' },
    { href: '/dashboard/admin/certificates', label: 'Sertifikat', icon: 'award' },
  ],
  mentor: [
    { href: '/dashboard/mentor', label: 'Ringkasan', icon: 'home' },
    { href: '/dashboard/mentor/curricula', label: 'Kurikulum Saya', icon: 'book' },
    { href: '/dashboard/mentor/schedules', label: 'Jadwal Bimbingan', icon: 'calendar' },
    { href: '/dashboard/mentor/forum', label: 'Forum Diskusi', icon: 'chat' },
    { href: '/dashboard/mentor/certificates', label: 'Sertifikat', icon: 'award' },
  ],
  siswa: [
    { href: '/dashboard/siswa', label: 'Ringkasan', icon: 'home' },
    { href: '/dashboard/siswa/materi', label: 'Materi', icon: 'book' },
    { href: '/dashboard/siswa/tugas-ujian', label: 'Tugas & Ujian', icon: 'clipboard' },
    { href: '/dashboard/siswa/jadwal', label: 'Jadwal Bimbingan', icon: 'calendar' },
    { href: '/dashboard/siswa/packages', label: 'Paket Belajar', icon: 'box' },
    { href: '/dashboard/siswa/leaderboard', label: 'Leaderboard', icon: 'trophy' },
    { href: '/dashboard/siswa/certificates', label: 'Sertifikat Saya', icon: 'award' },
  ],
  karyawan: [
    { href: '/dashboard/karyawan', label: 'Ringkasan', icon: 'home' },
    { href: '/dashboard/karyawan/training', label: 'Pelatihan', icon: 'grad' },
    { href: '/dashboard/karyawan/certificates', label: 'Sertifikasi', icon: 'award' },
  ],
  lembaga: [
    { href: '/dashboard/lembaga', label: 'Ringkasan', icon: 'home' },
    { href: '/dashboard/lembaga/students', label: 'Siswa', icon: 'users' },
    { href: '/dashboard/lembaga/curricula', label: 'Kurikulum', icon: 'book' },
    { href: '/dashboard/lembaga/packages', label: 'Paket & Voucher', icon: 'box' },
    { href: '/dashboard/lembaga/revenue', label: 'Pemasukan', icon: 'wallet' },
    { href: '/dashboard/lembaga/certificates', label: 'Sertifikat', icon: 'award' },
  ],
};

/** Role pertama yang dimiliki user (prioritas admin > mentor > lembaga > karyawan > siswa). */
export function primaryRole(roles: string[]): Role {
  const order: Role[] = ['admin', 'mentor', 'lembaga', 'karyawan', 'siswa'];
  return order.find((r) => roles.includes(r)) ?? 'siswa';
}

/** Redirect path tujuan berdasar role (untuk halaman /dashboard). */
export function dashboardFor(roles: string[]): string {
  return ROLE_DASHBOARD[primaryRole(roles)];
}

/** Apakah role boleh mengakses path tertentu? (guard sederhana). */
export function canAccess(roles: string[], pathname: string): boolean {
  for (const role of roles) {
    const roleBase = ROLE_DASHBOARD[role as Role];
    if (!roleBase || roleBase === '/dashboard/admin') continue;
    if (pathname.startsWith(roleBase)) return true;
  }
  // /dashboard mentah → semua user login boleh (redirect ke dashboardFor)
  if (pathname === '/dashboard') return roles.length > 0;
  return false;
}
