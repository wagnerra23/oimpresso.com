// Filtros da Fabricação — uma definição só para as abas que têm filtro (Ordens e Relatório).
//
// Antes cada aba montava os seus: Ordens com as medidas do protótipo e Relatório com as classes
// antigas do bundle (data 140×32, canto 6, texto 12,5; rótulo 10px sem peso). No protótipo as duas
// usam o MESMO `Campo` + `DatePicker` (`manufacturing-producao.jsx`), então a diferença era defeito,
// visto em produção por [M] em 2026-10-06. Daqui as duas abas leem a mesma coisa.

/**
 * Medidas dos filtros do `MfgProducaoView` (protótipo medido em 2026-10-06, 1600 px):
 * rótulo 10,5px/600 em caixa alta, tracking .04em; campo de data 150 de largura, canto 8, texto
 * 13,5px, fundo `--surface`.
 * Altura 34 e não os 36 do `DatePicker` do DS: no próprio DS o `Input`, o `select` e o `SearchInput`
 * têm 34, e o "Local" ao lado das datas ficava 2px mais baixo na mesma linha. [M] 2026-10-06: na
 * mesma família, a mesma altura.
 * O canto é `rounded-[8px]` e não `rounded-lg`: neste projeto o `rounded-lg` vale 12px (medido em
 * produção em 2026-10-05).
 * O rótulo usa `--text-dim` e não o `--text-mute` do DS: texto pequeno em `--text-mute` reprova AA
 * (ADR 0410). O campo usa o `Input` na variante `shadcn`: a `cowork` passa pelo `.cw-input`, que é
 * CSS fora de camada e vence a altura/largura/texto das classes (medido: `h-9 w-[150px]` saía 133×30).
 */
export const ROTULO_CAMPO = 'text-[10.5px] font-semibold uppercase tracking-[0.04em] text-[var(--text-dim)]';

export const CAMPO_DATA =
  'h-[34px] w-[150px] rounded-[8px] border-[var(--border)] bg-[var(--surface)] px-2.5 text-[13.5px] md:text-[13.5px] text-foreground dark:bg-[var(--surface)]';

/** Rótulo do "Só finalizadas" — o `label` do `Checkbox` do protótipo: 12,5px, peso 500, cor do texto. */
export const ROTULO_CHECKBOX = 'text-[12.5px] font-medium text-[var(--text)]';

/**
 * `aaaa-mm-dd` com ano ≥ 2000. O `<input type="date">` emite `0002-09-30`, `0020-…`, `0202-…`
 * enquanto o ano é digitado — sem este guard cada tecla viraria um request.
 */
export function isDataCompleta(value: string): boolean {
  const m = /^(\d{4})-\d{2}-\d{2}$/.exec(value);
  return !!m && Number(m[1]) >= 2000;
}

/**
 * D-MFG-DATA ([W] 2026-09-25): o intervalo aplica AO ESCOLHER, sem blur e sem botão. Só vale quando
 * o intervalo fica válido: os dois vazios (limpa) ou as duas datas completas. Um só preenchido não
 * aplica. Devolve o intervalo a aplicar, ou `null` quando ainda não há o que aplicar.
 */
export function intervaloAplicavel(de: string, ate: string): { de: string; ate: string } | null {
  if (!de && !ate) return { de: '', ate: '' };
  if (isDataCompleta(de) && isDataCompleta(ate)) return { de, ate };
  return null;
}
