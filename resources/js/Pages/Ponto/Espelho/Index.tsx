// @docvault
//   tela: /ponto/espelho
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-007
//   rules: R-PONT-001, R-PONT-005
//   adrs: ui/0001
//   tests: Modules/PontoWr2/Tests/Feature/EspelhoIndexTest

import AppShellV2 from '@/Layouts/AppShellV2';
import PontoAreaHeader from '@/Pages/Ponto/_shared/PontoAreaHeader';
import { Deferred, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Database } from 'lucide-react';
import { Inline } from '@/Components/layout';
import Toolbar from '@/Components/shared/Toolbar';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
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
  from?: number | null;
  to?: number | null;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
  // colaboradores vem via Inertia::defer — undefined no first render
  colaboradores?: Paginated;
  mes: string;
}

// Forma da `table.pt-tbl` do protótipo (ponto-page.css:58-68), só com tokens:
// th 11px uppercase .07em em --text-dim (--text-mute reprova AA), td 12.5px,
// sub-linha 10.5px, hover accent 5%.
const th = 'whitespace-nowrap border-b border-border px-2.5 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.07em] text-[var(--text-dim)]';
const td = 'px-2.5 py-[7px] align-middle';

export default function EspelhoIndex({ colaboradores, mes }: Props) {
  // Guarda defensiva (defesa dupla com o <Deferred>): colaboradores é undefined
  // no first render.
  const rows = colaboradores?.data ?? [];
  const total = colaboradores?.total ?? 0;
  const onMesChange = (novoMes: string) => {
    // D-14: partial reload — só re-busca o que muda com o mês. A lista de
    // colaboradores não depende de `mes` (só os links de destino usam) → fora
    // do only:, a closure defer nem roda no server.
    router.get('/ponto/espelho', { mes: novoMes }, { preserveState: true, preserveScroll: true, only: ['mes'] });
  };

  return (
    <>
      {/* `ponto-root` e `pt-body` são ganchos de medição — os seletores do ALVO em
          governance/design/targets/ponto--espelho--index.secoes.json. Não têm CSS próprio. */}
      <div className="ponto-root mx-auto max-w-7xl p-6 space-y-4">
        {/* ADR 0182 PageHeader canon — Wave Ponto 2026-05-22 */}
        <PontoAreaHeader active="espelho" />

        <div className="pt-body space-y-4">
          {/* BARRA — `window.PtBarra` do protótipo: Toolbar do DS dentro da própria moldura.
              Escala e "Só com divergência" do protótipo não entram: o controller não tem
              parâmetro nem dado pra eles (thread 33, campo inexistente). */}
          <div className="overflow-hidden rounded-xl border border-border bg-card">
            <Toolbar label="Filtros do espelho" bordered={false}>
              <label htmlFor="mes" className="flex min-w-[150px] flex-col gap-1 text-xs text-muted-foreground">
                Mês de referência
                <Input
                  id="mes"
                  type="month"
                  value={mes}
                  onChange={(e) => onMesChange(e.target.value)}
                  className="h-8 w-40"
                />
              </label>
              <label htmlFor="espelho-busca" className="flex min-w-0 flex-[1_1_260px] flex-col gap-1 text-xs text-muted-foreground">
                Buscar
                <Input id="espelho-busca" placeholder="Nome ou matrícula (em breve)" disabled className="h-8" />
              </label>
            </Toolbar>
          </div>

          {/* LISTA — Card "Colaboradores" do protótipo, paginado no SERVIDOR (W11, ADR 0418). */}
          <div>
          <Deferred data="colaboradores" fallback={<Skeleton className="h-64 w-full" />}>
            <section aria-labelledby="espelho-colaboradores-titulo">
              <Card flush className="gap-3 py-4">
                <CardHeader>
                  <CardTitle as="h2" id="espelho-colaboradores-titulo" badge={`(${total} ativos)`}>
                    <Inline gap={2} align="center" className="min-w-0">
                      <Database size={15} aria-hidden />
                      <span>Colaboradores</span>
                    </Inline>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  {rows.length === 0 ? (
                    <div className="p-12 text-center text-[12.5px] text-[var(--text-dim)]">
                      Nenhum colaborador com controle de ponto ativo.
                    </div>
                  ) : (
                    <div className="overflow-x-auto">
                      <table className="w-full text-[12.5px]">
                        <thead>
                          <tr>
                            <th scope="col" className={`${th} w-[92px]`}>Matrícula</th>
                            <th scope="col" className={th}>Colaborador</th>
                            <th scope="col" className={th}>CPF</th>
                            <th scope="col" className={`${th} w-[120px] text-right`}>Ação</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                          {rows.map((c) => (
                            <tr key={c.id} className="transition-colors hover:bg-[color-mix(in_oklch,var(--accent)_5%,transparent)]">
                              <td className={`${td} font-mono text-[11.5px]`}>{c.matricula ?? '—'}</td>
                              <td className={td}>
                                <b className="font-semibold">{c.nome}</b>
                                <small className="block text-[10.5px] text-[var(--text-dim)]">{c.email ?? 'sem e-mail'}</small>
                              </td>
                              <td className={`${td} font-mono text-[11.5px]`}>{c.cpf ?? '—'}</td>
                              <td className={`${td} text-right`}>
                                <Button size="sm" asChild>
                                  <Link href={`/ponto/espelho/${c.id}?mes=${mes}`} className="text-xs">
                                    Ver espelho
                                  </Link>
                                </Button>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}

                  {/* Rodapé sempre visível, como o Pager do protótipo. Sem seletor de tamanho de
                      página: o servidor pagina em 25 (charter §Goals). */}
                  {total > 0 && (
                    <div className="flex items-center justify-between gap-2 border-t border-border px-3 pt-3 text-xs">
                      <span className="text-muted-foreground">
                        Mostrando <b>{colaboradores?.from ?? 0}</b>–<b>{colaboradores?.to ?? 0}</b> de <b>{total}</b> colaboradores
                      </span>
                      {(colaboradores?.last_page ?? 1) > 1 && (
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
                      )}
                    </div>
                  )}
                </CardContent>
              </Card>
            </section>
          </Deferred>
          </div>
        </div>
      </div>
    </>
  );
}

EspelhoIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Espelho de Ponto" breadcrumbItems={[{ label: 'Ponto WR2' }, { label: 'Espelho' }]}>
    {page}
  </AppShellV2>
);
