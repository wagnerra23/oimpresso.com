// Forja — aba Triagem (F0 formalizado). Tela fiel ao protótipo aprovado
// (memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md §1 Triagem).
//
// Projeta `mcp_tasks` project=FORJA em estado de triagem (sem owner OU sem
// priority OU backlog) — projeção, sem dado fantasma. Cada linha: ID mono ·
// badge de tipo (Tela=roxo · Bug=âmbar · Refino=azul) · título · tag de módulo ·
// selo de ator [CC] · botão roxo "Analisar" → abre o dossiê lateral (ForjaDossier,
// que reusa o padrão Analista de ProjectMgmt apontando pros endpoints /forja/*).
//
// Reuso: estrutura de lista + navegação J/K + defer-guard espelham
// resources/js/Pages/ProjectMgmt/Triage/Index.tsx. DS v6: roxo canon (text/bg-primary)
// nas primárias, tabular-nums, layout via inline-flex/primitivos, data-testid locators.

import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { CheckCircle2, FileSearch, Inbox as InboxIcon } from 'lucide-react';
import { cn } from '@/Lib/utils';
import ForjaDossier from './ForjaDossier';

export interface ForjaTicket {
  task_id: string;
  identifier: string | null;
  display_id: string;
  title: string;
  module: string | null;
  owner: string | null;
  priority_raw: string | null;
  priority: string;
  status: string;
  type: string | null;
  forja_tipo: string | null;
  forja_papel: string | null;
  forja_onda: string | null;
  created_at: string | null;
  needs_owner: boolean;
  needs_prio: boolean;
  is_backlog: boolean;
}

interface Props {
  // tickets chega via Inertia::defer (ForjaController@triagem) → undefined no 1º
  // paint. Default-guard `= []` no destructuring pra NÃO crashar antes do defer
  // (skill inertia-defer-default; espelha ProjectMgmt/Triage/Index.tsx).
  tickets?: ForjaTicket[];
}

// Badge de tipo (contrato pixel do protótipo): Tela=roxo · Bug=âmbar · Refino=azul.
// 100% tokens semânticos DS v6 (sem paleta crua — ui:lint R1 = 0): primary /
// warning / info. Auto-adaptam ao dark (sem variantes dark: manuais).
const TIPO_BADGE: Record<string, string> = {
  Tela:   'bg-primary/10 text-primary',
  Bug:    'bg-warning-soft text-warning-fg',
  Refino: 'bg-info-soft text-info-fg',
};
const TIPO_FALLBACK = 'bg-muted text-muted-foreground';

export default function ForjaTriage({ tickets = [] }: Props) {
  // Tickets resolvidos (aprovado/rejeitado/fundido) — escondidos localmente até reload.
  const [resolved, setResolved] = useState<Set<string>>(new Set());
  // Linha em foco pra navegação J/K (mesma mecânica do Board/Triage).
  const [selectedId, setSelectedId] = useState<string | null>(null);
  // Drawer do dossiê do Analista.
  const [dossierId, setDossierId] = useState<string | null>(null);

  const visible = useMemo(
    () => tickets.filter((t) => !resolved.has(t.task_id)),
    [tickets, resolved],
  );

  // Quando a lista muda e o selecionado some, escolhe o primeiro (igual Board/Triage).
  useEffect(() => {
    if (!visible.length) {
      setSelectedId(null);
      return;
    }
    if (selectedId && !visible.find((t) => t.task_id === selectedId)) {
      setSelectedId(visible[0]?.task_id ?? null);
    }
  }, [visible, selectedId]);

  // Atalhos canônicos J/K (navegar) + Enter (abrir dossiê) — mesma mecânica inline
  // que Triage/Index.tsx. ⌘K (palette global) é dono do AppShellV2, não re-registra.
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      const tgt = e.target as HTMLElement | null;
      const isTyping = tgt && (
        tgt.tagName === 'INPUT' || tgt.tagName === 'TEXTAREA' || tgt.isContentEditable
      );
      if (isTyping) return;
      if (!visible.length) return;

      const idx = selectedId ? visible.findIndex((t) => t.task_id === selectedId) : -1;
      const cur = idx >= 0 ? visible[idx] : null;

      if (e.key === 'j' || e.key === 'J') {
        e.preventDefault();
        const n = idx < 0 ? 0 : Math.min(visible.length - 1, idx + 1);
        setSelectedId(visible[n]?.task_id ?? null);
      } else if (e.key === 'k' || e.key === 'K') {
        e.preventDefault();
        const p = idx <= 0 ? 0 : idx - 1;
        setSelectedId(visible[p]?.task_id ?? null);
      } else if (e.key === 'Enter' && cur) {
        e.preventDefault();
        setDossierId(cur.task_id);
      }
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [visible, selectedId]);

  return (
    <div data-testid="forja-triagem">
      {/* Texto-âncora (contrato pixel) */}
      <p className="mt-1 max-w-3xl text-xs leading-relaxed text-muted-foreground">
        <strong className="text-foreground">
          Tickets propostos aguardando o analista [AN] enriquecer e sua aprovação.
        </strong>{' '}
        Entram no backlog só depois — é o F0 do protocolo, formalizado.
      </p>

      {visible.length === 0 ? (
        <div className="mt-8 inline-flex w-full flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-16 text-center text-muted-foreground">
          <CheckCircle2 size={28} className="text-success" />
          <p className="text-base font-medium text-foreground">Nada pra triar</p>
          <p className="text-sm">Nenhuma proposta aguardando enriquecimento e aprovação.</p>
        </div>
      ) : (
        <div className="mt-4 divide-y rounded-lg border">
          {visible.map((t) => {
            const isSelected = t.task_id === selectedId;
            const tipo = t.forja_tipo ?? '—';
            return (
              <div
                key={t.task_id}
                aria-current={isSelected ? 'true' : undefined}
                className={cn(
                  'inline-flex w-full items-center gap-3 px-4 py-3 transition-colors',
                  isSelected ? 'bg-muted/60 ring-1 ring-inset ring-primary/60' : 'hover:bg-muted/40',
                )}
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

                {/* Tag de módulo (pílula sutil) */}
                {t.module && (
                  <span className="hidden shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground sm:inline">
                    {t.module}
                  </span>
                )}

                {/* Selo de ator [CC] (pílula mono) */}
                {t.forja_papel && (
                  <span className="hidden shrink-0 rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground sm:inline">
                    [{t.forja_papel}]
                  </span>
                )}

                {/* Botão roxo Analisar → dossiê lateral */}
                <button
                  type="button"
                  onClick={() => setDossierId(t.task_id)}
                  className="inline-flex shrink-0 items-center gap-1 rounded-md bg-primary px-2.5 py-1 text-[11px] font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                  data-testid="forja-analisar"
                >
                  <FileSearch size={12} /> Analisar
                </button>
              </div>
            );
          })}
        </div>
      )}

      <p className="mt-4 inline-flex items-center gap-1 text-xs text-muted-foreground">
        <InboxIcon size={12} className="-mt-0.5" />
        Fila = <code className="font-mono">mcp_tasks</code> project=FORJA em triagem (sem dono · sem
        prioridade · ou backlog). Aprovar promove pro backlog; rejeitar cancela. Nada vira oficial
        sem você confirmar.
      </p>

      {/* Dossiê do Analista (agente propõe, [W] aprova) */}
      <ForjaDossier
        taskId={dossierId}
        onClose={() => setDossierId(null)}
        onResolved={(id) => {
          setResolved((prev) => new Set(prev).add(id));
          router.reload({ only: ['tickets', 'triagemCount'] });
        }}
      />
    </div>
  );
}
