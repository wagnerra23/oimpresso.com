// Forja — aba Backlog. Projeta `mcp_tasks` project=FORJA em TODOS os status
// (não só triagem) como uma lista de issues agrupável client-side por
// Onda / Fase / Papel / Prioridade / Módulo. Sem dado fantasma — só projeta o
// que o ForjaBacklogService.build() já leu do banco.
//
// Reuso: badges de tipo (Tela=roxo · Bug=âmbar · Refino=azul) + idioma DS v6
// (tokens semânticos, tabular-nums, layout via inline-flex/primitivos,
// data-testid locators) espelham resources/js/Pages/team-mcp/Forja/_components/ForjaTriage.tsx.

import { useMemo, useState } from 'react';
import { cn } from '@/Lib/utils';

export interface BacklogTask {
  display_id: string;
  title: string;
  tipo: string | null;
  fase: string | null;
  papel: string | null;
  onda: string | null;
  modulo: string | null;
  prioridade: string;
  status: string;
}

// Badge de tipo — mesmo contrato pixel do protótipo usado na Triagem: Tela=roxo ·
// Bug=âmbar · Refino=azul. 100% tokens semânticos DS v6 (sem paleta crua —
// ui:lint R1 = 0): primary / warning / info. Auto-adaptam ao dark.
const TIPO_BADGE: Record<string, string> = {
  Tela:   'bg-primary/10 text-primary',
  Bug:    'bg-warning-soft text-warning-fg',
  Refino: 'bg-info-soft text-info-fg',
};
const TIPO_FALLBACK = 'bg-muted text-muted-foreground';

// Dimensões de agrupamento (seletor AGRUPAR). key = campo da BacklogTask.
const GRUPOS = [
  { key: 'onda',       label: 'Onda' },
  { key: 'fase',       label: 'Fase' },
  { key: 'papel',      label: 'Papel' },
  { key: 'prioridade', label: 'Prioridade' },
  { key: 'modulo',     label: 'Módulo' },
] as const;

type GrupoKey = (typeof GRUPOS)[number]['key'];

const SEM_GRUPO = 'Sem classificação';

// Ordem canônica de prioridade (p0 mais alta primeiro). Demais dimensões ordenam
// alfabeticamente; SEM_GRUPO sempre por último.
const PRIO_ORDER: Record<string, number> = { p0: 0, p1: 1, p2: 2, p3: 3 };

function groupValue(task: BacklogTask, key: GrupoKey): string {
  const raw = task[key];
  return raw && raw.trim() !== '' ? raw : SEM_GRUPO;
}

export default function ForjaBacklog({ backlog = [] }: { backlog?: BacklogTask[] }) {
  const [agrupar, setAgrupar] = useState<GrupoKey>('onda');

  // Reagrupa client-side quando muda a dimensão (ou a lista). Cada grupo vira
  // { nome, tasks } e a lista de grupos é ordenada pela dimensão escolhida.
  const grupos = useMemo(() => {
    const mapa = new Map<string, BacklogTask[]>();
    for (const task of backlog) {
      const nome = groupValue(task, agrupar);
      const bucket = mapa.get(nome);
      if (bucket) {
        bucket.push(task);
      } else {
        mapa.set(nome, [task]);
      }
    }

    return Array.from(mapa.entries())
      .map(([nome, tasks]) => ({ nome, tasks }))
      .sort((a, b) => {
        // SEM_GRUPO sempre por último.
        if (a.nome === SEM_GRUPO) return 1;
        if (b.nome === SEM_GRUPO) return -1;
        if (agrupar === 'prioridade') {
          const oa = PRIO_ORDER[a.nome] ?? 99;
          const ob = PRIO_ORDER[b.nome] ?? 99;
          if (oa !== ob) return oa - ob;
        }
        return a.nome.localeCompare(b.nome);
      });
  }, [backlog, agrupar]);

  return (
    <div data-testid="forja-backlog">
      {/* Seletor AGRUPAR (segmented) — reagrupa client-side. */}
      <div className="inline-flex w-full items-center gap-2">
        <span className="text-xs font-medium text-muted-foreground">Agrupar por</span>
        <div className="inline-flex items-center gap-0.5 rounded-md border p-0.5" role="group" aria-label="Agrupar backlog por">
          {GRUPOS.map((g) => {
            const active = g.key === agrupar;
            return (
              <button
                key={g.key}
                type="button"
                onClick={() => setAgrupar(g.key)}
                aria-pressed={active}
                className={cn(
                  'inline-flex items-center rounded px-2.5 py-1 text-xs font-medium transition-colors',
                  active
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                )}
                data-testid={`forja-agrupar-${g.key}`}
              >
                {g.label}
              </button>
            );
          })}
        </div>
      </div>

      {backlog.length === 0 ? (
        <div className="mt-8 inline-flex w-full flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-16 text-center text-muted-foreground">
          <p className="text-base font-medium text-foreground">Backlog vazio</p>
          <p className="text-sm">Nenhuma issue project=FORJA no banco ainda.</p>
        </div>
      ) : (
        <div className="mt-4 inline-flex w-full flex-col gap-5">
          {grupos.map((grupo) => (
            <section key={grupo.nome}>
              {/* Cabeçalho de grupo com contagem */}
              <div className="inline-flex w-full items-center gap-2 border-b pb-1.5">
                <h3 className="text-xs font-semibold uppercase tracking-wider text-foreground">
                  {grupo.nome}
                </h3>
                <span className="inline-flex h-4 min-w-4 items-center justify-center rounded bg-muted px-1 text-[10px] font-semibold tabular-nums text-muted-foreground">
                  {grupo.tasks.length}
                </span>
              </div>

              <div className="mt-1.5 divide-y rounded-lg border">
                {grupo.tasks.map((t) => {
                  const tipo = t.tipo ?? '—';
                  return (
                    <div
                      key={t.display_id}
                      className="inline-flex w-full items-center gap-3 px-4 py-2.5 transition-colors hover:bg-muted/40"
                      data-testid="forja-backlog-row"
                    >
                      {/* ID mono */}
                      <span className="shrink-0 font-mono text-[11px] tabular-nums text-muted-foreground">
                        {t.display_id}
                      </span>

                      {/* Badge de tipo (Tela=roxo · Bug=âmbar · Refino=azul) */}
                      <span
                        className={cn(
                          'shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold',
                          TIPO_BADGE[tipo] ?? TIPO_FALLBACK,
                        )}
                        data-testid="forja-tipo"
                      >
                        {tipo}
                      </span>

                      {/* Título (cresce) */}
                      <span className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">
                        {t.title}
                      </span>

                      {/* Módulo */}
                      {t.modulo && (
                        <span className="hidden shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground sm:inline">
                          {t.modulo}
                        </span>
                      )}

                      {/* Fase */}
                      {t.fase && (
                        <span className="hidden shrink-0 text-[10px] text-muted-foreground md:inline">
                          {t.fase}
                        </span>
                      )}

                      {/* Papel (pílula mono) */}
                      {t.papel && (
                        <span className="hidden shrink-0 rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground md:inline">
                          [{t.papel}]
                        </span>
                      )}

                      {/* Prioridade */}
                      <span className="shrink-0 font-mono text-[10px] uppercase tabular-nums text-muted-foreground">
                        {t.prioridade}
                      </span>
                    </div>
                  );
                })}
              </div>
            </section>
          ))}
        </div>
      )}

      {/* Rodapé com contagem total */}
      <p className="mt-4 text-xs text-muted-foreground tabular-nums">
        {backlog.length} {backlog.length === 1 ? 'issue' : 'issues'}
      </p>
    </div>
  );
}
