'use client';

import { PageHeader } from '@/components/ui';
import { CertificateReview } from '@/components/certificate-review';

export default function AdminCertificatesPage() {
  return (
    <>
      <PageHeader title="Sertifikat" description="Tinjau permohonan sertifikat dari seluruh lembaga." />
      <CertificateReview showHeader={false} />
    </>
  );
}