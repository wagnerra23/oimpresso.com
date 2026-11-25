// Anatomia do Design System usada na tela Cadastros de apoio (tabela, botões, chips, aviso).
//
// Medido no protótipo servido (prototipo-ui/cowork/Wagner/produto-cadastros.jsx, dark, 1590px) em
// 2026-11-25 contra a produção em /units?aba=variacoes. A produção usava a tabela e os botões crus do
// shadcn: cabeçalho 12px sem caixa alta, células 14px, colunas sem largura, valores em caixinha
// quadrada, "Excluir" igual ao "Editar" e o "Nova variação" sem ícone. As medidas do DS:
//   - DataTable (DataGrid `comfortable`, sem listra): th 600 10px/1.2 caixa alta .05em, pad 7×10,
//     fundo --bg-2, borda --border embaixo · td 12,5px, pad 9×10, borda --border-2 embaixo.
//   - Button `sm`: 26px, 12px, pad 0 10px, raio 8 · `default`: 30px, 12,5px, pad 0 12px.
//     ghost = --surface + --border + --text-dim · danger = texto/borda em --color-destructive-fg.
//   - TagChip neutro: pílula, borda --border, fundo --color-secondary, 10,5px 500, minúsculo.
//   - Alert: fundo 6% e borda 22% do tom, ícone em caixa 22px a 14%, título 13px 600, texto 12,5px.
//
// Texto pequeno sai em --text-dim e não em --text-mute (ADR 0410: --text-mute reprova AA em
// 10-11px) — a mesma troca da tabela compartilhada (Components/shared/DataTable, densidade `grid`).
//
// Os tokens só existem dentro do `.cockpit`: drawer e janela montam lá (prop `container`).

export const TH =
  'border-b border-[var(--border)] bg-[var(--bg-2)] px-2.5 py-[7px] text-left text-[10px] leading-[1.2] font-semibold uppercase tracking-[.05em] whitespace-nowrap text-[var(--text-dim)]';
export const TD = 'border-b border-[var(--border-2)] px-2.5 py-[9px] align-middle text-[12.5px] text-[var(--text)]';

/** Célula `{ primary, sub }` do DataGrid. */
export const PRIMARIO = 'block text-[12.5px] font-semibold tracking-[-0.006em] text-[var(--text)]';
export const SUB = 'block text-[11px] text-[var(--text-dim)]';
export const MONO = 'font-mono text-[12px] tracking-[-0.01em]';

const BOTAO_BASE = 'rounded-[8px] border shadow-none font-medium';
/** Button `sm` do DS: 26px. */
export const SM = `${BOTAO_BASE} h-[26px] gap-1.5 px-2.5 py-0 text-[12px]`;
/** Button `default` do DS: 30px. */
export const MD = `${BOTAO_BASE} h-[30px] gap-1.5 px-3 py-0 text-[12.5px]`;
export const GHOST =
  'border-[var(--border)] bg-[var(--surface)] text-[var(--text-dim)] hover:border-[var(--text-mute)] hover:bg-[var(--bg-2)] hover:text-[var(--text)] dark:border-[var(--border)] dark:bg-[var(--surface)] dark:hover:bg-[var(--bg-2)]';
export const DANGER =
  'border-[color-mix(in_oklch,var(--color-destructive-fg)_30%,transparent)] bg-[var(--surface)] text-[var(--color-destructive-fg)] hover:bg-[var(--color-destructive-soft)] hover:text-[var(--color-destructive-fg)] dark:border-[color-mix(in_oklch,var(--color-destructive-fg)_30%,transparent)] dark:bg-[var(--surface)] dark:hover:bg-[var(--color-destructive-soft)]';
/** Primário: fundo e hover ficam com o Button (`--color-primary`). */
export const PRIMARIO_BTN = 'border-transparent font-semibold';

export const CHIP =
  'inline-flex items-center rounded-full border border-[var(--border)] bg-secondary px-2 py-px text-[10.5px] leading-[1.6] font-medium lowercase tracking-[-0.01em] text-secondary-foreground';

/** Texto de apoio (`.pb-help`): 11px. */
export const AJUDA = 'text-[11px] text-[var(--text-dim)]';

/** Rótulo de campo do Input do DS: 10,5px 600 caixa alta .04em. */
export const ROTULO = 'text-[10.5px] font-semibold uppercase tracking-[.04em] text-[var(--text-dim)]';
/** Input do DS: 34px, 13px, raio 8, fundo --surface. */
export const CAMPO = 'h-[34px] rounded-[8px] border-[var(--border)] bg-[var(--surface)] px-2.5 text-[13px] shadow-none dark:bg-[var(--surface)]';
