// @docvault
//   tela: /ponto/welcome
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-006
//   tests: Modules/PontoWr2/Tests/Feature/WelcomeTest

import AppShell from '@/Layouts/AppShell';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { useBusiness, useAuth } from '@/Hooks/usePageProps';

export default function PontoWelcome() {
  const business = useBusiness();
  const auth = useAuth();

  return (
    <AppShell
      title="Ponto WR2 · React"
      breadcrumb={[
        { label: 'Ponto WR2' },
        { label: 'Bem-vindo' },
      ]}
    >
      <div className="mx-auto max-w-5xl p-6">
        <header className="mb-6">
          <h1 className="text-2xl font-bold tracking-tight">Ponto WR2 — nova interface</h1>
          <p className="text-sm text-muted-foreground">
            Página piloto renderizada via Inertia + React 19 + shadcn/ui + Tailwind 4.
          </p>
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
    </AppShell>
  );
}
