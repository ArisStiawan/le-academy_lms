'use client';

import { PageHeader } from '@/components/ui';
import { PackageManager } from '@/components/package-manager';

export default function LembagaPackagesPage() {
  return (
    <>
      <PageHeader
        title="Paket & Voucher"
        description="Paket belajar milik lembaga Anda. Siswa dapat diimpor ke paket ini dari menu Siswa."
      />
      <PackageManager />
    </>
  );
}