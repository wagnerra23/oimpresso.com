// ForjaTabBar — porte do `TabBar` do Design System do Cowork
// (`components/TabBar/TabBar.jsx`, bundle de 2026-08-24 em
// `scripts/design-sync/mirror-snapshot/_ds_bundle.js`). É o que o protótipo
// renderiza nas abas do Integrador, através do adaptador `window.CliTabs`
// (`prototipo-ui/cowork/cli-tabs.jsx`).
//
// POR QUE UM PORTE, e não `PageHeaderTabs`: a régua é a sonda, não o nome do
// componente (PARIDADE §11, Onda 10). Medido, o `PageHeaderTabs` não tem como
// bater — ele é `<div role="tablist">` com `<a href>` (navegação Inertia entre
// ROTAS), `text-sm` (14px) e `px-3 py-1.5`; o alvo é `<nav>` com `<button>` de
// estado LOCAL, 13px e `0 14px`. Forçá-lo pediria href falso mais três
// overrides de tipografia/espaço — seria mais desvio, não menos.
//
// Os valores que a sonda pinou no protótipo em 2026-09-02 vivem no comentário do
// PR 6563 (JSON bruto) e no dono do inventário desta tela,
// `memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md`. Não os repito
// aqui: doc não restateia número que outro sistema sabe melhor — aponta pro dono
// (§5 2026-07-17). O que ESTE arquivo fixa são os tokens de onde eles saem, que
// é o que o código abaixo usa — nunca literal de cor:
//   · font-size 13px · padding `0 14px` · altura 36px
//   · texto ativo   `var(--text)`
//   · underline 2px `var(--accent)`  (0,70 no dark, escopado à Forja pela Onda 2.1)
//   · fundo ativo   `color-mix(in oklch, var(--accent-soft) 50%, transparent)`
//   · contador      `var(--bg-2)`/`var(--text-dim)`, e `var(--accent)`/`var(--accent-fg)` na ativa
//
// POR QUE NÃO O `SubNav`, que a regra `ds/no-inline-tablist` aponta como dono do
// "switch in-page controlado": medido, ele diverge em 9 das 12 propriedades do
// alvo — `text-sm` (14px) contra 13px, `px-3 py-2` contra `0 14px`, `gap-0.5`
// contra 0, sem fundo na aba ativa, peso 500 contra 600, contador via `<Badge>`.
// E o underline dele é `border-primary/70`: o próprio `PageHeaderTabs` já
// registra que `--primary` resolve num roxo mais claro (L 0,70) e destoa do accent,
// e por isso o próprio header canon deixou de usar `border-b-primary`. Alinhá-lo
// exigiria sobrescrever quase toda classe — isso não é usar o componente do DS,
// é lutar com ele. A regra continua valendo pro caso geral; aqui a âncora é o
// prototípo (ADR 0388 D-1) e a régua é a sonda.
//
// ARIA segue o DS à risca: `<nav aria-label>` + `aria-current="page"` na ativa.
// A primeira versão deste porte carregou o `role="tablist"`/`role="tab"` que o
// código antigo tinha num `<div>`, e o lint pegou: sobrescrever o papel implícito
// de um `<nav>` com papel interativo é violação
// (`jsx-a11y/no-noninteractive-element-to-interactive-role`). O padrão tab também
// estava incompleto dos dois lados — nunca houve `tabpanel` nem `aria-controls`.
//
// O hover do DS é gravado no DOM por `onMouseEnter`/`onMouseLeave`; aqui é
// estado React. O computed é o mesmo, sem o resíduo de estilo inline que sobra
// no DS quando a aba ativa muda com o ponteiro em cima de outra.

import { useState, type CSSProperties } from 'react';

export type ForjaTab<K extends string = string> = {
  key: K;
  label: string;
  /** Contador à direita do rótulo (pílula mono). Ausente = sem pílula. */
  count?: number;
};

type Props<K extends string> = {
  tabs: ForjaTab<K>[];
  active: K;
  onChange: (key: K) => void;
  /** Rótulo do `<nav>`. O DS fixa "Sub-navegação"; o chamador pode ser específico. */
  ariaLabel?: string;
};

// ── estilos: cópia 1:1 do inline do TabBar do DS ──────────────────────────────
const NAV: CSSProperties = {
  display: 'flex',
  alignItems: 'center',
  gap: 0,
  borderBottom: '1px solid var(--border)',
  overflowX: 'auto',
};

function botao(on: boolean, hover: boolean): CSSProperties {
  return {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 6,
    padding: '0 14px',
    height: 36,
    border: 0,
    borderBottom: '2px solid ' + (on ? 'var(--accent)' : 'transparent'),
    marginBottom: -1,
    background: on
      ? 'color-mix(in oklch, var(--accent-soft) 50%, transparent)'
      : hover
        ? 'color-mix(in oklch, var(--border-2) 60%, transparent)'
        : 'transparent',
    color: on || hover ? 'var(--text)' : 'var(--text-dim)',
    font: (on ? '600' : '500') + ' 13px/1 var(--font-sans)',
    cursor: 'pointer',
    whiteSpace: 'nowrap',
    transition: 'color .15s, background .15s, border-color .15s',
  };
}

function contador(on: boolean): CSSProperties {
  return {
    font: '600 10.5px/1.4 var(--font-mono)',
    padding: '0 6px',
    minWidth: 18,
    textAlign: 'center',
    borderRadius: 99,
    background: on ? 'var(--accent)' : 'var(--bg-2)',
    color: on ? 'var(--accent-fg)' : 'var(--text-dim)',
  };
}

export default function ForjaTabBar<K extends string>({
  tabs,
  active,
  onChange,
  ariaLabel = 'Sub-navegação',
}: Props<K>) {
  const [hover, setHover] = useState<K | null>(null);

  return (
    <nav aria-label={ariaLabel} style={NAV}>
      {tabs.map((t) => {
        const on = t.key === active;
        return (
          <button
            key={t.key}
            type="button"
            aria-current={on ? 'page' : undefined}
            onClick={() => onChange(t.key)}
            onMouseEnter={() => setHover(t.key)}
            onMouseLeave={() => setHover((h) => (h === t.key ? null : h))}
            style={botao(on, !on && hover === t.key)}
          >
            {t.label}
            {t.count != null && <span style={contador(on)}>{t.count}</span>}
          </button>
        );
      })}
    </nav>
  );
}
