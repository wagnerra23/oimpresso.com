// Trabalho — a lista ÚNICA do hub Forja.
//
// @memcofre
//   tela: /forja/trabalho
//   module: Forja
//   adrs: 0070 (Jira-style) · 0093 (Tier 0) · 0253 (primitivos) · UI-0013
//   permissao: jana.mcp.usage.all
//   paridade: TrabalhoService (fusão de ForjaBacklogService + BacklogController + Tasks)
//
// FUSÃO DOS TRÊS BACKLOGS (US-FORJA-006 · medido 2026-08-08)
// A mesma pergunta — "o que tem pra fazer?" — era respondida por três telas com
// escopos e riquezas diferentes. Esta é a fusão, com a NATIVA como base (é a
// rica: filtros, KPIs, contrato defendido por gate).
//
// ⚠️ NADA FOI DELETADO NESTA ONDA. As três seguem no ar; a comparação é olhando,
// e a remoção da perdedora é decisão [W] com smoke antes.
//
// SEM CHIP DE FRENTE, por decisão [W]: a lista abre com TODAS as tasks. O recorte
// por projeto se faz buscando ou lendo a coluna Frente — não escondendo o resto.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { PageHeader } from '@/Components/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
// Reuso do DS em vez de recriar (rule reuse-check): os mapas de cor de
// prioridade/status são canônicos e já usados por Board/Backlog/Triage.
import { PRIORITY_BADGE, STATUS_BADGE, COLUMN_LABEL_PT, type Priority, type Status } from '@/Components/board/badges';
import ForjaHub from '@/Pages/team-mcp/Forja/_components/ForjaHub';
import { Grid, Inline, Stack } from '@/Components/layout';
import { cn } from '@/Lib/utils';
import { Lock, Clock, List as ListIcon, LayoutGrid } from 'lucide-react';
import TrabalhoQuadro, { type EixoQuadro } from './_components/TrabalhoQuadro';

interface Task {
  task_id: string;
  identifier: string | null;
  display_id: string;
  title: string;
  module: string | null;
  owner: string | null;
  priority: Priority;
  status: Status;
  type: string | null;
  estimate_h: number | null;
  story_points: number | null;
  due_date: string | null;
  blocked_by: string[];
  is_blocked: boolean;
  is_overdue: boolean;
  forja_tipo: string | null;
  forja_fase: string | null;
  forja_papel: string | null;
  forja_onda: string | null;
  frente_id: number | null;
}

interface Kpis {
  total: number;
  ativas: number;
  p0: number;
  fazendo: number;
  bloqueadas: number;
  sem_dono: number;
  atrasadas: number;
}

const KPIS_VAZIO: Kpis = {
  total: 0, ativas: 0, p0: 0, fazendo: 0, bloqueadas: 0, sem_dono: 0, atrasadas: 0,
};

interface Props {
  titulo: string;
  subtitle: string;
  /** `visao` e `eixo` moram nos filtros — assim a URL carrega a vista inteira. */
  filtros: Record<string, unknown>;
  sorts: string[];
  statuses: string[];
  // Deferidas → `undefined` no 1º paint. Default no destructuring pra não
  // crashar antes do partial reload chegar (o sintoma sem isso é tela branca —
  // PR #1940).
  tasks?: Task[];
  kpis?: Kpis;
  frentes?: Record<number, string>;
}

const ORDEM_LABEL: Record<string, string> = {
  rank: 'Prioridade',
  execucao: 'Em execução',
  recent: 'Recentes',
  due: 'Vencimento',
  title: 'Título',
  id: 'ID',
};

export default function Trabalho({
  titulo, subtitle, filtros, sorts, tasks = [], kpis = KPIS_VAZIO, frentes = {},
}: Props) {
  const [busca, setBusca] = useState(String(filtros.q ?? ''));
  const ordem = String(filtros.sort ?? 'rank');
  const visao = String(filtros.visao ?? 'lista');
  const eixo = String(filtros.eixo ?? 'execucao') as EixoQuadro;

  /** Recarrega só o que muda — o cabeçalho e os filtros ficam. */
  const aplicar = useCallback((patch: Record<string, string>) => {
    router.get('/forja/trabalho', { ...filtros, ...patch } as Record<string, string>, {
      preserveState: true,
      preserveScroll: true,
      only: ['tasks', 'kpis', 'filtros'],
    });
  }, [filtros]);

  const submeterBusca = useCallback((e: React.FormEvent) => {
    e.preventDefault();
    aplicar({ q: busca });
  }, [aplicar, busca]);

  // Agrupa por Frente — é o recorte por projeto sem esconder o resto (decisão [W]).
  const grupos = useMemo(() => {
    const mapa = new Map<string, Task[]>();
    for (const t of tasks) {
      const nome = t.frente_id ? (frentes[t.frente_id] ?? `#${t.frente_id}`) : '— sem frente —';
      const atual = mapa.get(nome);
      if (atual) atual.push(t); else mapa.set(nome, [t]);
    }
    return [...mapa.entries()].sort((a, b) => b[1].length - a[1].length);
  }, [tasks, frentes]);

  return (
    <AppShellV2 title="Forja — Trabalho" breadcrumbItems={[{ label: 'Forja' }, { label: 'Trabalho' }]}>
      <ForjaHub active="trabalho" />

      <Stack gap={6}>
        <PageHeader title={titulo} subtitle={subtitle} />

        <KpiGrid>
          <KpiCard label="Ativas" value={kpis.ativas} icon="ClipboardList" />
          <KpiCard label="P0 abertas" value={kpis.p0} icon="Flag" />
          <KpiCard label="Fazendo" value={kpis.fazendo} icon="Zap" />
          <KpiCard label="Bloqueadas" value={kpis.bloqueadas} icon="AlertTriangle" />
        </KpiGrid>

        <Inline gap={2} align="center" wrap>
          {/* Sub-visões da MESMA lista — não são telas diferentes: o pool, os
              filtros e a busca são os mesmos; só muda como se olha. */}
          <Inline gap={1} align="center">
            <button
              type="button" onClick={() => aplicar({ visao: 'lista' })}
              aria-pressed={visao === 'lista'} data-testid="trabalho-visao-lista"
              className={cn('inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs transition-colors',
                visao === 'lista' ? 'border-primary bg-primary/10 text-primary'
                                  : 'border-border text-muted-foreground hover:text-foreground')}
            >
              <ListIcon className="h-3 w-3" aria-hidden /> Lista
            </button>
            <button
              type="button" onClick={() => aplicar({ visao: 'quadro' })}
              aria-pressed={visao === 'quadro'} data-testid="trabalho-visao-quadro"
              className={cn('inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs transition-colors',
                visao === 'quadro' ? 'border-primary bg-primary/10 text-primary'
                                   : 'border-border text-muted-foreground hover:text-foreground')}
            >
              <LayoutGrid className="h-3 w-3" aria-hidden /> Quadro
            </button>
          </Inline>

          {visao === 'quadro' && (
            <Inline gap={1} align="center">
              {(['execucao', 'pipeline'] as EixoQuadro[]).map((e) => (
                <button
                  key={e} type="button" onClick={() => aplicar({ eixo: e })}
                  aria-pressed={eixo === e} data-testid={`trabalho-eixo-${e}`}
                  title={e === 'execucao'
                    ? 'O que está andando — vale pra toda task'
                    : 'Em que ponto do protocolo de tela — só trabalho de tela tem fase'}
                  className={cn('rounded-md border px-2 py-1 text-xs transition-colors',
                    eixo === e ? 'border-primary bg-primary/10 text-primary'
                               : 'border-border text-muted-foreground hover:text-foreground')}
                >
                  {e === 'execucao' ? 'Execução' : 'Pipeline F0→F3.5'}
                </button>
              ))}
            </Inline>
          )}

          <form onSubmit={submeterBusca}>
            <Input
              value={busca}
              onChange={(e) => setBusca(e.target.value)}
              placeholder="Buscar por título, id, dono ou módulo…"
              className="h-8 w-72 text-xs"
              data-testid="trabalho-busca"
            />
          </form>

          {/* Ordenação como segmentos, não <select> nativo (ds/no-native-select).
              Só na Lista: no Quadro quem ordena é a coluna. */}
          {visao === 'lista' && (
          <Inline gap={1} align="center">
            {sorts.map((s) => (
              <button
                key={s}
                type="button"
                onClick={() => aplicar({ sort: s })}
                aria-pressed={ordem === s}
                data-testid={`trabalho-ordem-${s}`}
                className={cn(
                  'rounded-md border px-2 py-1 text-xs transition-colors',
                  ordem === s
                    ? 'border-primary bg-primary/10 text-primary'
                    : 'border-border text-muted-foreground hover:text-foreground',
                )}
              >
                {ORDEM_LABEL[s] ?? s}
              </button>
            ))}
          </Inline>
          )}

          <span className="ml-auto text-xs text-muted-foreground tabular-nums" data-testid="trabalho-total">
            {kpis.total} task{kpis.total === 1 ? '' : 's'}
            {kpis.sem_dono > 0 && ` · ${kpis.sem_dono} sem dono`}
          </span>
        </Inline>

        {visao === 'quadro' && tasks.length > 0 && (
          <TrabalhoQuadro tasks={tasks} eixo={eixo} />
        )}

        {tasks.length === 0 && (
          <Card data-testid="trabalho-vazio">
            <CardContent className="py-12 text-center">
              <p className="text-sm font-medium text-foreground">Nada encontrado.</p>
              <p className="mt-1 text-sm text-muted-foreground">
                Ajuste a busca ou a ordenação — a lista abre com todas as tasks do time.
              </p>
            </CardContent>
          </Card>
        )}

        {visao === 'lista' && grupos.map(([frente, itens]) => (
          <Stack gap={2} key={frente}>
            <Inline gap={2} align="baseline">
              <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {frente}
              </h2>
              <span className="text-xs text-muted-foreground tabular-nums">{itens.length}</span>
            </Inline>

            <Card>
              <CardContent className="p-0">
                {itens.map((t) => (
                  <Grid
                    key={t.task_id}
                    gap={2}
                    className="grid-cols-[7rem_1fr_auto] items-center border-b border-border px-4 py-2 last:border-b-0"
                    data-testid="trabalho-linha"
                  >
                    <Inline gap={2} align="center">
                      <span
                        className={cn('rounded px-1.5 py-0.5 text-[10px] font-medium uppercase', PRIORITY_BADGE[t.priority])}
                        title={`Prioridade ${t.priority}`}
                      >
                        {t.priority}
                      </span>
                      <span className="font-mono text-[11px] text-muted-foreground">{t.display_id}</span>
                    </Inline>

                    <Inline gap={2} align="center" className="min-w-0">
                      {t.is_blocked && <Lock className="h-3 w-3 shrink-0 text-destructive" aria-label="bloqueada" />}
                      <span className="truncate text-sm text-foreground">{t.title}</span>
                      {t.module && (
                        <span className="shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">
                          {t.module}
                        </span>
                      )}
                      {t.forja_fase && (
                        <span className="shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground" title="Fase do pipeline de tela">
                          {t.forja_fase}
                        </span>
                      )}
                    </Inline>

                    <Inline gap={2} align="center">
                      {t.is_overdue && (
                        <Clock className="h-3 w-3 text-destructive" aria-label="atrasada" />
                      )}
                      {t.owner
                        ? <span className="text-xs text-muted-foreground">{t.owner}</span>
                        : <span className="text-xs italic text-muted-foreground/60">sem dono</span>}
                      <span className={cn('rounded px-1.5 py-0.5 text-[10px] font-medium', STATUS_BADGE[t.status])}>
                        {COLUMN_LABEL_PT[t.status] ?? t.status}
                      </span>
                    </Inline>
                  </Grid>
                ))}
              </CardContent>
            </Card>
          </Stack>
        ))}
      </Stack>
    </AppShellV2>
  );
}
