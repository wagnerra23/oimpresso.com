// @docvault
//   tela: /ponto/espelho/show
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-007, US-PONT-008
//   rules: R-PONT-001, R-PONT-002
//   adrs: 0004
//   tests: Modules/PontoWr2/Tests/Feature/EspelhoShowTest

import AppShellV2 from '@/Layouts/AppShellV2';
import PontoSubNav from '@/Pages/Ponto/_shared/PontoSubNav';
import { Deferred, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Skeleton } from '@/Components/ui/skeleton';
import { Grid } from '@/Components/layout';
import {
  AlertTriangle,
  ArrowLeft,
  ArrowRight,
  ChevronLeft,
  ChevronRight,
  ClipboardList,
  Printer,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { cn, formatMinutes } from '@/Lib/utils';
import MonthHeatmap from '../_components/MonthHeatmap';

interface Colaborador {
  id: number;
  matricula: string | null;
  cpf: string | null;
  nome: string;
  email: string | null;
  admissao: string | null;
  escala: string | null;
  /** Cabeçalho legal do espelho — Portaria MTP 671/2021 Art. 85. PII: tela do RH, nunca log. */
  pis: string | null;
  desligamento: string | null;
  carga_diaria_minutos: number;
}

interface Totais {
  trabalhado: number;
  atraso: number;
  falta: number;
  he_diurna: number;
  he_noturna: number;
  adicional_not: number;
  bh_credito: number;
  bh_debito: number;
  divergencias: number;
}

interface Marcacao {
  hora: string;
  tipo: string;
  origem: string;
}

interface Linha {
  data: string;
  dow: string;
  dia: number;
  is_weekend: boolean;
  trabalhado: number;
  atraso: number;
  falta: number;
  he: number;
  divergencia: boolean;
  /** Colunas da apuração diária — as do Blade legado, campo a campo (paridade). */
  previsto: number;
  bh_credito: number;
  bh_debito: number;
  estado: string;
  marcacoes: Marcacao[];
}

interface Props {
  colaborador: Colaborador;
  mes: string;
  // totais e linhas vêm via Inertia::defer (EspelhoController) — undefined no
  // first render até o auto-fetch async resolver (RUNBOOK-inertia-defer-pattern.md).
  totais?: Totais;
  linhas?: Linha[];
}

const TOTAIS_FALLBACK: Totais = {
  trabalhado: 0, atraso: 0, falta: 0, he_diurna: 0, he_noturna: 0,
  adicional_not: 0, bh_credito: 0, bh_debito: 0, divergencias: 0,
};

const tipoBadgeVariant: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
  ENTRADA:       'default',
  ALMOCO_INICIO: 'secondary',
  ALMOCO_FIM:    'secondary',
  SAIDA:         'outline',
};

export default function EspelhoShow({ colaborador, mes, totais, linhas }: Props) {
  // Guardas defensivas (defesa dupla com o <Deferred>): props deferidas são
  // undefined no first render.
  const t = totais ?? TOTAIS_FALLBACK;
  const rows = linhas ?? [];
  // Tabela é o default porque ela É o documento; a grade é leitura de relance.
  const [modo, setModo] = useState<'tabela' | 'grade'>('tabela');

  const onMesChange = (novo: string) => {
    // D-14: partial reload — só re-busca o que muda com o mês (totais/linhas defer).
    // Cabeçalho `colaborador` é por registro, não muda com o mês → fora do only:.
    router.get(`/ponto/espelho/${colaborador.id}`, { mes: novo }, {
      preserveState: true,
      only: ['mes', 'totais', 'linhas'],
    });
  };

  const navegarMes = (delta: number) => {
    const [y, m] = mes.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    const novo = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    onMesChange(novo);
  };

  return (
    <>
      <Head title={`Espelho · ${colaborador.nome} · ${mes}`} />
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        {/* ADR 0182 PageHeader canon — Wave Ponto 2026-05-22 */}
        <header className="os-page-h">
          <div className="os-page-h-l">
            <h1>Espelho <span className="text-stone-400 font-normal">· {colaborador.nome}</span></h1>
            <p>
              {colaborador.matricula && <span>mat. {colaborador.matricula}</span>}
              {colaborador.escala && <span className="ml-2">· Escala: {colaborador.escala}</span>}
            </p>
          </div>
          <div className="os-page-h-r">
            <PontoSubNav active="espelho" hidePrimary />
            <Button variant="outline" size="sm" asChild>
              <Link href="/ponto/espelho"><ArrowLeft size={14} className="mr-1.5" /> Voltar</Link>
            </Button>
            <Button size="sm" asChild>
              <a href={`/ponto/espelho/${colaborador.id}/imprimir?mes=${mes}`} target="_blank" rel="noreferrer">
                <Printer size={14} className="mr-1.5" /> Imprimir PDF
              </a>
            </Button>
          </div>
        </header>

        {/* Navegação de mês */}
        <Card>
          <CardContent className="pt-4 flex items-center justify-between gap-3">
            <Button variant="outline" size="sm" onClick={() => navegarMes(-1)}>
              <ChevronLeft size={14} className="mr-1" /> Mês anterior
            </Button>
            <Input
              type="month"
              value={mes}
              onChange={(e) => onMesChange(e.target.value)}
              className="w-40 text-center"
            />
            <Button variant="outline" size="sm" onClick={() => navegarMes(1)}>
              Próximo mês <ChevronRight size={14} className="ml-1" />
            </Button>
          </CardContent>
        </Card>

        {/* Cabeçalho legal — 1ª seção do contrato `ponto-espelho`. NÃO é deferido:
            vem por registro (não muda com o mês) e é o que faz a folha valer em
            fiscalização. Sem matrícula/CPF/PIS o documento não serve. */}
        <Card data-contract="espelho-dados-colaborador">
          <CardHeader className="pb-3">
            <CardTitle className="text-base">Dados do colaborador</CardTitle>
          </CardHeader>
          <CardContent className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm md:grid-cols-4">
            <Dado rotulo="Matrícula:" valor={colaborador.matricula} />
            <Dado rotulo="CPF:" valor={colaborador.cpf} />
            <Dado rotulo="PIS:" valor={colaborador.pis} />
            <Dado rotulo="Escala atual:" valor={colaborador.escala} />
            <Dado
              rotulo="Carga diária:"
              valor={colaborador.carga_diaria_minutos > 0 ? formatMinutes(colaborador.carga_diaria_minutos) : null}
            />
            <Dado rotulo="Admissão:" valor={fmtData(colaborador.admissao)} />
            <Dado rotulo="Desligamento:" valor={fmtData(colaborador.desligamento)} />
          </CardContent>
        </Card>

        <Deferred
          data={['totais', 'linhas']}
          fallback={(
            <div className="space-y-4">
              <Grid cols={6} gap={3}>
                {Array.from({ length: 6 }).map((_, i) => (
                  <Skeleton key={i} className="h-20 w-full" />
                ))}
              </Grid>
              <Skeleton className="h-40 w-full" />
              <Skeleton className="h-64 w-full" />
            </div>
          )}
        >
        {/* Totalizadores */}
        {/* Os SEIS totalizadores do contrato — os mesmos do Blade legado, campo a
            campo. "Hora extra" soma diurna+noturna (o Blade totaliza junto); o
            banco de horas vira DUAS colunas, crédito e débito, porque somá-las
            esconderia compensação. */}
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3" data-contract="espelho-totais">
          <Totalizador label="Trabalhado" value={formatMinutes(t.trabalhado)} tone="blue" />
          <Totalizador label="Atraso" value={formatMinutes(t.atraso)} tone={t.atraso > 0 ? 'amber' : 'muted'} />
          <Totalizador label="Falta" value={formatMinutes(t.falta)} tone={t.falta > 0 ? 'red' : 'muted'} />
          <Totalizador label="Hora extra" value={formatMinutes(t.he_diurna + t.he_noturna)} tone="violet" />
          <Totalizador label="Banco hrs (+)" value={formatMinutes(t.bh_credito)} tone="emerald" />
          <Totalizador label="Banco hrs (−)" value={formatMinutes(t.bh_debito)} tone="emerald" />
        </div>

        {/* Alerta divergências */}
        {t.divergencias > 0 && (
          <div className="flex items-center gap-2 rounded-lg border border-warning/40 bg-warning/5 p-3 text-sm">
            <AlertTriangle size={16} className="text-warning-fg" />
            <span>
              <strong>{t.divergencias}</strong> dia(s) com divergência detectada na apuração.
              Dia divergente não consolida: veja o estado de cada um na apuração diária.
            </span>
          </div>
        )}

        {/* Modo de visão — a TABELA é o documento; a GRADE é a leitura de relance.
            As duas olham a mesma apuração, então o seletor não recarrega nada:
            só troca o que aparece. */}
        <div className="flex items-center gap-1 print:hidden" data-contract="espelho-modo-visao">
          <Button
            variant={modo === 'tabela' ? 'default' : 'outline'}
            size="sm"
            onClick={() => setModo('tabela')}
            aria-pressed={modo === 'tabela'}
          >
            Tabela
          </Button>
          <Button
            variant={modo === 'grade' ? 'default' : 'outline'}
            size="sm"
            onClick={() => setModo('grade')}
            aria-pressed={modo === 'grade'}
          >
            Grade do mês
          </Button>
        </div>

        {modo === 'grade' && (
        <MonthHeatmap
          mes={mes}
          linhas={rows}
          onDayClick={(linha) => {
            const el = document.getElementById(`dia-${linha.data}`);
            if (el) {
              el.scrollIntoView({ behavior: 'smooth', block: 'center' });
              el.classList.add('ring-2', 'ring-primary', 'ring-offset-2');
              setTimeout(() => el.classList.remove('ring-2', 'ring-primary', 'ring-offset-2'), 1500);
            }
          }}
        />
        )}

        {/* Apuração diária — as 7 colunas do contrato são as do Blade legado,
            campo a campo. `Previsto` vem de prevista_carga_minutos e `Estado` do
            estado da apuração: é o que diz se o dia consolida ou trava o mês. */}
        {modo === 'tabela' && (
        <Card data-contract="espelho-apuracao-diaria">
          <CardHeader className="pb-3">
            <CardTitle className="text-base">Apuração diária</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <div className="overflow-x-auto">
              <table className="w-full text-xs">
                <thead className="border-b border-border bg-muted/30 text-muted-foreground">
                  <tr>
                    <th className="text-left p-2 font-medium">Dia</th>
                    <th className="text-right p-2 font-medium">Previsto</th>
                    <th className="text-right p-2 font-medium">Realizado</th>
                    <th className="text-left p-2 font-medium">Marcações</th>
                    <th className="text-right p-2 font-medium">Atraso</th>
                    <th className="text-right p-2 font-medium">HE</th>
                    <th className="text-right p-2 font-medium">BH (+/−)</th>
                    <th className="text-left p-2 font-medium">Estado</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {rows.map((l) => (
                    <tr
                      key={l.data}
                      id={`dia-${l.data}`}
                      className={cn(
                        'hover:bg-accent/30 transition-all scroll-mt-20',
                        l.divergencia && 'bg-warning/5',
                        l.is_weekend && 'text-muted-foreground',
                      )}
                    >
                      <td className="p-2 whitespace-nowrap">
                        <span className="font-semibold">{String(l.dia).padStart(2, '0')}</span>
                        <span className="ml-2 text-[10px] uppercase">{l.dow}</span>
                      </td>
                      <td className="p-2 text-right font-mono text-muted-foreground">
                        {formatMinutes(l.previsto)}
                      </td>
                      <td className="p-2 text-right font-mono">{formatMinutes(l.trabalhado)}</td>
                      <td className="p-2">
                        {l.marcacoes.length === 0 ? (
                          <span className="text-muted-foreground italic text-[10px]">—</span>
                        ) : (
                          <div className="flex flex-wrap gap-1">
                            {l.marcacoes.map((m, i) => (
                              <Badge
                                key={i}
                                variant={tipoBadgeVariant[m.tipo] ?? 'outline'}
                                className="text-[10px] px-1.5 py-0"
                                title={`${m.tipo} · ${m.origem}`}
                              >
                                {m.hora}
                              </Badge>
                            ))}
                          </div>
                        )}
                      </td>
                      <td className={cn('p-2 text-right font-mono', l.atraso > 0 && 'text-warning-fg')}>
                        {formatMinutes(l.atraso)}
                      </td>
                      <td className={cn('p-2 text-right font-mono', l.he > 0 && 'text-violet-600')}>
                        {formatMinutes(l.he)}
                      </td>
                      <td className="p-2 text-right font-mono whitespace-nowrap">
                        {l.bh_credito > 0 && <span className="text-emerald-600">+{formatMinutes(l.bh_credito)}</span>}
                        {l.bh_credito > 0 && l.bh_debito > 0 && <span className="text-muted-foreground"> / </span>}
                        {l.bh_debito > 0 && <span className="text-destructive-fg">{'−'}{formatMinutes(l.bh_debito)}</span>}
                        {l.bh_credito === 0 && l.bh_debito === 0 && <span className="text-muted-foreground">—</span>}
                      </td>
                      <td className="p-2 whitespace-nowrap">
                        <EstadoDia estado={l.estado} isWeekend={l.is_weekend} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
        )}
        </Deferred>

        {/* Folha de prova — SÓ no print (`hidden print:block`). É o que o
            colaborador assina e o que a fiscalização recebe; por isso repete o
            cabeçalho e cita a norma. Portaria MTP 671/2021 Art. 85. */}
        <section className="hidden print:block" data-contract="espelho-folha-impressao">
          <h2 className="text-center text-base font-semibold">Espelho de Ponto Eletrônico</h2>
          <p className="mt-1 text-center text-xs">
            {colaborador.nome}
            {colaborador.matricula ? ` · mat. ${colaborador.matricula}` : ''} · {mes}
          </p>
          <p className="mt-4 text-xs font-semibold">Apuração diária</p>
          <p className="mt-4 text-xs font-semibold">Totais do mês</p>
          <div className="mt-10 flex justify-between gap-8 text-xs">
            <div className="flex-1 border-t border-black pt-1 text-center">Colaborador</div>
            <div className="flex-1 border-t border-black pt-1 text-center">Responsável RH</div>
          </div>
          <p className="mt-6 text-center text-[10px] text-muted-foreground">
            Portaria MTP 671/2021 Art. 85
          </p>
        </section>
      </div>
    </>
  );
}

EspelhoShow.layout = (page: ReactNode) => (
  <AppShellV2 breadcrumbItems={[
    { label: 'Ponto WR2' },
    { label: 'Espelho', href: '/ponto/espelho' },
  ]}>
    {page}
  </AppShellV2>
);

function Totalizador({
  label,
  value,
  tone,
  small,
}: {
  label: string;
  value: string;
  tone: 'blue' | 'emerald' | 'amber' | 'red' | 'violet' | 'muted';
  small?: boolean;
}) {
  const toneClass: Record<typeof tone, string> = {
    blue:    'text-blue-700 dark:text-blue-400',
    emerald: 'text-emerald-700 dark:text-emerald-400',
    amber:   'text-amber-700 dark:text-amber-400',
    red:     'text-red-700 dark:text-red-400',
    violet:  'text-violet-700 dark:text-violet-400',
    muted:   'text-muted-foreground',
  };
  return (
    <Card>
      <CardContent className="pt-4 pb-4">
        <p className="text-[10px] uppercase tracking-wide text-muted-foreground">{label}</p>
        <p className={cn('font-bold font-mono mt-0.5', small ? 'text-sm' : 'text-lg', toneClass[tone])}>
          {value}
        </p>
      </CardContent>
    </Card>
  );
}

/** Par rótulo/valor do cabeçalho legal. `—` quando o campo não está preenchido:
 *  campo vazio precisa APARECER vazio no documento, não sumir da folha. */
function Dado({ rotulo, valor }: { rotulo: string; valor: string | null }) {
  return (
    <div>
      <span className="text-muted-foreground">{rotulo}</span>{' '}
      <span className="font-medium">{valor || '—'}</span>
    </div>
  );
}

/** Data ISO -> pt-BR sem timezone (a string já vem 'Y-m-d' do controller;
 *  `new Date('Y-m-d')` seria UTC e voltaria um dia em BRT). */
function fmtData(iso: string | null): string | null {
  if (!iso) return null;
  const [y, m, d] = iso.split('-');
  return y && m && d ? `${d}/${m}/${y}` : iso;
}

/** Estado da apuração do dia. Sem apuração o controller manda '' — aqui vira
 *  folga no fim de semana e `—` nos demais, em vez de inventar um estado. */
function EstadoDia({ estado, isWeekend }: { estado: string; isWeekend: boolean }) {
  if (!estado) {
    return <span className="text-[10px] uppercase text-muted-foreground">{isWeekend ? 'folga' : '—'}</span>;
  }
  const tom =
    estado === 'DIVERGENCIA' ? 'text-warning-fg'
    : estado === 'FECHADO' || estado === 'CONSOLIDADO' ? 'text-success'
    : 'text-muted-foreground';
  return <span className={cn('text-[10px] uppercase font-medium', tom)}>{estado.toLowerCase()}</span>;
}
