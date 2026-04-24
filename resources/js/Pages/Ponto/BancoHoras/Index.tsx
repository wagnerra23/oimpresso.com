// @docvault
//   tela: /ponto/banco-horas
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-002
//   rules: R-PONT-001
//   adrs: arq/0001
//   tests: Modules/PontoWr2/Tests/Feature/BancoHorasIndexTest

import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { cn, formatMinutes } from '@/Lib/utils';

import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';

interface Saldo {
  colaborador_id: number;
  matricula: string | null;
  nome: string;
  saldo_minutos: number;
  atualizado_em: string | null;
}

interface Paginated {
  data: Saldo[];
  total: number;
  current_page: number;
  last_page: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Totais {
  credito_total: number;
  debito_total: number;
  colaboradores_credito: number;
  colaboradores_debito: number;
}

interface Props {
  saldos: Paginated;
  totais: Totais;
}

export default function BancoHorasIndex({ saldos, totais }: Props) {
  return (
    <>
      <Head title="Banco de Horas" />
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        <PageHeader
          icon="piggy-bank"
          title="Banco de Horas"
          description="Saldo consolidado por colaborador. Ledger append-only — ajustes são movimentos, nunca updates."
        />

        <KpiGrid cols={4}>
          <KpiCard
            label="Crédito total"
            value={formatMinutes(totais.credito_total)}
            icon="trending-up"
            tone="success"
          />
          <KpiCard
            label="Débito total"
            value={formatMinutes(totais.debito_total)}
            icon="trending-down"
            tone="danger"
          />
          <KpiCard
            label="Com crédito"
            value={totais.colaboradores_credito}
            icon="users"
            tone="success"
            description="Colaboradores com saldo positivo"
          />
          <KpiCard
            label="Com débito"
            value={totais.colaboradores_debito}
            icon="users"
            tone="danger"
            description="Colaboradores com saldo negativo"
          />
        </KpiGrid>

        <Card>
          <CardContent className="p-0">
            {saldos.data.length === 0 ? (
              <EmptyState
                icon="piggy-bank"
                title="Nenhum saldo registrado"
                description="O banco de horas será populado automaticamente quando os colaboradores começarem a acumular saldo via apuração diária."
              />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                    <tr>
                      <th className="text-left p-3 font-medium">Matrícula</th>
                      <th className="text-left p-3 font-medium">Colaborador</th>
                      <th className="text-right p-3 font-medium">Saldo atual</th>
                      <th className="text-left p-3 font-medium">Atualizado</th>
                      <th className="text-right p-3 font-medium">Ações</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {saldos.data.map((s) => (
                      <tr key={s.colaborador_id} className="hover:bg-accent/30 transition-colors">
                        <td className="p-3 font-mono text-xs">{s.matricula ?? '—'}</td>
                        <td className="p-3 font-medium">{s.nome}</td>
                        <td className={cn(
                          'p-3 text-right font-mono font-semibold tabular-nums',
                          s.saldo_minutos > 0 && 'text-emerald-600 dark:text-emerald-400',
                          s.saldo_minutos < 0 && 'text-destructive',
                        )}>
                          {formatMinutes(s.saldo_minutos)}
                        </td>
                        <td className="p-3 text-xs text-muted-foreground">{s.atualizado_em ?? '—'}</td>
                        <td className="p-3 text-right">
                          <Button size="sm" variant="outline" asChild>
                            <Link href={`/ponto/banco-horas/${s.colaborador_id}`} className="text-xs gap-1">
                              Movimentos <ArrowRight size={12} />
                            </Link>
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            {saldos.last_page > 1 && (
              <div className="flex items-center justify-between border-t border-border p-3 text-xs">
                <span className="text-muted-foreground">
                  Página {saldos.current_page} de {saldos.last_page} · {saldos.total} saldo(s)
                </span>
                <div className="flex gap-1">
                  {saldos.links.map((link, i) => (
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

BancoHorasIndex.layout = (page: ReactNode) => (
  <AppShell breadcrumb={[{ label: 'Ponto WR2' }, { label: 'Banco de Horas' }]}>
    {page}
  </AppShell>
);
