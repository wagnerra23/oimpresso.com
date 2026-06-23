// @memcofre tela=/advisor module=Financeiro
// Onda 31 (2026-05-20) #57 US-FIN-037 — Dashboard do contador parceiro.
// Mostra cards de cada cliente acessível via grant ATIVO + links read-only
// pra /financeiro/unificado e /financeiro/relatorios (middleware AdvisorViewScope
// força readonly + valida grant).
// Portal isolado (sem AppShellV2 — advisor é entidade global, não tem sidebar POS).
// DS v4 — alinhado ao sibling Login.tsx (Card/Button/Badge/KpiCard/EmptyState + tokens).

import { router, Head, Link } from '@inertiajs/react';

import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';
import { Icon } from '@/Components/Icon';

interface ClienteCard {
  access_id: number;
  business_id: number;
  business_name: string;
  granted_at_label: string | null;
  can_view_unificado: boolean;
  can_view_reports: boolean;
  has_consent: boolean;
  url_unificado: string;
  url_relatorios: string;
}

interface Props {
  advisor: {
    id: number;
    nome: string;
    email: string;
    referral_code: string;
  };
  clientes: ClienteCard[];
  total_clientes: number;
}

function Dashboard({ advisor, clientes, total_clientes }: Props) {
  const logout = () => {
    router.post('/advisor/logout');
  };

  const pendentesConsentimento = clientes.filter((c) => !c.has_consent).length;

  return (
    <>
      <Head title="Portal do Contador" />
      <div className="min-h-screen bg-background">
        <header className="border-b bg-card">
          <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
            <div className="flex items-center gap-3 min-w-0">
              <div className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary">
                <Icon name="users" size={20} className="text-primary-foreground" />
              </div>
              <div className="min-w-0">
                <h1 className="text-lg font-semibold text-foreground">Portal do Contador</h1>
                <p className="truncate text-xs text-muted-foreground">
                  {advisor.nome} · {advisor.email}
                </p>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <span className="hidden items-center gap-2 text-xs text-muted-foreground sm:inline-flex">
                Código de indicação
                <code className="rounded bg-muted px-2 py-0.5 font-mono text-foreground">
                  {advisor.referral_code}
                </code>
              </span>
              <Button variant="ghost" size="sm" onClick={logout}>
                <Icon name="log-out" size={16} />
                Sair
              </Button>
            </div>
          </div>
        </header>

        <main className="mx-auto max-w-6xl space-y-6 px-6 py-8">
          <section aria-labelledby="advisor-clientes-heading" className="space-y-1">
            <h2 id="advisor-clientes-heading" className="text-base font-semibold text-foreground">
              Meus clientes
            </h2>
            <p className="text-sm text-muted-foreground">
              Visualização somente leitura. Qualquer alteração precisa ser feita pelo próprio cliente.
            </p>
          </section>

          <section aria-label="Resumo" className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <KpiCard label="Clientes ativos" value={total_clientes} icon="users" tone="info" />
            <KpiCard
              label="Pendentes de consentimento"
              value={pendentesConsentimento}
              icon="shield-alert"
              tone={pendentesConsentimento > 0 ? 'warning' : 'default'}
              description="Sem LGPD registrado, o acesso pode ser bloqueado."
            />
          </section>

          {clientes.length === 0 ? (
            <Card>
              <CardContent className="py-2">
                <EmptyState
                  icon="users"
                  title="Nenhum cliente ativo"
                  description="Peça ao cliente para te adicionar em Financeiro → Configurações → Contador."
                />
              </CardContent>
            </Card>
          ) : (
            <section
              aria-label="Lista de clientes"
              className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
            >
              {clientes.map((c) => (
                <Card key={c.access_id} className="gap-4 py-5">
                  <CardHeader>
                    <CardTitle className="flex items-center justify-between gap-2">
                      <span className="truncate">{c.business_name}</span>
                      {c.has_consent ? (
                        <Badge variant="outline">LGPD ok</Badge>
                      ) : (
                        <Badge variant="destructive">Sem consentimento</Badge>
                      )}
                    </CardTitle>
                    <p className="text-xs text-muted-foreground">
                      Desde {c.granted_at_label ?? '—'}
                    </p>
                  </CardHeader>

                  <CardContent className="space-y-3">
                    {!c.has_consent && (
                      <Alert variant="destructive">
                        <Icon name="shield-alert" size={16} />
                        <AlertDescription>
                          Sem consentimento LGPD registrado. Acesso pode ser bloqueado.
                        </AlertDescription>
                      </Alert>
                    )}

                    <div className="flex flex-wrap gap-2">
                      {c.can_view_unificado && (
                        <Button asChild variant="default" size="sm">
                          <Link href={c.url_unificado}>
                            <Icon name="layout-dashboard" size={16} />
                            Visão Unificada
                          </Link>
                        </Button>
                      )}
                      {c.can_view_reports && (
                        <Button asChild variant="outline" size="sm">
                          <Link href={c.url_relatorios}>
                            <Icon name="file-text" size={16} />
                            Relatórios
                          </Link>
                        </Button>
                      )}
                    </div>
                  </CardContent>
                </Card>
              ))}
            </section>
          )}
        </main>
      </div>
    </>
  );
}

export default Dashboard;
