'use client';

import { PageHeader } from '@/components/ui';
import { CurriculumManager } from '@/components/curriculum-manager';

export default function LembagaCurriculaPage() {
  return (
    <>
      <PageHeader title="Kurikulum Lembaga" description="Buat dan kelola kurikulum serta modul lembaga Anda." />
      <CurriculumManager />
    </>
  );
}