// @memcofre
//   tela: /ponto/welcome
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-006
//   tests: Modules/PontoWr2/Tests/Feature/WelcomeTest

import AppShellV2 from '@/Layouts/AppShellV2';
import PontoSubNav from '@/Pages/Ponto/_shared/PontoSubNav';
import type { ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { useBusiness, useAuth } from '@/Hooks/usePageProps';

export default function PontoWelcome() {
  const business = useBusiness();
  const auth = useAuth();

  return (
    <>
      <div className="mx-auto max-w-5xl p-6 space-y-4">
        {/* ADR 0182 PageHeader canon — Wave Ponto 2026-05-22 */}
        <header className="os-page-h">
          <div className="os-page-h-l">
            <h1>Bem-vindo <span className="text-stone-400 font-normal">· Ponto WR2</span></h1>
            <p>Página piloto renderizada via Inertia + React 19 + shadcn/ui + Tailwind 4.</p>
          </div>
          <div className="os-page-h-r">
            <PontoSubNav active="dashboard" hidePrimary />
          </div>
        </header>

        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle className="text-sm text-muted-foreground font-normal">Business</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-xl font-semibold">{business?.name ?? '—'}</p>
              <p className="mt-1 text-xs text-muted-foreground">ID: {business?.id ?? '—'}</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-sm text-muted-foreground font-normal">Usuário</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-xl font-semibold">{auth.user?.name ?? 'Anônimo'}</p>
              <p className="mt-1 text-xs text-muted-foreground">{auth.user?.email ?? '—'}</p>
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  );
}

PontoWelcome.layout = (page: ReactNode) => (
  <AppShellV2 title="Ponto WR2 · Bem-vindo" breadcrumbItems={[{ label: 'Ponto WR2' }, { label: 'Bem-vindo' }]}>
    {page}
  </AppShellV2>
);
