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

// ── Pill do CONTADOR (`badge`) — autoridade = TabBar do DS ──────────────────
// O `badge` é opt-in e NENHUM teste o cobria até 2026-09-04: os ghosts acima não
// declaram contador, então as duas pernas do pill passavam despercebidas.
//
// Autoridade do token = componente `TabBar` do DS (`components/TabBar/TabBar.jsx`,
// ramo `t.count != null`): ativo `--accent`/`--accent-fg`, inativo `--bg-2`/`--text-dim`.
// Divergência conhecida e resolvida a favor do DS: o protótipo de TELA
// `.cli-moduletopnav-n` (clientes-page.css:114) escreve `--border-2` no fundo inativo.
// Os dois arquivos foram lidos em 2026-09-04; o componente do DS manda sobre o CSS
// de uma tela. Se o DS mudar, estas constantes mudam CONSCIENTEMENTE (como as de cima).
//
// Resolução medida em browser real (harness com os `_generated-cockpit-{light,dark}.css`,
// controle positivo `--accent` + controle negativo de token inexistente):
//   --bg-2      light oklch(0.965 0.004 90)  · dark oklch(0.23 0.006 240)
//   --border-2  light oklch(0.93 0.004 90)   · dark oklch(0.31 0.008 240)
// Trocam com o tema E diferem entre si nos dois — a troca não é inerte.
const BADGE_ATIVO = { bg: 'var(--accent)', fg: 'var(--accent-fg)' };
const BADGE_INATIVO = { bg: 'var(--bg-2)', fg: 'var(--text-dim)' };

const GHOSTS_COM_BADGE = [
  { key: 'unificado', label: 'Unificado', href: '/x/unificado', badge: 7 },
  { key: 'pagar', label: 'Pagar', href: '/x/pagar', badge: 3 },
];

function renderPills(activeKey: string) {
  const { container } = render(
    <PageHeaderTabs ghosts={GHOSTS_COM_BADGE} activeGhostKey={activeKey} />,
  );
  const pill = (selecionada: 'true' | 'false') =>
    container.querySelector<HTMLElement>(
      `[role="tab"][aria-selected="${selecionada}"] span.rounded-full`,
    );
  return { ativo: pill('true'), inativo: pill('false') };
}

describe('PageHeaderTabs — pill do contador segue o TabBar do DS', () => {
  it('o pill RENDERIZA quando o ghost declara badge (senão o resto é vacuoso)', () => {
    const { ativo, inativo } = renderPills('unificado');
    expect(ativo).not.toBeNull();
    expect(inativo).not.toBeNull();
    expect(ativo!.textContent).toBe('7');
    expect(inativo!.textContent).toBe('3');
  });

  it('INATIVO: fundo `--bg-2` + texto `--text-dim` (DS, não o `--border-2` da tela)', () => {
    const { inativo } = renderPills('unificado');
    expect(inativo!.style.backgroundColor).toBe(BADGE_INATIVO.bg);
    expect(inativo!.style.color).toBe(BADGE_INATIVO.fg);
  });

  it('ATIVO: fundo `--accent` + texto `--accent-fg` (vizinhança intacta)', () => {
    const { ativo } = renderPills('unificado');
    expect(ativo!.style.backgroundColor).toBe(BADGE_ATIVO.bg);
    expect(ativo!.style.color).toBe(BADGE_ATIVO.fg);
  });

  // Não-vacuoso: os dois ramos têm de DIFERIR — impede colapsar num token só.
  it('ESPECIFICIDADE: ativo e inativo não compartilham o mesmo fundo', () => {
    const { ativo, inativo } = renderPills('unificado');
    expect(ativo!.style.backgroundColor).not.toBe(inativo!.style.backgroundColor);
  });

  // Sem `badge` declarado, nada renderiza — o opt-in continua opt-in.
  it('ghost SEM badge não renderiza pill nenhum', () => {
    const { container } = render(
      <PageHeaderTabs ghosts={GHOSTS} activeGhostKey="unificado" />,
    );
    expect(container.querySelector('[role="tab"] span.rounded-full')).toBeNull();
  });
});
