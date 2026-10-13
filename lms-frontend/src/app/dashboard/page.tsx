'use client';

import { useRouter } from 'next/navigation';
import { useEffect } from 'react';
import { useAuth } from '@/lib/auth-context';
import { dashboardFor } from '@/lib/roles';
import { Spinner } from '@/components/ui';

export default function DashboardRedirect() {
  const { user, roles, loading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!loading && user) {
      router.replace(dashboardFor(roles));
    }
  }, [loading, user, roles, router]);

  return (
    <div className="flex min-h-[50vh] items-center justify-center">
      <Spinner className="h-8 w-8 text-brand" />
    </div>
  );
}