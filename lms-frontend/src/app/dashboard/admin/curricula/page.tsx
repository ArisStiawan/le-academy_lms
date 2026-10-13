'use client';

import { PageHeader } from '@/components/ui';
import { CurriculumManager } from '@/components/curriculum-manager';

export default function AdminCurriculaPage() {
  return (
    <>
      <PageHeader title="Kurikulum" description="Kelola kurikulum platform Maxy Academy." />
      <CurriculumManager />
    </>
  );
}