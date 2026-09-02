// Chip de filtro do Fiscal, composto sobre a primitiva <Button> do DS.
//
// POR QUE EXISTE (e por que NÃO é componente novo do DS)
// ------------------------------------------------------
// A Onda 1 tira a classe hand-rolled `fx-chip` (fiscal-cockpit.css) das 7 telas do
// Fiscal. O papel dela é "pill de filtro SINGLE-SELECT" — clicar troca o status da
// lista, só um ativo por vez. Antes de compor, os donos do tema foram consultados
// (`prototipo-ui/REGISTRY_DS_COMPONENTES.md`), e nenhum cobre este papel:
//
//   · `PageFilters` / `FilterChip` (@/Components/shared) — chip de filtro JÁ APLICADO,
//     com botão X pra remover, dentro de um container colapsável de campos. Papel
//     diferente: lá o chip REPRESENTA um filtro ativo; aqui ele SELECIONA.
//   · `SubNav` (@/Components/shared) — troca de SEÇÃO in-page (value/onChange). O
//     registry marca a onda de migração dele como fora do escopo desta. Também não é
//     o caso: os chips não trocam de seção, filtram a mesma lista.
//   · `Segmented` — toggle de 2–3 opções mutuamente exclusivas; os filtros do Fiscal
//     chegam a 5 e carregam contador.
//
// Logo isto é COMPOSIÇÃO de primitiva (o que o DS pede), não componente novo — que
// seria soberania [W] (proibicoes.md §"token/componente novo do DS").
//
// Mora em `_lib/` (dir auxiliar, fora do escopo de trio-de-tela) ao lado de
// `fiscal-helpers.ts`, seguindo o padrão que o módulo já usa.
//
// Refs: ADR 0235 (acento roxo) · ADR UI-0013 · eslint `ds/no-raw-palette-color`.

import { cn } from '@/Lib/utils';

/** Tom semântico do chip. `undefined` = neutro (usa o acento primário quando ativo). */
export type ChipTone = 'danger' | 'warn';

/**
 * Props de `<Button>` que reproduzem a caixa do antigo `.fx-chip`.
 *
 * `size="xs"` (h-6 · text-xs) + `rounded-full` dá a mesma pílula. O tom sai SEMPRE de
 * token semântico (`primary` / `destructive` / `warning`) — nunca de paleta crua do
 * Tailwind, que a regra `ds/no-raw-palette-color` proíbe nas telas.
 *
 * O tom "warn" usa `variant="outline"` + classes de token em vez de uma variante
 * própria: o `Button` do DS não tem variante `warning`, e criar uma é decisão [W].
 */
export function chipProps(active: boolean, tone?: ChipTone) {
  const size = 'xs' as const;

  if (!active) {
    return {
      variant: 'outline' as const,
      size,
      className: cn(
        'rounded-full font-medium',
        tone === 'danger' ? 'text-destructive-fg border-destructive/30'
          : tone === 'warn' ? 'text-warning-fg border-warning/30'
            : 'text-muted-foreground',
      ),
    };
  }

  if (tone === 'danger') {
    return { variant: 'destructive' as const, size, className: 'rounded-full font-medium' };
  }
  if (tone === 'warn') {
    return {
      variant: 'outline' as const,
      size,
      className: 'rounded-full font-medium bg-warning text-white border-warning hover:bg-warning/90',
    };
  }
  return { variant: 'default' as const, size, className: 'rounded-full font-medium' };
}

/**
 * Classe do contador dentro do chip — era `.fx-chip span`, que hard-codava
 * `rgba(0,0,0,.08)` / `rgba(255,255,255,.25)` no CSS.
 */
export function chipCount(active: boolean) {
  return cn(
    'ml-0.5 rounded-full px-1.5 text-[10px] font-semibold',
    active ? 'bg-white/25' : 'bg-foreground/10',
  );
}
