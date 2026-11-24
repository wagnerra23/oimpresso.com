// Fila de vendas de uma mesma devolução (pedido [W] 2026-11-24: várias vendas do mesmo
// cliente numa devolução só). O núcleo grava uma devolução por venda, pelo store() de
// sempre; a fila só diz ao registro (SellReturn/Add) qual venda abrir depois de salvar.
// Não carrega valor nenhum: só ids, que o servidor escopa pelo business da sessão.

/** Endereço do registro: a primeira venda abre, as demais seguem em `?fila=`. */
export function urlDoRegistro(ids: number[]): string {
  const [primeira, ...resto] = ids;
  return resto.length ? `/sell-return/add/${primeira}?fila=${resto.join(',')}` : `/sell-return/add/${primeira}`;
}

/** Ids que ainda faltam, lidos do `?fila=` da URL. Ignora o que não é inteiro positivo. */
export function lerFila(search: string): number[] {
  const valor = new URLSearchParams(search).get('fila') ?? '';
  return valor
    .split(',')
    .map((p) => Number(p.trim()))
    .filter((n) => Number.isInteger(n) && n > 0);
}
