// @docvault
//   tela: /ponto/importacoes
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-009, US-PONT-010, US-PONT-011
//   rules: R-PONT-001
//   adrs: 0004
//   tests: Modules/PontoWr2/Tests/Feature/ImportacoesIndexTest

import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ArrowRight, Plus } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { formatBytes } from '@/Lib/utils';

import PageHeader from '@/Components/shared/PageHeader';
import StatusBadge from '@/Components/shared/StatusBadge';
import EmptyState from '@/Components/shared/EmptyState';

interface Importacao {
  id: number;
  tipo: string;
  nome_arquivo: string;
  tamanho_bytes: number;
  estado: string;
  linhas_processadas: number;
  linhas_criadas: number;
  created_at: string | null;
  created_at_human: string | null;
  usuario: string | null;
}

interface Props {
  importacoes: {
    data: Importacao[];
    total: number;
    current_page: number;
    last_page: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
}

/**
 * Normaliza estado vindo do backend (ESTADO_PENDENTE etc.) para os values
 * mapeados no StatusBadge kind='importacao' (pendente/processando/sucesso/erro).
 */
function normalizeEstado(s: string): string {
  const raw = (s ?? '').replace('ESTADO_', '').toLowerCase();
  if (raw === 'concluido') return 'sucesso';
  if (raw === 'falhou') return 'erro';
  return raw;
}

export default function ImportacoesIndex({ importacoes }: Props) {
  return (
    <>
      <Head title="Importações AFD" />
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        <PageHeader
          icon="file-up"
          title="Importações AFD"
          description="Arquivos AFD/AFDT lidos por REPs conforme Portaria MTP 671/2021. Dedup por SHA-256."
          action={
            <Button asChild>
              <Link href="/ponto/importacoes/novo">
                <Plus size={14} className="mr-1.5" /> Nova importação
              </Link>
            </Button>
          }
        />

        <Card>
          <CardContent className="p-0">
            {importacoes.data.length === 0 ? (
              <EmptyState
                icon="file-up"
                title="Nenhuma importação"
                description="Faça upload do primeiro arquivo AFD/AFDT exportado do REP (relógio de ponto)."
                action={
                  <Button asChild size="sm">
                    <Link href="/ponto/importacoes/novo">
                      <Plus size={14} className="mr-1.5" /> Fazer primeira importação
                    </Link>
                  </Button>
                }
              />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                    <tr>
                      <th className="text-left p-3 font-medium">Arquivo</th>
                      <th className="text-left p-3 font-medium">Tipo</th>
                      <th className="text-right p-3 font-medium">Tamanho</th>
                      <th className="text-left p-3 font-medium">Estado</th>
                      <th className="text-right p-3 font-medium">Linhas</th>
                      <th className="text-left p-3 font-medium">Por</th>
                      <th className="text-left p-3 font-medium">Quando</th>
                      <th className="text-right p-3 font-medium"></th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {importacoes.data.map((i) => (
                      <tr key={i.id} className="hover:bg-accent/30">
                        <td className="p-3 font-mono text-xs">{i.nome_arquivo}</td>
                        <td className="p-3 text-xs">
                          <Badge variant="outline" className="text-[10px]">{i.tipo}</Badge>
                        </td>
                        <td className="p-3 text-right font-mono text-xs tabular-nums">{formatBytes(i.tamanho_bytes)}</td>
                        <td className="p-3">
                          <StatusBadge kind="importacao" value={normalizeEstado(i.estado)} />
                        </td>
                        <td className="p-3 text-right font-mono text-xs tabular-nums">
                          {i.linhas_criadas}/{i.linhas_processadas}
                        </td>
                        <td className="p-3 text-xs text-muted-foreground">{i.usuario ?? '—'}</td>
                        <td className="p-3 text-xs text-muted-foreground" title={i.created_at ?? ''}>
                          {i.created_at_human ?? '—'}
                        </td>
                        <td className="p-3 text-right">
                          <Button size="sm" variant="outline" asChild>
                            <Link href={`/ponto/importacoes/${i.id}`} className="text-xs gap-1">
                              Ver <ArrowRight size={12} />
                            </Link>
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            {importacoes.last_page > 1 && (
              <div className="flex items-center justify-between border-t border-border p-3 text-xs">
                <span className="text-muted-foreground">
                  Página {importacoes.current_page}/{importacoes.last_page} · {importacoes.total}
                </span>
                <div className="flex gap-1">
                  {importacoes.links.map((link, i) => (
                    <Button
                      key={i}
                      variant={link.active ? 'default' : 'outline'}
                      size="sm"
                      className="h-7 min-w-8 px-2 text-xs"
                      disabled={!link.url}
                      onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                    >
                      <span dangerouslySetInnerHTML={{ __html: link.label }} />
                    </Button>
                  ))}
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </>
  );
}

ImportacoesIndex.layout = (page: ReactNode) => (
  <AppShell breadcrumb={[{ label: 'Ponto WR2' }, { label: 'Importações' }]}>
    {page}
  </AppShell>
);
