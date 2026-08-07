// JanaAreaHeader — header sticky compartilhado entre as telas Jana
// (Index/Painel · Chat/Conversa · Memoria · Pro).
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

// Onda 3 da fusão (US-COPI-148, 2026-08-07) — o type tinha 15 membros e MEDIDOS
// só 4 eram passados por alguém (`chat` · `dashboard` · `memoria` · `cockpit`,
// mais `copiloto` num uso direto de JanaSubNav). Os 9 removidos são ghosts que já
// não existem no DataController: cada um sobreviveu à remoção do próprio ghost e
// virou membro que o TypeScript aceita alegremente para produzir uma aba que não
// renderiza.
//
//   painel                            onda 1 desta fusão (2026-08-06)
//   brief                             2026-06-15
//   metas                             2026-05-23 (MetasController ainda é Blade)
//   regras                            2026-08-04
//   kb · custos · roadmap
//   governanca-mcp · qualidade-jana   2026-08-05 (ADR 0366)
//
// A US nomeava só o `painel` (resíduo da onda 1); os outros 8 são o mesmo defeito
// com a mesma prova — ghost removido + zero consumidor —, então saem juntos.
export type JanaAreaTab =
  | 'chat'      // = copiloto
  | 'dashboard' // = o Painel; a key vira 'painel' quando o ghost for rekeyado
  | 'memoria'   // = memorias
  // Ghost keys canon — ninguém passa hoje, mas são os alvos do map abaixo e
  // valem como valor direto (o PageHeaderTabs casa por key).
  | 'copiloto'
  | 'memorias';

// Map retrocompat — telas antigas passam 'chat'/'memoria'; convertemos pro
// ghost key canon do DataController Jana. O 'cockpit' saiu do type na onda 4
// (US-COPI-148) junto com a Page e o ghost do menu.
function mapActiveToGhostKey(active: JanaAreaTab): string {
  switch (active) {
    case 'chat':
      return 'copiloto';
    case 'memoria':
      return 'memorias';
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

      {/* Right — primary "Conversar" hue 220 azul (canon ADR 0182).
          Onda 3: destino passou de `/ia` (que virou o Painel) pra `/ia/conversa`. */}
      <div className="shrink-0">
        <JanaPrimaryButton onClick={() => router.visit('/ia/conversa')}>
          Conversar
        </JanaPrimaryButton>
      </div>
    </header>
  );
}
