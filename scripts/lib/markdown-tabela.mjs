/**
 * markdown-tabela.mjs — dono ÚNICO de "fatiar uma linha de tabela markdown em células".
 *
 * Nasceu em `scripts/design/lote-resumo-ci.mjs` (2026-09-18) e foi movido para cá em
 * 2026-09-28 quando o 2º parser do repo caiu no mesmo defeito: `parsePartes`
 * (`scripts/design/gerar-contrato.mjs`, reusado pelo `gerar-map.mjs`) fazia `.split('|')` cru,
 * e em `memory/requisitos/Compras/compras-grade-matrix-gap.md` o pipe escapado de um code-span
 * deslocou a coluna Ação para a vizinha. Duas cópias da mesma regra divergem por construção
 * (§5 2026-09-21), então os dois consumidores importam daqui — `lote-resumo-ci.mjs` roda
 * `main()` ao ser importado e não serve de biblioteca.
 */

/**
 * Fatia uma linha da tabela em células.
 * NÃO use split por `|` cru num parser de markdown: a célula Motivo carrega pipe ESCAPADO
 * (`\|`) — medido em 2026-09-18: 148 linhas do RESUMO tinham — e um `awk -F'|'` fatiou o
 * campo e deslocou a classificação inteira. O erro só apareceu porque a SOMA não fechou
 * (66 num total de 64), que é o controle barato desta função.
 *
 * Pressupõe a linha com `|` de abertura E de fechamento (o `slice(1, -1)`): uma linha sem o
 * `|` final perde a última célula. Medido em 2026-09-28: 0 de 1044 linhas de tabela nos
 * `*-gap.md` versionados vêm sem fechamento.
 */
export function celulas(linha) {
  const bruto = linha.split('|').slice(1, -1);
  const out = [];
  for (const parte of bruto) {
    if (out.length && out[out.length - 1].endsWith('\\')) {
      out[out.length - 1] = out[out.length - 1].slice(0, -1) + '|' + parte;
    } else {
      out.push(parte);
    }
  }
  return out.map((s) => s.trim());
}
