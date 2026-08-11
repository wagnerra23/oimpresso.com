// Quadro do Trabalho — DOIS EIXOS sobre a MESMA lista.
//
// Por que dois, e não um: o hub tinha dois boards que pareciam concorrentes e
// respondiam perguntas diferentes.
//
//   PIPELINE (F0→F3.5) — "em que ponto do protocolo de TELA isto está?"
//     Só faz sentido pra trabalho de tela. Task de infra/gate/ADR não tem fase, e
//     isso é correto — não é dado faltando. Por isso o eixo FILTRA em vez de
//     inventar uma coluna "sem fase": card sem fase não pertence a este board.
//
//   EXECUÇÃO (status canon) — "o que está andando?"
//     Vocabulário de `mcp_tasks` (backlog/todo/doing/review/done/blocked). Vale
//     pra TODA task, inclusive as que não são de tela. É onde infra/gate/ADR vive.
//
// F4/`done` NÃO é coluna do pipeline: quando a tela conclui, ela sai do board e
// vira changelog. No eixo Execução, `done`/`cancelled` também ficam fora — board
// de trabalho mostra o que está em curso, não o arquivo.
//
// ⚠️ SEM DRAG NESTA ONDA. O pedido descreve arrastar pra mudar `status` (e propor
// mudança de fase). Mover card é MUTAÇÃO, e mutação sem o caminho governado
// (`TaskCrudService`, que valida o FSM) seria um segundo caminho de escrita — o
// erro que a Mesa evitou de propósito. Fica pra onda com o endpoint.

import { Card, CardContent } from '@/Components/ui/card';
import { Grid, Inline, Stack } from '@/Components/layout';
import { COLUMN_LABEL_PT, COLUMN_BORDER, type Status } from '@/Components/board/badges';
// Selos canônicos — reuso, não hand-roll. O `ActorSeal` distingue AGENTE de
// HUMANO, que é o conceito central da Forja (o subtítulo do hub diz "atores
// humano vs agente"); a 1ª versão deste quadro mostrava `owner` em texto cinza
// e perdia a distinção inteira. Ver proibicoes §5 2026-08-10 — "Construir tela
// derivando do CÓDIGO quando existe FONTE DE DESIGN".
import { ActorSeal, PriorityDot } from '@/Components/shared/TaskBadges';
import type { Priority } from '@/Lib/taskTokens';
import { cn } from '@/Lib/utils';
import { Lock } from 'lucide-react';

export type EixoQuadro = 'pipeline' | 'execucao';

interface TaskCard {
  task_id: string;
  display_id: string;
  title: string;
  module: string | null;
  owner: string | null;
  priority: Priority;
  status: Status;
  is_blocked: boolean;
  forja_fase: string | null;
}

/**
 * As fases do protocolo — MESMA lista e MESMOS rótulos do `ForjaQuadroService`
 * (`Modules/Forja/Services/ForjaQuadroService.php`). Duplicar aqui seria criar
 * uma segunda declaração do pipeline; isto é espelho consciente do backend, e o
 * `UC-TRAB-07` trava a igualdade dos dois lados.
 */
const FASES: { key: string; label: string }[] = [
  { key: 'F0',   label: 'F0 Brief' },
  { key: 'F1',   label: 'F1 Design' },
  { key: 'F1.5', label: 'F1.5 Critique' },
  { key: 'F2',   label: 'F2 Screenshot' },
  { key: 'F3',   label: 'F3 Code' },
  { key: 'F3.5', label: 'F3.5 A11y' },
];

/** Colunas do eixo Execução — as ATIVAS. `done`/`cancelled` saem do board. */
const STATUS_ATIVOS: Status[] = ['todo', 'doing', 'review', 'blocked'];

export default function TrabalhoQuadro({ tasks, eixo, agents = [] }: { tasks: TaskCard[]; eixo: EixoQuadro; agents?: string[] }) {
  const colunas = eixo === 'pipeline'
    ? FASES.map((f) => ({
        chave: f.key,
        titulo: f.label,
        borda: 'border-primary/40',
        // Só quem TEM a fase. Card sem fase não é trabalho de tela — não
        // pertence a este board, e forçá-lo numa coluna mentiria sobre ele.
        itens: tasks.filter((t) => t.forja_fase === f.key),
      }))
    : STATUS_ATIVOS.map((s) => ({
        chave: s,
        titulo: COLUMN_LABEL_PT[s],
        borda: COLUMN_BORDER[s],
        itens: tasks.filter((t) => t.status === s),
      }));

  const totalNoBoard = colunas.reduce((n, c) => n + c.itens.length, 0);
  const foraDoBoard = tasks.length - totalNoBoard;

  return (
    <Stack gap={3}>
      {/* Honestidade sobre o recorte: o board NUNCA mostra tudo, e o usuário
          precisa saber quantas ficaram de fora e por quê — senão ele conta os
          cards, compara com o KPI e conclui que o sistema perdeu task. */}
      <p className="text-xs text-muted-foreground tabular-nums" data-testid="quadro-recorte">
        {totalNoBoard} no quadro
        {foraDoBoard > 0 && (
          <> · <span data-testid="quadro-fora">{foraDoBoard} fora</span>{' '}
            {eixo === 'pipeline'
              ? '(sem fase de pipeline — infra, gate, ADR: não é trabalho de tela)'
              : '(concluídas ou canceladas — board mostra o que está em curso)'}
          </>
        )}
      </p>

      <Grid gap={3} className="md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-6" data-testid="trabalho-quadro">
        {colunas.map((col) => (
          <Stack gap={2} key={col.chave}>
            <Inline gap={2} align="baseline" className={cn('border-t-2 pt-2', col.borda)}>
              <h3 className="text-xs font-semibold uppercase tracking-wide text-foreground">
                {col.titulo}
              </h3>
              <span className="text-xs text-muted-foreground tabular-nums">{col.itens.length}</span>
            </Inline>

            {col.itens.length === 0 && (
              <p className="px-1 text-xs italic text-muted-foreground/60">vazia</p>
            )}

            {col.itens.map((t) => (
              <Card key={t.task_id} data-testid="quadro-card">
                <CardContent className="p-2.5">
                  <Stack gap={2}>
                    <Inline gap={2} align="center">
                      <PriorityDot priority={t.priority} />
                      <span className="font-mono text-[10px] text-muted-foreground">{t.display_id}</span>
                      {t.is_blocked && <Lock className="ml-auto h-3 w-3 text-destructive" aria-label="bloqueada" />}
                    </Inline>

                    <p className="line-clamp-2 text-xs leading-snug text-foreground">{t.title}</p>

                    <Inline gap={2} align="center">
                      {t.module && (
                        <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">
                          {t.module}
                        </span>
                      )}
                      <ActorSeal owner={t.owner} agents={agents} className="ml-auto" />
                    </Inline>
                  </Stack>
                </CardContent>
              </Card>
            ))}
          </Stack>
        ))}
      </Grid>
    </Stack>
  );
}

export { FASES as FASES_PIPELINE, STATUS_ATIVOS };
