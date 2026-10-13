'use client';

import { PageHeader } from '@/components/ui';
import { CertificateReview } from '@/components/certificate-review';

export default function LembagaCertificatesPage() {
  return (
    <>
      <PageHeader title="Sertifikat" description="Setujui atau tolak permohonan sertifikat siswa lembaga Anda." />
      <CertificateReview showHeader={false} />
    </>
  );
}