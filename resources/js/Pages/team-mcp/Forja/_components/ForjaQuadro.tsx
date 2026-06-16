// Forja — aba Quadro (board F0→F3.5). Espelha a Triagem (ForjaTriage.tsx) em
// DS v6/defer/badges de tipo, mas em formato kanban horizontal: 1 coluna por
// fase do protocolo (F0 Brief → F3.5 A11y), cards projetados de mcp_tasks
// project=FORJA agrupados por custom_fields['forja_fase'] (ForjaQuadroService).
//
// DS v6: SÓ tokens semânticos (primary / warning-soft / info-soft / muted /
// foreground / border) — ui:lint R1 = 0 (sem paleta crua). Layout via
// inline-flex/inline-grid (sem flex/grid solto), tabular-nums nas contagens,
// máx rounded-lg, data-testid locators.

import { cn } from '@/Lib/utils';

interface QuadroCard {
  display_id: string;
  title: string;
  tipo: string | null;
  onda: string | null;
}

interface QuadroFase {
  key: string;
  label: string;
  cards: QuadroCard[];
}

export interface QuadroData {
  fases: QuadroFase[];
}

interface Props {
  // quadro chega via Inertia::defer (ForjaController) → undefined no 1º paint.
  // Default-guard `?` + fallback `[]` pra NÃO crashar antes do defer resolver
  // (skill inertia-defer-default; espelha ForjaTriage.tsx).
  quadro?: QuadroData;
}

// Badge de tipo (mesmo contrato pixel da Triagem): Tela=roxo · Bug=âmbar · Refino=azul.
// 100% tokens semânticos DS v6 (sem paleta crua): primary / warning / info.
const TIPO_BADGE: Record<string, string> = {
  Tela:   'bg-primary/10 text-primary',
  Bug:    'bg-warning-soft text-warning-fg',
  Refino: 'bg-info-soft text-info-fg',
};
const TIPO_FALLBACK = 'bg-muted text-muted-foreground';

export default function ForjaQuadro({ quadro }: Props) {
  const fases = quadro?.fases ?? [];

  return (
    <div data-testid="forja-quadro">
      {/* Texto-âncora (contrato pixel) */}
      <p className="mt-1 max-w-3xl text-xs leading-relaxed text-muted-foreground">
        <strong className="text-foreground">
          O ciclo de vida de cada tela, do brief à acessibilidade.
        </strong>{' '}
        Cada card avança da esquerda pra direita conforme o protocolo Forja
        formaliza a fase (F0 → F3.5).
      </p>

      {/* Board horizontal: colunas roláveis em x (uma por fase). */}
      <div className="mt-4 inline-flex w-full gap-3 overflow-x-auto pb-2">
        {fases.map((fase) => (
          <div
            key={fase.key}
            className="inline-flex w-64 shrink-0 flex-col rounded-lg border bg-muted/30"
            data-testid="forja-quadro-coluna"
          >
            {/* Header da coluna: label + contagem (tabular-nums) */}
            <div className="inline-flex items-center justify-between gap-2 border-b px-3 py-2">
              <span className="truncate text-xs font-semibold text-foreground">
                {fase.label}
              </span>
              <span className="shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium tabular-nums text-muted-foreground">
                {fase.cards.length}
              </span>
            </div>

            {/* Cards da fase (ou área pontilhada se vazia) */}
            <div className="inline-flex w-full flex-col gap-2 p-2">
              {fase.cards.length === 0 ? (
                <div className="inline-flex w-full items-center justify-center rounded-lg border border-dashed py-10 text-xs text-muted-foreground">
                  —
                </div>
              ) : (
                fase.cards.map((card) => {
                  const tipo = card.tipo ?? '—';
                  return (
                    <div
                      key={card.display_id}
                      className="inline-flex w-full flex-col gap-1.5 rounded-lg border bg-background px-2.5 py-2 transition-colors hover:bg-muted/40"
                      data-testid="forja-quadro-card"
                    >
                      {/* ID mono + onda */}
                      <div className="inline-flex w-full items-center justify-between gap-2">
                        <span className="shrink-0 font-mono text-[11px] tabular-nums text-muted-foreground">
                          {card.display_id}
                        </span>
                        {card.onda && (
                          <span className="shrink-0 truncate rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">
                            {card.onda}
                          </span>
                        )}
                      </div>

                      {/* Título */}
                      <span className="line-clamp-2 text-xs font-medium text-foreground">
                        {card.title}
                      </span>

                      {/* Badge de tipo (Tela=roxo · Bug=âmbar · Refino=azul) */}
                      <span
                        className={cn(
                          'inline-flex w-fit shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold',
                          TIPO_BADGE[tipo] ?? TIPO_FALLBACK,
                        )}
                        data-testid="forja-quadro-tipo"
                      >
                        {tipo}
                      </span>
                    </div>
                  );
                })
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
