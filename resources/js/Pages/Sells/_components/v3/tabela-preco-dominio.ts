// Tabela de preço na Venda V3 gravando (grupo 3b · UC-V397..V399 · REGRA MESTRE de valor).
//
// A regra é a do `Sells/Create.tsx`, sem nada inventado:
// - o preço de uma linha vinda de `/products/list` é `precoDaBusca` — `variation_group_price`
//   quando a busca levou `price_group` e a variação tem preço no grupo, senão `selling_price`
//   (cópia local de `Sells/_components/precoDaBusca.ts`: a V3 não importa `Sells/_components`,
//   UC-V303);
// - trocar a tabela refaz a busca de cada linha com o grupo novo e troca o preço; a linha que a
//   busca não devolve fica com o preço que tinha (`handlePriceGroupChange`);
// - escolher cliente com tabela aplica a tabela dele; cliente sem tabela mantém a atual
//   (`handleCustomerSelect`).
// O store de `/pos` só registra o grupo (`transactions.selling_price_group_id`): o preço que
// grava é o `unit_price` de cada linha, que sai daqui.

export type TabelaDePreco = { id: number; nome: string };

/** Linha de `/products/list` (ProductUtil::filterProduct), os dois campos de preço. */
export type PrecoDaBusca = {
  selling_price?: number | string | null;
  variation_group_price?: number | string | null;
};

export function precoDaBusca(linha: PrecoDaBusca, fallback = 0): number {
  if (linha.variation_group_price !== undefined && linha.variation_group_price !== null) {
    return Number(linha.variation_group_price);
  }
  return Number(linha.selling_price ?? fallback);
}

/** Rótulo da tabela: o nome da lista; 0 ou nenhuma = preço padrão; fora da lista = pelo id. */
export function nomeDaTabela(tabelas: TabelaDePreco[], id: number | null): string {
  const achada = tabelas.find((t) => t.id === (id ?? 0));
  if (achada) return achada.nome;
  return !id ? 'Preço padrão' : `Tabela #${id}`;
}

/** A tabela depois de escolher um cliente: a dele, ou a que já estava (Create). */
export function tabelaAposCliente(atual: number | null, grupoDoCliente: number | null): number | null {
  return grupoDoCliente !== null ? grupoDoCliente : atual;
}

/** Parâmetros da busca de uma linha, com o grupo só quando há um (Create: `if (newGroupId)`). */
export function parametrosDaBusca(termo: string, locationId: number, tabelaId: number | null): URLSearchParams {
  const q = new URLSearchParams({ term: termo, location_id: String(locationId) });
  if (tabelaId) q.set('price_group', String(tabelaId));
  return q;
}

export type LinhaReprecificavel = { variationId: number | null; preco: string };

/**
 * Aplica os preços novos: `novos` é variationId → preço (número). Linha sem variação ou sem
 * resultado fica como estava, e conta em `mantidas` para a tela avisar.
 */
export function aplicarPrecos<T extends LinhaReprecificavel>(
  itens: T[],
  novos: Map<number, number>,
  formatar: (n: number) => string,
): { itens: T[]; mantidas: number } {
  let mantidas = 0;
  const saida = itens.map((l) => {
    const preco = l.variationId ? novos.get(l.variationId) : undefined;
    if (preco === undefined || !Number.isFinite(preco)) {
      mantidas++;
      return l;
    }
    return { ...l, preco: formatar(preco) };
  });
  return { itens: saida, mantidas };
}
