import AppLayout from '@/Layouts/AppLayout';
import { useBusiness, useAuth } from '@/Hooks/usePageProps';

export default function PontoWelcome() {
  const business = useBusiness();
  const auth = useAuth();

  return (
    <AppLayout title="Ponto WR2 · React">
      <div className="mx-auto max-w-4xl p-8">
        <h1 className="text-3xl font-bold text-foreground">
          Ponto WR2 — nova interface
        </h1>
        <p className="mt-2 text-muted-foreground">
          Primeira página renderizada via Inertia + React + shadcn/ui + Tailwind 4.
        </p>

        <div className="mt-8 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="rounded-lg border border-border bg-card p-6">
            <p className="text-sm text-muted-foreground">Business</p>
            <p className="mt-1 text-xl font-semibold">
              {business ? business.name : '—'}
            </p>
            <p className="mt-2 text-xs text-muted-foreground">
              ID: {business?.id ?? '—'}
            </p>
          </div>

          <div className="rounded-lg border border-border bg-card p-6">
            <p className="text-sm text-muted-foreground">Usuário</p>
            <p className="mt-1 text-xl font-semibold">
              {auth.user ? auth.user.name : 'Anônimo'}
            </p>
            <p className="mt-2 text-xs text-muted-foreground">
              {auth.user?.email ?? '—'}
            </p>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
