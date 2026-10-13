'use client';

import { PageHeader } from '@/components/ui';
import { PackageManager } from '@/components/package-manager';

export default function AdminPackagesPage() {
  return (
    <>
      <PageHeader title="Paket" description="Semua paket belajar di platform (termasuk non-aktif)." />
      <PackageManager showInstitution />
    </>
  );
}