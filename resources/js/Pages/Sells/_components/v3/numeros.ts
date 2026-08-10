// Dinheiro e número pt-BR da Venda V3 — funções PURAS, sem React.
//
// POR QUE ARQUIVO SEPARADO
// Estas funções moravam no `primitivos.tsx` junto dos componentes. Misturar
// helper com componente quebra o Fast Refresh do Vite (`react-refresh/
// only-export-components`): editar uma função forçava remount da árvore toda.
// Módulo sem JSX resolve, e de quebra deixa o guard testável sem montar React.
//
// POR QUE SÃO LOCAIS (e não em @/Lib)
// A regra dura da V3 é não tocar em nada que `Sells/Create.tsx` consome — a tela
// que a ROTA LIVRE opera. Utilitário compartilhado editado vazaria pra ela pelo
// import. A variação nasce aqui; o original fica intacto.

/**
 * Lê número digitado em pt-BR (`1.234,56`) sem confundir milhar com decimal.
 *
 * Portado LITERAL do handoff (§9). Não é utilitário genérico: é o guard do
 * incidente de 2026-06-05, em que `Util::num_uf` leu o ponto decimal como
 * separador de milhar e inflou `final_total` em ~×100.000 em 16 vendas do biz=4.
 * Separador de milhar tem SEMPRE 3 dígitos — é isso que o lookahead exige, e é
 * por isso que `204.99605` NÃO vira `20499605`.
 */
export const parseBR = (s: string | number): number => {
  if (typeof s === 'number') return s;
  const t = String(s ?? '')
    .replace(/\.(?=\d{3}(\D|$))/g, '')
    .replace(',', '.');
  const v = parseFloat(t);
  return isNaN(v) ? 0 : v;
};

/** O que o front PODE mandar: 2 casas, sem ambiguidade de locale (handoff §9). */
export const submitSafe = (n: number): number =>
  Math.round((Number(n) + Number.EPSILON) * 100) / 100;

export const fmtBR = (n: number): string =>
  Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const brl = (n: number | null | undefined): string =>
  n || n === 0 ? `R$ ${fmtBR(Number(n))}` : '—';

export const num = (n: number, d = 2): string =>
  Number(n).toLocaleString('pt-BR', { minimumFractionDigits: d, maximumFractionDigits: d });
