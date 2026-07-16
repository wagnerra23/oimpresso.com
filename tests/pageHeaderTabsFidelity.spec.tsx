// Fidelidade protótipo↔componente — a aba ATIVA do <PageHeaderTabs> não pode
// divergir do `.cli-moduletopnav-tab.active` do protótipo Cowork aprovado por [W].
//
// CAUSA que este teste fecha: o bug que o [W] pegou NO OLHO (2026-07) — alguém pôs
// `rounded-md` na aba e o underline/pill deixou de bater com o protótipo. Nenhum gate
// pegava: conformance-gate.mjs + stylelint só olham .css; o estilo da aba ativa é
// inline (style={{}}) + className no TSX. Este render-test trava as 4 propriedades-chave
// extraídas de prototipo-ui/cowork/clientes-page.css `.cli-moduletopnav-tab.active`:
//
//   border-radius     : 0                                        → RETO (o bug foi rounded-md)
//   border-bottom-color: var(--accent)                           → underline roxo 295 (ADR 0190)
//   background        : color-mix(in oklch, var(--accent-soft)…) → pill roxo suave, dark-aware
//   font-weight       : 600                                       → font-semibold
//
// 2026-07-15: o inline migrou de literais hardcodados (oklch(0.55 0.15 295) + /0.10) pra
// tokens `var(--accent)` / `color-mix(--accent-soft 50%)` — idênticos ao protótipo, e
// dark-aware de UM lugar só (`--accent-soft` escurece no `.cockpit[data-theme=dark]`).
// Harness claro+escuro (tokens reais) revisado por [W]. As constantes ACCENT/PILL_BG
// abaixo são a NOVA expectativa consciente (ponto único de verdade da fidelidade).
//
// Método (ADR 0258 — "todo ✅ tem que ter sido visto falhar"): controle-negativo
// explícito no detector de radius (sensibilidade: injeta `rounded-md` → detecta;
// especificidade: o className real NÃO casa) + prova que o inline vivo bate o valor.
//
// Refs: ADR proposta tab-nav-canonico · ADR 0338 (eixo valor-vs-token) · ADR 0190
// (primary/accent roxo 295) · clientes-page.css §Slot 2 ModuleTopNav.

import { describe, it, expect } from 'vitest';
import { render, cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';

afterEach(cleanup);

// Inline vivo da aba ativa = os tokens do protótipo (jsdom preserva `var(...)` verbatim —
// provado por probe jsdom antes da entrega). `--accent` == roxo 295 do botão primary
// (ADR 0190); no `.cockpit` (AppShellV2) resolve em toda tela. Se o inline mudar de token,
// atualizar aqui CONSCIENTEMENTE (o teste é o ponto único de verdade da fidelidade).
const ACCENT = 'var(--accent)';
// pill = `color-mix(--accent-soft 50%)` (idem protótipo `.cli-moduletopnav-tab.active`).
// `--accent-soft` é dark-aware (light 0.95 0.04 295 → dark 0.32 0.06 295) → dark de um lugar só.
const PILL_BG = 'color-mix(in oklch, var(--accent-soft) 50%, transparent)';

// Detector de utilitário de radius. É a peça que "quebra se alguém puser rounded-md".
function hasRoundedUtility(className: string): boolean {
  return /\brounded(?:-(?:sm|md|lg|xl|2xl|3xl|full|none))?\b/.test(className);
}

const GHOSTS = [
  { key: 'unificado', label: 'Unificado', href: '/x/unificado' },
  { key: 'pagar', label: 'Pagar', href: '/x/pagar' },
];

function renderTabs(activeKey: string) {
  const { container } = render(
    <PageHeaderTabs ghosts={GHOSTS} activeGhostKey={activeKey} />,
  );
  const active = container.querySelector<HTMLElement>('[role="tab"][aria-selected="true"]');
  const inactive = container.querySelector<HTMLElement>('[role="tab"][aria-selected="false"]');
  if (!active) throw new Error('aba ativa não renderizou (role=tab aria-selected=true)');
  return { active, inactive, container };
}

// ── CONTROLE-NEGATIVO do detector de radius (ADR 0258) ──────────────────────
describe('hasRoundedUtility — sensibilidade + especificidade (não vacuoso)', () => {
  it('SENSIBILIDADE: detecta rounded-md (o bug que o [W] pegou)', () => {
    expect(hasRoundedUtility('px-3 py-1.5 rounded-md text-sm')).toBe(true);
  });
  it('SENSIBILIDADE: detecta rounded-lg / rounded (qualquer step)', () => {
    expect(hasRoundedUtility('rounded-lg')).toBe(true);
    expect(hasRoundedUtility('rounded')).toBe(true);
    expect(hasRoundedUtility('rounded-full')).toBe(true);
  });
  it('ESPECIFICIDADE: className RETO (sem radius) NÃO casa', () => {
    expect(hasRoundedUtility('px-3 py-1.5 text-sm -mb-px border-b-2 font-semibold')).toBe(false);
  });
});

// ── FIDELIDADE da aba ATIVA vs .cli-moduletopnav-tab.active ─────────────────
describe('PageHeaderTabs — aba ativa fiel ao protótipo (não regride)', () => {
  it('border-radius: 0 — a aba ativa é RETA (quebra se puser rounded-md)', () => {
    const { active } = renderTabs('unificado');
    expect(hasRoundedUtility(active.className)).toBe(false);
  });

  it('border-bottom-color: var(--accent) — underline roxo 295 inline', () => {
    const { active } = renderTabs('unificado');
    expect(active.style.borderBottomColor).toBe(ACCENT);
  });

  it('background: color-mix(--accent-soft 50%) — pill roxo suave dark-aware inline', () => {
    const { active } = renderTabs('unificado');
    expect(active.style.backgroundColor).toBe(PILL_BG);
  });

  it('font-weight: 600 — aba ativa em font-semibold', () => {
    const { active } = renderTabs('unificado');
    expect(active.className).toMatch(/\bfont-semibold\b/);
  });

  // Especificidade (não vacuoso): a aba INATIVA NÃO carrega o accent inline.
  it('aba inativa NÃO tem o underline/pill accent (só a ativa)', () => {
    const { inactive } = renderTabs('unificado');
    expect(inactive).not.toBeNull();
    expect(inactive!.style.borderBottomColor).toBe('');
    expect(inactive!.style.backgroundColor).toBe('');
    expect(hasRoundedUtility(inactive!.className)).toBe(false);
  });
});
