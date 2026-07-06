// @docvault
//   tela: /ponto/banco-horas
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-002
//   rules: R-PONT-001
//   adrs: arq/0001
//   tests: Modules/PontoWr2/Tests/Feature/BancoHorasIndexTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';
import { cn, formatMinutes } from '@/Lib/utils';

import PontoSubNav from '@/Pages/Ponto/_shared/PontoSubNav';
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
  // saldos e totais vêm via Inertia::defer — undefined no first render
  saldos?: Paginated;
  totais?: Totais;
}

const TOTAIS_FALLBACK: Totais = {
  credito_total: 0, debito_total: 0, colaboradores_credito: 0, colaboradores_debito: 0,
};

export default function BancoHorasIndex({ saldos, totais }: Props) {
  // Guardas defensivas (defesa dupla com o <Deferred>): props deferidas são
  // undefined no first render.
  const t = totais ?? TOTAIS_FALLBACK;
  const rows = saldos?.data ?? [];
  return (
    <>
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        {/* ADR 0182 PageHeader canon — Wave Ponto 2026-05-22 */}
        <header className="os-page-h">
          <div className="os-page-h-l">
            <h1>Banco de Horas <span className="text-stone-400 font-normal">· Ledger append-only</span></h1>
            <p>Saldo consolidado por colaborador — ajustes são movimentos, nunca updates.</p>
          </div>
          <div className="os-page-h-r">
            <PontoSubNav active="banco-horas" />
          </div>
        </header>

        <Deferred
          data={['saldos', 'totais']}
          fallback={(
            <div className="space-y-4">
              <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                {Array.from({ length: 4 }).map((_, i) => (
                  <Skeleton key={i} className="h-24 w-full" />
                ))}
              </div>
              <Skeleton className="h-64 w-full" />
            </div>
          )}
        >
        <KpiGrid cols={4}>
          <KpiCard
            label="Crédito total"
            value={formatMinutes(t.credito_total)}
            icon="trending-up"
            tone="success"
          />
          <KpiCard
            label="Débito total"
            value={formatMinutes(t.debito_total)}
            icon="trending-down"
            tone="danger"
          />
          <KpiCard
            label="Com crédito"
            value={t.colaboradores_credito}
            icon="users"
            tone="success"
            description="Colaboradores com saldo positivo"
          />
          <KpiCard
            label="Com débito"
            value={t.colaboradores_debito}
            icon="users"
            tone="danger"
            description="Colaboradores com saldo negativo"
          />
        </KpiGrid>

        <Card>
          <CardContent className="p-0">
            {rows.length === 0 ? (
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
                    {rows.map((s) => (
                      <tr key={s.colaborador_id} className="hover:bg-accent/30 transition-colors">
                        <td className="p-3 font-mono text-xs">{s.matricula ?? '—'}</td>
                        <td className="p-3 font-medium">{s.nome}</td>
                        <td className={cn(
                          'p-3 text-right font-mono font-semibold tabular-nums',
                          s.saldo_minutos > 0 && 'text-success-fg',
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
            {(saldos?.last_page ?? 1) > 1 && (
              <div className="flex items-center justify-between border-t border-border p-3 text-xs">
                <span className="text-muted-foreground">
                  Página {saldos?.current_page ?? 1} de {saldos?.last_page ?? 1} · {saldos?.total ?? 0} saldo(s)
                </span>
                <div className="flex gap-1">
                  {(saldos?.links ?? []).map((link, i) => (
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
        </Deferred>
      </div>
    </>
  );
}

BancoHorasIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Banco de Horas" breadcrumbItems={[{ label: 'Ponto WR2' }, { label: 'Banco de Horas' }]}>
    {page}
  </AppShellV2>
);
