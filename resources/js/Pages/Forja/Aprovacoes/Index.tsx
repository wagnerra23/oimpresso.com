// Mesa de Aprovações — a superfície do funil de admissão (ADR 0368).
//
// @memcofre
//   tela: /forja/aprovacoes
//   module: Forja
//   adrs: 0368 (funil de admissão) · 0070 (Jira-style) · UI-0013 (Constituição UI v2)
//   permissao: jana.mcp.usage.all
//   paridade: fila = McpTask::AWAITING_HUMAN; decisão = TaskCrudService (mesmo
//             chokepoint da tool MCP `tasks-update`)
//
// A ADR 0368 fechou a política em 2026-08-04 e deixou o código pra "PR próprio".
// O estado e as travas vieram em #5283/#5288 — faltava a tela. Sem ela a fila
// existia no banco e o [W] não tinha onde olhar.
//
// ⚠️ `<ForjaHub>` é OBRIGATÓRIO em Page sob /forja/*: o hub esconde a topbar do
// AppShellV2 e desenha a própria faixa. Sem montar aqui, a tela abre sem
// navegação nenhuma — foi o que aconteceu com o Roadmap (Gantt) até 2026-08-06
// (lápide §5 "Navegação tem CINCO superfícies na Forja").
//
// ⚠️ POR QUE O DESFAZER ADIA O ENVIO EM VEZ DE REVERTER ─────────────────────
// O FSM não tem volta pra `pending_approval`: `TRANSITIONS` não a lista como
// destino de todo/backlog/cancelled. Um botão "Desfazer" que tentasse reverter
// bateria em 422 sempre — seria mecanismo ANUNCIANDO saída que não implementa
// (lápide §5 2026-07-30). Então a janela de 6s acontece ANTES do POST: durante
// ela nada foi persistido, e desfazer é só cancelar o timer. Modelo "Undo Send"
// do Gmail. O custo honesto: a decisão leva 6s pra valer — e é justamente esse
// atraso que torna o desfazer real em vez de decorativo.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
// PageHeader canon (named, `@/Components/PageHeader`) — o mesmo do Gantt, que é a
// tela irmã sob /forja/*. O `shared/PageHeader` (default) é o das telas nativas
// /project-mgmt/*; misturar os dois no mesmo hub dá dois cabeçalhos diferentes.
import { PageHeader } from '@/Components/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import { PRIORITY_BADGE, type Priority } from '@/Components/board/badges';
import ForjaHub from '@/Pages/team-mcp/Forja/_components/ForjaHub';
// Layout é COMPOSIÇÃO destes primitivos, nunca `<div className="flex gap-4">` solto
// (ADR 0253 — enforçado pelo `layout-primitives-guard`).
import { Grid, Inline, Stack } from '@/Components/layout';
import { cn } from '@/Lib/utils';
import { CheckCircle2, PauseCircle, Undo2, XCircle } from 'lucide-react';

/** Faixa de espera calculada no backend (ForjaAprovacoesService::sla). */
type Sla = 'ok' | 'atencao' | 'urgente';

interface ItemFila {
  task_id: string;
  identifier: string | null;
  title: string;
  module: string | null;
  type: string | null;
  priority: Priority | null;
  owner: string | null;
  created_at: string | null;
  created_at_human: string | null;
  espera_min: number;
  sla: Sla;
}

/** Decisão possível — vem DERIVADA do FSM pelo backend, nunca hardcoded aqui. */
interface Decisao {
  status: string;
  verbo: string;
  descricao: string;
  exige_motivo: boolean;
  atalho: string;
}

interface Props {
  titulo: string;
  subtitle: string;
  decisoes: Decisao[];
  // `fila`/`contagem` chegam por Inertia::defer → `undefined` no 1º paint.
  // Default no destructuring pra não crashar antes do defer (skill
  // inertia-defer-default; o sintoma sem isso é tela branca — PR #1940).
  fila?: ItemFila[];
  contagem?: number;
}

/** Segundos de arrependimento antes da decisão sair pro servidor. */
const JANELA_DESFAZER_S = 6;

const SLA_CLASSE: Record<Sla, string> = {
  ok: 'text-muted-foreground',
  atencao: 'text-amber-600 dark:text-amber-400',
  urgente: 'text-red-600 dark:text-red-400',
};

const SLA_TITULO: Record<Sla, string> = {
  ok: 'Esperando há pouco.',
  atencao: 'Esperando há mais de 30 minutos.',
  urgente: 'Esperando há mais de 2 horas.',
};

const ICONE_DECISAO: Record<string, typeof CheckCircle2> = {
  todo: CheckCircle2,
  backlog: PauseCircle,
  cancelled: XCircle,
};

interface Pendente {
  item: ItemFila;
  decisao: Decisao;
  motivo: string;
  restam: number;
}

export default function Aprovacoes({ titulo, subtitle, decisoes, fila = [], contagem = 0 }: Props) {
  const [motivo, setMotivo] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);
  /** Decisão tomada, ainda NÃO enviada — a janela de arrependimento. */
  const [pendente, setPendente] = useState<Pendente | null>(null);

  // Enquanto há decisão pendente, o item sai da vista (a mesa já andou), mas o
  // POST ainda não saiu. `fila[0]` é sempre o mais antigo — a ordem é do backend.
  const atual = pendente ? null : (fila[0] ?? null);

  // Item novo na mesa = motivo/erro do anterior não valem mais.
  useEffect(() => {
    setMotivo('');
    setErro(null);
  }, [atual?.task_id]);

  /** Manda a decisão pro servidor. Só chamado quando a janela de desfazer expira. */
  const enviar = useCallback((p: Pendente) => {
    setEnviando(true);

    router.post(
      `/forja/aprovacoes/${p.item.task_id}/decidir`,
      { destino: p.decisao.status, motivo: p.motivo },
      {
        preserveScroll: true,
        onError: (errs) =>
          setErro(
            Object.values(errs)[0] ??
              `Não foi possível ${p.decisao.verbo.toLowerCase()} ${p.item.identifier ?? p.item.task_id}.`,
          ),
        onFinish: () => {
          setEnviando(false);
          router.reload({ only: ['fila', 'contagem'] });
        },
      },
    );
  }, []);

  // `enviar` numa ref pro efeito do contador não depender dela (e não reiniciar
  // o timer a cada render).
  const enviarRef = useRef(enviar);
  useEffect(() => {
    enviarRef.current = enviar;
  }, [enviar]);

  // Contagem regressiva. Ao zerar, a decisão SAI — antes disso ela não existe
  // pra ninguém além desta tela.
  useEffect(() => {
    if (!pendente) return;

    if (pendente.restam <= 0) {
      enviarRef.current(pendente);
      setPendente(null);
      return;
    }

    const t = setTimeout(() => setPendente((p) => (p ? { ...p, restam: p.restam - 1 } : null)), 1000);
    return () => clearTimeout(t);
  }, [pendente]);

  const decidir = useCallback(
    (decisao: Decisao) => {
      if (!atual || enviando || pendente) return;

      if (decisao.exige_motivo && motivo.trim() === '') {
        // Espelha a trava do backend (ADR 0368 §5) pra dar o retorno na hora —
        // mas quem de fato barra é o TaskCrudService, não este if.
        setErro('Recusar exige motivo — ele vai pro inventário e evita que a mesma proposta volte em três meses.');
        return;
      }

      setErro(null);
      setPendente({ item: atual, decisao, motivo: motivo.trim(), restam: JANELA_DESFAZER_S });
    },
    [atual, enviando, pendente, motivo],
  );

  /** Desfazer = cancelar antes do envio. Nada foi persistido, então é de verdade. */
  const desfazer = useCallback(() => setPendente(null), []);

  // Atalhos: cada decisão traz o seu (a/d/x), vindos do backend junto do FSM.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const alvo = e.target as HTMLElement | null;
      // Não sequestrar tecla enquanto o [W] digita o motivo.
      if (alvo && ['INPUT', 'TEXTAREA', 'SELECT'].includes(alvo.tagName)) return;
      if (e.metaKey || e.ctrlKey || e.altKey) return;

      const d = decisoes.find((x) => x.atalho === e.key.toLowerCase());
      if (d) {
        e.preventDefault();
        decidir(d);
      }
    };

    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [decisoes, decidir]);

  const urgentes = useMemo(() => fila.filter((i) => i.sla === 'urgente').length, [fila]);

  return (
    <AppShellV2
      title="Forja — Aprovações"
      breadcrumbItems={[{ label: 'Forja' }, { label: 'Aprovações' }]}
    >
      <ForjaHub active="aprovacoes" />

      <div className="space-y-6">
        <PageHeader title={titulo} subtitle={subtitle} />

        <KpiGrid>
          <KpiCard label="Esperando você" value={contagem} icon="Inbox" />
          <KpiCard label="Há mais de 2h" value={urgentes} icon="AlarmClock" />
        </KpiGrid>

        {pendente && (
          <Card data-testid="mesa-desfazer">
            <Inline asChild gap={3} align="center" justify="between" wrap>
              <CardContent className="py-3">
                <p className="text-sm text-foreground">
                  <strong>{pendente.decisao.verbo}</strong>{' '}
                  {pendente.item.identifier ?? pendente.item.task_id} — {pendente.item.title}
                </p>
                <Inline gap={3} align="center">
                  <span className="text-xs text-muted-foreground tabular-nums" aria-live="polite">
                    vale em {pendente.restam}s
                  </span>
                  <Button variant="outline" size="sm" onClick={desfazer} data-testid="mesa-desfazer-btn">
                    <Undo2 className="mr-1.5 h-3.5 w-3.5" aria-hidden />
                    Desfazer
                  </Button>
                </Inline>
              </CardContent>
            </Inline>
          </Card>
        )}

        {erro && (
          <p role="alert" className="text-sm text-red-600 dark:text-red-400" data-testid="mesa-erro">
            {erro}
          </p>
        )}

        {!atual && !pendente && (
          <Card data-testid="mesa-vazia">
            <CardContent className="py-12 text-center">
              <CheckCircle2 className="mx-auto mb-3 h-8 w-8 text-muted-foreground" aria-hidden />
              <p className="text-sm font-medium text-foreground">Nada esperando por você.</p>
              <p className="mt-1 text-sm text-muted-foreground">
                Quando uma proposta entrar no funil, ela aparece aqui — mais antiga primeiro.
              </p>
            </CardContent>
          </Card>
        )}

        {atual && (
          {/* 1fr + coluna fixa de decisões: `cols` do primitivo é uniforme, então a
              proporção vai em className — o `Grid` segue dono do display e do gap. */}
          <Grid gap={4} className="lg:grid-cols-[1fr_18rem]">
            {/* O artefato no centro — é o que se decide, não a linha da lista. */}
            <Card data-testid="mesa-artefato">
              <CardContent className="space-y-4 py-5">
                <Inline gap={2} align="center" wrap>
                  <span className="font-mono text-xs text-muted-foreground">
                    {atual.identifier ?? atual.task_id}
                  </span>
                  {atual.priority && (
                    <span
                      className={cn(
                        'rounded px-1.5 py-0.5 text-[11px] font-medium uppercase',
                        PRIORITY_BADGE[atual.priority],
                      )}
                    >
                      {atual.priority}
                    </span>
                  )}
                  {atual.module && (
                    <span className="rounded bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground">
                      {atual.module}
                    </span>
                  )}
                  <span
                    className={cn('ml-auto text-xs tabular-nums', SLA_CLASSE[atual.sla])}
                    title={SLA_TITULO[atual.sla]}
                  >
                    espera {atual.created_at_human ?? `${atual.espera_min} min`}
                  </span>
                </Inline>

                <h2 className="text-lg font-semibold leading-snug text-foreground">{atual.title}</h2>

                <div>
                  <label htmlFor="mesa-motivo" className="mb-1.5 block text-xs font-medium text-foreground">
                    Motivo <span className="font-normal text-muted-foreground">(obrigatório para recusar)</span>
                  </label>
                  <Textarea
                    id="mesa-motivo"
                    data-testid="mesa-motivo"
                    rows={3}
                    value={motivo}
                    onChange={(e) => setMotivo(e.target.value)}
                    placeholder="Por que esta proposta é recusada? O texto vai pro inventário."
                  />
                </div>
              </CardContent>
            </Card>

            <Stack gap={3}>
              {decisoes.map((d) => {
                const Icone = ICONE_DECISAO[d.status] ?? CheckCircle2;
                return (
                  <Button
                    key={d.status}
                    data-testid={`mesa-decisao-${d.status}`}
                    variant={d.status === 'todo' ? 'default' : 'outline'}
                    className="h-auto w-full justify-start py-2.5 text-left"
                    disabled={enviando}
                    onClick={() => decidir(d)}
                  >
                    <Icone className="mr-2 h-4 w-4 shrink-0" aria-hidden />
                    <span className="min-w-0">
                      <span className="block text-sm font-medium">
                        {d.verbo}
                        <kbd className="ml-1.5 rounded border border-border px-1 text-[10px] uppercase">
                          {d.atalho}
                        </kbd>
                      </span>
                      <span className="block text-xs font-normal opacity-80">{d.descricao}</span>
                    </span>
                  </Button>
                );
              })}

              {fila.length > 1 && (
                <p className="pt-1 text-xs text-muted-foreground tabular-nums">
                  {fila.length} na fila
                </p>
              )}
            </Stack>
          </Grid>
        )}
      </div>
    </AppShellV2>
  );
}
