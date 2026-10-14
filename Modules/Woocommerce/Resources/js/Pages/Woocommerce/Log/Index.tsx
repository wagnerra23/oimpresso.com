// @memcofre
//   tela: /woocommerce/view-sync-log
//   module: Woocommerce
//   stories: thread woocommerce/playbook/02 (Log de sincronização)
//   permissao: superadmin | assinatura woocommerce_module (tipos visíveis pela permissão de sincronizar)
//
// Log das execuções de sincronização com a loja. Atrás da flag `useV2WoocommercePainel` (a mesma do painel);
// desligada, ou com `?classico=1`, a rota segue na Blade `woocommerce::woocommerce.sync_log` (DataTables).
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Woocommerce/RUNBOOK-log.md
// Âncora de design: governance/design/targets/woocommerce--log--index.alvo.json (A1) ← integra-extras.jsx WooCommercePage.
//
// Mesma query do DataTables (WoocommerceController::logsDaTela → paraDatatable): só o negócio da sessão, tipos pela
// permissão. Os erros de cada execução são os do partial `log_details`.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { Fragment, useMemo, useState, type ReactNode } from 'react';
import { ChevronDown, ChevronRight, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Inline, Stack } from '@/Components/layout';
import WoocommerceAbas from '../_components/WoocommerceAbas';

export interface Execucao {
  id: number;
  data: string;
  quando: string;
  tipo: string;
  operacao: string;
  por: string;
  registros: string[];
  erros: string[];
}
interface Props {
  logs?: { itens: Execucao[]; pagina: number; ultima_pagina: number; total: number };
  pode: { configurar: boolean };
}

const plural = (n: number, um: string, varios: string) => `${n} ${n === 1 ? um : varios}`;

function LogIndex({ logs, pode }: Props) {
  const itens = useMemo(() => logs?.itens ?? [], [logs]);
  const [q, setQ] = useState('');
  const [aberta, setAberta] = useState<number | null>(null);
  const termo = q.trim().toLowerCase();
  const visiveis = itens.filter((e) => !termo || `${e.tipo} ${e.operacao} ${e.por} ${e.registros.join(' ')}`.toLowerCase().includes(termo));

  const irPagina = (pagina: number) => router.reload({ only: ['logs'], data: { page: pagina } });

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="WooCommerce" subtitle="log de sincronização"
          subnav={<WoocommerceAbas ativa="/woocommerce/view-sync-log" podeConfigurar={pode.configurar} />} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Deferred data="logs" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando o log…</p>}>
          {itens.length > 0 ? (
            <section data-contract="log" className="rounded-xl border">
              <h3 className="flex items-center justify-between border-b px-4 py-3 text-sm font-semibold">
                Log de sincronização <span className="font-normal text-muted-foreground">{plural(logs?.total ?? 0, 'execução', 'execuções')}</span>
              </h3>
              <Stack gap={2} className="p-4">
                <Inline data-contract="toolbar" wrap gap={2}>
                  <div className="relative min-w-64 flex-1">
                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
                      placeholder="Buscar nesta página (tipo, quem, registro)…" aria-label="Buscar nesta página" />
                  </div>
                </Inline>
                <table className="w-full text-sm" data-contract="tabela">
                  <caption className="sr-only">Execuções de sincronização</caption>
                  <thead>
                    <tr className="text-left text-muted-foreground">
                      <th className="w-8 py-2" aria-label="Detalhes" />
                      <th className="py-2 font-medium">Data</th>
                      <th className="py-2 font-medium">Tipo</th>
                      <th className="py-2 font-medium">Operação</th>
                      <th className="py-2 font-medium">Sincronizado por</th>
                      <th className="py-2 font-medium">Registros</th>
                      <th className="py-2 font-medium">Situação</th>
                    </tr>
                  </thead>
                  <tbody>
                    {visiveis.map((e) => {
                      const temDetalhe = e.erros.length > 0 || e.registros.length > 0;
                      const expandida = aberta === e.id;
                      return (
                        <Fragment key={e.id}>
                          <tr className="border-t align-top">
                            <td className="py-2">
                              {temDetalhe && (
                                <button type="button" className="rounded p-1 hover:bg-muted" aria-expanded={expandida}
                                  aria-label={expandida ? 'Fechar detalhes' : 'Ver detalhes'} onClick={() => setAberta(expandida ? null : e.id)}>
                                  {expandida ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
                                </button>
                              )}
                            </td>
                            <td className="py-2 pr-3">{e.data}<div className="text-xs text-muted-foreground">{e.quando}</div></td>
                            <td className="py-2 pr-3">{e.tipo}</td>
                            <td className="py-2 pr-3">{e.operacao}</td>
                            <td className="py-2 pr-3">{e.por || 'Sincronização automática'}</td>
                            <td className="py-2 pr-3">{plural(e.registros.length, 'registro', 'registros')}</td>
                            <td className="py-2">
                              {e.erros.length > 0 ? <Badge variant="warning">Com erro</Badge> : <Badge variant="success">Sincronizado</Badge>}
                            </td>
                          </tr>
                          {expandida && (
                            <tr data-contract="detalhe">
                              <td />
                              <td colSpan={6} className="pb-3 pr-3">
                                {e.erros.length > 0 && (
                                  <ul className="mb-2 list-disc pl-5 text-destructive">{e.erros.map((m, i) => <li key={i}>{m}</li>)}</ul>
                                )}
                                {e.registros.length > 0 && <p className="text-muted-foreground">{e.registros.join(', ')}</p>}
                              </td>
                            </tr>
                          )}
                        </Fragment>
                      );
                    })}
                    {visiveis.length === 0 && (
                      <tr><td colSpan={7} className="py-6 text-center text-muted-foreground">Nenhuma execução com esse termo nesta página.</td></tr>
                    )}
                  </tbody>
                </table>
                {logs && logs.ultima_pagina > 1 && (
                  <Inline data-contract="paginacao" gap={2} className="justify-end text-sm">
                    <span className="text-muted-foreground">Página {logs.pagina} de {logs.ultima_pagina}</span>
                    <Button variant="outline" size="sm" disabled={logs.pagina <= 1} onClick={() => irPagina(logs.pagina - 1)}>Anterior</Button>
                    <Button variant="outline" size="sm" disabled={logs.pagina >= logs.ultima_pagina} onClick={() => irPagina(logs.pagina + 1)}>Próxima</Button>
                  </Inline>
                )}
              </Stack>
            </section>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhuma sincronização ainda" description="Cada vez que categorias, produtos ou pedidos sincronizam com a loja, a execução aparece aqui." />
            </div>
          )}
        </Deferred>
      </Stack>
    </div>
  );
}

LogIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default LogIndex;
