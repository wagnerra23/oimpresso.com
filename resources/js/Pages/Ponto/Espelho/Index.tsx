// @docvault
//   tela: /ponto/espelho
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-007
//   rules: R-PONT-001, R-PONT-005
//   adrs: ui/0001
//   tests: Modules/PontoWr2/Tests/Feature/EspelhoIndexTest

import AppShellV2 from '@/Layouts/AppShellV2';
import PontoSubNav from '@/Pages/Ponto/_shared/PontoSubNav';
import { Deferred, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ClipboardList, Search, ArrowRight } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';

interface Colaborador {
  id: number;
  matricula: string | null;
  cpf: string | null;
  nome: string;
  email: string | null;
}

interface Paginated {
  data: Colaborador[];
  total: number;
  current_page: number;
  last_page: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
  // colaboradores vem via Inertia::defer — undefined no first render
  colaboradores?: Paginated;
  mes: string;
}

export default function EspelhoIndex({ colaboradores, mes }: Props) {
  // Guarda defensiva (defesa dupla com o <Deferred>): colaboradores é undefined
  // no first render.
  const rows = colaboradores?.data ?? [];
  const onMesChange = (novoMes: string) => {
    // D-14: partial reload — só re-busca o que muda com o mês. A lista de
    // colaboradores não depende de `mes` (só os links de destino usam) → fora
    // do only:, a closure defer nem roda no server.
    router.get('/ponto/espelho', { mes: novoMes }, { preserveState: true, preserveScroll: true, only: ['mes'] });
  };

  return (
    <>
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        {/* ADR 0182 PageHeader canon — Wave Ponto 2026-05-22 */}
        <header className="os-page-h">
          <div className="os-page-h-l">
            <h1>Espelho <span className="text-stone-400 font-normal">· Folha mensal</span></h1>
            <p>Selecione um colaborador para ver o espelho mensal detalhado.</p>
          </div>
          <div className="os-page-h-r">
            <PontoSubNav active="espelho" hidePrimary />
          </div>
        </header>

        <Card>
          <CardContent className="pt-4">
            <div className="flex flex-col md:flex-row gap-3">
              <div className="flex-1 flex items-center gap-2 rounded-md border border-input bg-background px-3 py-1.5">
                <Search size={14} className="text-muted-foreground" />
                <Input
                  placeholder="Buscar por matrícula, nome ou CPF (em breve)"
                  disabled
                  className="h-7 border-0 bg-transparent p-0 text-sm shadow-none focus-visible:ring-0"
                />
              </div>
              <div className="flex items-center gap-2">
                <label htmlFor="mes" className="text-xs text-muted-foreground whitespace-nowrap">
                  Mês de referência:
                </label>
                <Input
                  id="mes"
                  type="month"
                  value={mes}
                  onChange={(e) => onMesChange(e.target.value)}
                  className="w-40"
                />
              </div>
            </div>
          </CardContent>
        </Card>

        <Deferred data="colaboradores" fallback={<Skeleton className="h-64 w-full" />}>
        <Card>
          <CardContent className="p-0">
            {rows.length === 0 ? (
              <div className="p-12 text-center text-muted-foreground text-sm">
                Nenhum colaborador com controle de ponto ativo.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                    <tr>
                      <th className="text-left p-3 font-medium">Matrícula</th>
                      <th className="text-left p-3 font-medium">Colaborador</th>
                      <th className="text-left p-3 font-medium">CPF</th>
                      <th className="text-left p-3 font-medium">E-mail</th>
                      <th className="text-right p-3 font-medium">Espelho</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {rows.map((c) => (
                      <tr key={c.id} className="hover:bg-accent/30 transition-colors">
                        <td className="p-3 font-mono text-xs">{c.matricula ?? '—'}</td>
                        <td className="p-3 font-medium">{c.nome}</td>
                        <td className="p-3 text-xs font-mono">{c.cpf ?? '—'}</td>
                        <td className="p-3 text-xs text-muted-foreground">{c.email ?? '—'}</td>
                        <td className="p-3 text-right">
                          <Button size="sm" asChild>
                            <Link href={`/ponto/espelho/${c.id}?mes=${mes}`} className="text-xs gap-1">
                              Ver {mes} <ArrowRight size={12} />
                            </Link>
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {(colaboradores?.last_page ?? 1) > 1 && (
              <div className="flex items-center justify-between border-t border-border p-3 text-xs">
                <span className="text-muted-foreground">
                  Página {colaboradores?.current_page ?? 1} de {colaboradores?.last_page ?? 1} · {colaboradores?.total ?? 0} colaborador(es)
                </span>
                <div className="flex gap-1">
                  {(colaboradores?.links ?? []).map((link, i) => (
                    <Button
                      key={i}
                      variant={link.active ? 'default' : 'outline'}
                      size="sm"
                      className="h-7 min-w-8 px-2 text-xs"
                      disabled={!link.url}
                      // D-14: partial reload — paginação só re-busca a página da lista
                      onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true, only: ['colaboradores', 'mes'] })}
                    >
                      <span dangerouslySetInnerHTML={{ __html: link.label }} />
                    </Button>
                  ))}
                </div>
              </div>
            )}
          </CardContent>
        </Card>
        </Deferred>
      </div>
    </>
  );
}

EspelhoIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Espelho de Ponto" breadcrumbItems={[{ label: 'Ponto WR2' }, { label: 'Espelho' }]}>
    {page}
  </AppShellV2>
);
