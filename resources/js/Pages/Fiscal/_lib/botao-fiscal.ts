// Mapa `fx-btn <kind>` → props da primitiva <Button> do DS.
//
// POR QUE EXISTE
// --------------
// A Onda 1 tira a classe hand-rolled `fx-btn` (fiscal-cockpit.css) das telas e dos
// drawers do Fiscal. A troca é quase toda mecânica, MAS dois sítios escolhem a
// variante em RUNTIME — `NotaDrawer.tsx` e `NotaDrawerV2.tsx` fazem
// `className={`fx-btn ${recipe.primary.kind}`}`, onde o `kind` vem da receita SEFAZ.
// Sem um mapa, esses dois exigiriam um ternário inline em cada um, e o mapa
// `kind → variante` passaria a existir em duplicata. Aqui ele tem um dono só.
//
// O tom sai SEMPRE de token semântico — nunca de paleta crua do Tailwind, que a
// regra `ds/no-raw-palette-color` (eslint.config.js) proíbe nas telas.
//
// Mora em `_lib/` (dir auxiliar, fora do escopo de trio-de-tela) ao lado de
// `fiscal-helpers.ts` e `chip-filtro.ts`, seguindo o padrão do módulo.

/** Os tons que a classe `fx-btn` aceitava. `default` = o `fx-btn` sem sufixo. */
export type BotaoKind = 'default' | 'ghost' | 'primary' | 'danger' | 'warn';

/**
 * Props de `<Button>` equivalentes a `fx-btn <kind>`.
 *
 * `ghost` e `primary` mapeiam nas variantes Cowork canon (ADR UI-0015), que é a
 * convenção já usada em 5 telas do repo e reproduz a caixa do `fx-btn` (h-30px,
 * radius 5px, 12.5px).
 *
 * `danger` e `warn` NÃO usam variante Cowork: as classes `.cw-btn-*` moram
 * UNLAYERED e vencem as utilitárias Tailwind, que são layered — armadilha
 * documentada em `cowork-fields.css:168-176`. Combinar as duas faria a cor ser
 * silenciosamente ignorada. Por isso esses dois são `destructive`/`outline` +
 * `size="cowork"`, todos layered, que compõem.
 *
 * Não existe variante `warning` no Button do DS, e criar uma é soberania [W]
 * (proibicoes.md §"token/componente novo do DS") — daí o `outline` + tokens.
 */
export function btnProps(kind: BotaoKind = 'default') {
  switch (kind) {
    case 'ghost':
      return { variant: 'cowork-ghost' as const };
    case 'primary':
      return { variant: 'cowork-primary' as const };
    case 'danger':
      return { variant: 'destructive' as const, size: 'cowork' as const };
    case 'warn':
      return {
        variant: 'outline' as const,
        size: 'cowork' as const,
        className: 'border-warning bg-warning text-white hover:bg-warning/90',
      };
    default:
      // `fx-btn` sem sufixo era sólido neutro (`background: var(--fx-text)`),
      // não o acento — por isso `secondary`, e não `default` (que é o primary roxo).
      return { variant: 'secondary' as const, size: 'cowork' as const };
  }
}
