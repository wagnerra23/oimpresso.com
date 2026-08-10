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

/* ── FORMATAÇÃO: delega ao canon, não recria ───────────────────────────────
   A primeira versão daqui reimplementava `toLocaleString('pt-BR', …)`, e o
   `reuse:duplicates` acusou com razão: `formatDecimalPtBR` já é o formatador
   canônico do projeto. Reusar é seguro porque formatação é SAÍDA — ela não
   decide valor.

   O que NÃO delega, e é deliberado: `parseBR` e `submitSafe` acima. Eles são a
   ENTRADA (texto do usuário → número) e vieram literais do handoff §9 como o
   guard do incidente `num_uf`. Trocar parser de valor é território `[V0]`
   (REGRA MESTRE, `memory/proibicoes.md`) — exige provar equivalência por dois
   caminhos e aprovação, não é higiene de duplicata. */
export { formatDecimalPtBR as num } from '@/Lib/numberPtBR';

import { formatDecimalPtBR } from '@/Lib/numberPtBR';

/** Atalho de 2 casas — a precisão default do canon, nomeada aqui por leitura. */
export const fmtBR = (n: number): string => formatDecimalPtBR(n, 2);

/** `brl` é local: o fallback `—` para vazio é decisão desta tela, não do canon. */
export const brl = (n: number | null | undefined): string =>
  n || n === 0 ? `R$ ${fmtBR(Number(n))}` : '—';

/**
 * Formata QUANTIDADE — 2 casas, exceto quando 2 casas mentiriam.
 *
 * Dinheiro tem 2 casas sempre; medida não. Uma tira de 0,50 × 0,004 m dá
 * 0,002 m², e `fmtBR` exibe isso como `0,00` — a tela ficaria mostrando uma
 * quantidade zerada ao lado de um total cobrado, que é aritmética visivelmente
 * falsa pra quem opera e o tipo de coisa que vira chamado de "a venda está
 * errada". Medido no harness antes de existir esta função.
 *
 * Regra: 2 casas por padrão (não mexe em nada do caso normal — 0,05 segue
 * `0,05`, 12,5 segue `12,50`); expande até 4 SÓ quando o valor não é zero mas
 * arredondaria pra zero. Exibir mais casas é decisão de LEITURA — o cálculo
 * continua sendo `quantidadeFaturada`, e não muda por causa disto.
 */
export const fmtQtd = (n: number): string => {
  const v = Number(n);
  if (v !== 0 && Math.abs(v) < 0.005) return formatDecimalPtBR(v, 4);
  return fmtBR(v);
};
