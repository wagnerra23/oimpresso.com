// @docvault
//   tela: /ponto/banco-horas
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-002
//   rules: R-PONT-001
//   adrs: arq/0001
//   tests: Modules/PontoWr2/Tests/Feature/BancoHorasIndexTest

import AppShell from '@/Layouts/AppShell';
import { useModuleNav } from '@/Hooks/usePageProps';
import { Link, router } from '@inertiajs/react';
import { ArrowRight, PiggyBank, TrendingDown, TrendingUp, Users } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { cn, formatMinutes } from '@/Lib/utils';

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
  const moduleNav = useModuleNav('Ponto');

  return (
    <AppShell
      title="Banco de Horas"
      breadcrumb={[{ label: 'Ponto WR2' }, { label: 'Banco de Horas' }]}
      moduleNav={moduleNav}
    >
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        <header>
          <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
            <PiggyBank size={22} /> Banco de Horas
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Saldo consolidado por colaborador. Ledger append-only — ajustes são movimentos, nunca updates.
          </p>
        </header>

        {/* KPIs */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <Stat label="Crédito total" value={formatMinutes(totais.credito_total)} tone="emerald" icon={TrendingUp} />
          <Stat label="Débito total" value={formatMinutes(totais.debito_total)} tone="red" icon={TrendingDown} />
          <Stat label="Com crédito" value={String(totais.colaboradores_credito)} tone="emerald" icon={Users} />
          <Stat label="Com débito" value={String(totais.colaboradores_debito)} tone="red" icon={Users} />
        </div>

        <Card>
          <CardContent className="p-0">
            {saldos.data.length === 0 ? (
              <div className="p-12 text-center text-sm text-muted-foreground">
                Nenhum saldo de banco de horas registrado.
              </div>
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
                          'p-3 text-right font-mono font-semibold',
                          s.saldo_minutos > 0 && 'text-emerald-600 dark:text-emerald-400',
                          s.saldo_minutos < 0 && 'text-red-600 dark:text-red-400',
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
              <Pagination p={saldos} />
            )}
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}

function Stat({
  label,
  value,
  tone,
  icon: Icon,
}: {
  label: string;
  value: string;
  tone: 'emerald' | 'red' | 'blue' | 'muted';
  icon: React.ComponentType<{ size?: number; className?: string }>;
}) {
  const toneClass: Record<typeof tone, string> = {
    emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    red:     'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    blue:    'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    muted:   'bg-muted text-muted-foreground',
  };
  return (
    <Card>
      <CardContent className="pt-4 pb-4 flex items-start justify-between gap-2">
        <div>
          <p className="text-[10px] uppercase tracking-wide text-muted-foreground">{label}</p>
          <p className="text-xl font-bold font-mono mt-0.5">{value}</p>
        </div>
        <div className={cn('size-9 rounded-lg flex items-center justify-center', toneClass[tone])}>
          <Icon size={16} />
        </div>
      </CardContent>
    </Card>
  );
}

function Pagination({ p }: { p: Paginated }) {
  return (
    <div className="flex items-center justify-between border-t border-border p-3 text-xs">
      <span className="text-muted-foreground">Página {p.current_page} de {p.last_page} · {p.total} saldo(s)</span>
      <div className="flex gap-1">
        {p.links.map((link, i) => (
          <Button key={i} variant={link.active ? 'default' : 'outline'} size="sm"
                  className="h-7 min-w-8 px-2 text-xs" disabled={!link.url}
                  onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}>
            <span dangerouslySetInnerHTML={{ __html: link.label }} />
          </Button>
        ))}
      </div>
    </div>
  );
}
