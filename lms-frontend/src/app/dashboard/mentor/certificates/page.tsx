'use client';

import { PageHeader } from '@/components/ui';
import { CertificateReview } from '@/components/certificate-review';

export default function MentorCertificatesPage() {
  return (
    <>
      <PageHeader title="Sertifikat" description="Setujui atau tolak permohonan sertifikat siswa Anda." />
      <CertificateReview showHeader={false} />
    </>
  );
}