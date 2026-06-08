// JanaAreaHeader — header sticky compartilhado entre telas Jana (Chat / Dashboard /
// Memoria / Cockpit / Admin/*).
//
// ADR 0182 + GUIA-SIDEBAR-V3 Wagner 2026-05-21: refatorado pra usar JanaSubNav
// canon (ghosts ARIA tablist com auto-promoção do ativo + overflow ⋯ Mais)
// em vez dos 2 tabs hardcoded antigos (Dashboard/Chat). Hue OKLCH 220 (grupo `ia`).
//
// Pattern canon (espelha FinanceiroSubNav usado em /financeiro/*).
//
// Map de retrocompat:
//   active="chat"      → ghost `copiloto` (canon)
//   active="dashboard" → ghost `dashboard`
//   active="memoria"   → ghost `memorias`
//   active="cockpit"   → ghost `copiloto` (Cockpit = visão consolidada)
//   active="custos"    → ghost `custos`
//   (qualquer string) → passada direta como activeGhostKey
//
// Refs:
// - ADR 0180/0182 (sidebar v3 + pageheader canon)
// - GUIA-SIDEBAR-V3-PASSO-A-PASSO (Wagner 2026-05-21)
// - JanaSubNav.tsx (componente filho que lê shell.menu)
// - PR #1053 (Fase 1 anterior, este supersede)

import type { ReactNode } from 'react';
import JanaSubNav from '@/Pages/Jana/_shared/JanaSubNav';
import JanaPrimaryButton from '@/Pages/Jana/_shared/JanaPrimaryButton';
import { router } from '@inertiajs/react';

export type JanaAreaTab =
  | 'chat'      // = copiloto
  | 'dashboard'
  | 'memoria'   // = memorias
  | 'cockpit'   // ghost canon próprio (DataController Wagner 2026-05-25)
  | 'custos'
  | 'metas'
  | 'brief'
  | 'kb'
  | 'regras'
  | 'copiloto'
  | 'memorias'
  // Wagner 2026-05-25 (propagação JanaAreaHeader pra 6 telas faltantes):
  | 'painel'
  | 'roadmap'
  | 'governanca-mcp'
  | 'qualidade-jana';

// Map retrocompat — telas antigas passam 'chat'/'memoria'; convertemos pro
// ghost key canon do DataController Jana. NOTA: 'cockpit' tem ghost próprio
// desde Wagner 2026-05-25 (DataController linha 268), não cai mais em 'copiloto'.
function mapActiveToGhostKey(active: JanaAreaTab): string {
  switch (active) {
    case 'chat':
      return 'copiloto';
    case 'memoria':
      return 'memorias';
    case 'painel':
      // Painel.tsx é mock Onda A1 (sobreposto ao Cockpit). Não tem ghost próprio
      // — usa 'copiloto' como destaque ativo (Wagner avalia se promove a ghost).
      return 'copiloto';
    default:
      return active;
  }
}

export function JanaAreaHeader({ active }: { active: JanaAreaTab }): ReactNode {
  const ghostKey = mapActiveToGhostKey(active);

  return (
    <header
      className="sticky top-0 z-10 flex items-center gap-4 border-b border-border bg-card/95 px-4 py-2 backdrop-blur"
      aria-label="Área Jana"
    >
      {/* Left — area dot + label (hue 220 = SIDEBAR_GROUP_HUE.ia) */}
      <div className="flex shrink-0 items-center gap-2">
        <span
          aria-hidden
          className="inline-block size-2 rounded-full"
          style={{ background: 'oklch(0.62 0.13 220)' }}
        />
        <span className="text-[13px] font-semibold uppercase tracking-wide text-foreground/80">
          JANA
        </span>
      </div>

      {/* Center — JanaSubNav canon (ghost tabs ARIA + overflow ⋯ Mais) */}
      <div className="flex-1 min-w-0">
        <JanaSubNav active={ghostKey} hidePrimary />
      </div>

      {/* Right — primary "Conversar" hue 220 azul (canon ADR 0182) */}
      <div className="shrink-0">
        <JanaPrimaryButton onClick={() => router.visit('/ia')}>
          Conversar
        </JanaPrimaryButton>
      </div>
    </header>
  );
}
