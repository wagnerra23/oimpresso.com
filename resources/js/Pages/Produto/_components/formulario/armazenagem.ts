// Locais de armazenagem do produto (CU-PROD-20): em quais locais de estoque ele fica, o endereço
// dentro de cada um (prateleira, fileira, posição) e qual é o local padrão.
//
// O servidor guarda o endereço no `product_racks` (uma linha por local) e o padrão em
// `products.local_padrao_id`. O envio usa os mesmos campos do Blade: `product_racks` cria a linha
// do local e `product_racks_update` muda a que já existe. O cadastro não grava quantidade: o
// estoque entra pela tela de estoque inicial (decisão do #10544).
//
// No Editar, um local gravado só sai quando o produto não tem saldo nele ([M] 2026-10-22): vai em
// `product_racks_remover`, e o servidor confere o saldo de novo antes de apagar o endereço.

/** Quais partes do endereço a empresa usa (Configurações: enable_racks · enable_row · enable_position). */
export interface EnderecoNoEstoque {
  prateleira: boolean;
  fileira: boolean;
  posicao: boolean;
}

export interface LinhaArmazenagem {
  local: number;
  prateleira: string;
  fileira: string;
  posicao: string;
  /** A linha já está gravada: no envio vai em `product_racks_update`, e não sai pela tela. */
  salvo: boolean;
}

export interface EstadoArmazenagem {
  linhas: LinhaArmazenagem[];
  padrao: number | null;
  /** Editar: locais gravados que a pessoa tirou. Ausente enquanto nenhum saiu. */
  removidos?: number[];
}

/** Editar: o saldo do produto em cada local (id do local => quantidade). Local sem registro = 0. */
export type SaldoPorLocal = Record<string, number>;

/** O saldo de um local, ou 0 quando o produto nunca teve estoque nele. */
export function saldoNoLocal(saldos: SaldoPorLocal, local: number): number {
  return Number(saldos[String(local)] ?? 0);
}

/** Linha gravada só sai pela tela com o saldo conhecido e zerado (no Novo nenhuma linha é gravada). */
export function podeTirar(linha: LinhaArmazenagem, saldos?: SaldoPorLocal): boolean {
  if (!linha.salvo) return true;
  return saldos !== undefined && Math.abs(saldoNoLocal(saldos, linha.local)) < 0.00001;
}

type RackGravado = { location_id?: number | string; rack?: string | null; row?: string | null; position?: string | null };

/**
 * O estado inicial a partir do endereço que o servidor manda (`getRackDetails`, indexado pelo id do
 * local; vazio chega como `[]`). Sem nenhuma linha gravada, o Novo começa com `localInicial`, que vira
 * o padrão; o Editar começa vazio, com o padrão gravado.
 */
export function armazenagemInicial(
  gravado: Record<string, RackGravado> | RackGravado[] | null | undefined,
  { salvo, padrao, localInicial }: { salvo: boolean; padrao?: number | null; localInicial?: number | null },
): EstadoArmazenagem {
  const linhas = Object.entries(gravado ?? {}).map(([chave, r]): LinhaArmazenagem => ({
    local: Number(r.location_id ?? chave),
    prateleira: r.rack ?? '',
    fileira: r.row ?? '',
    posicao: r.position ?? '',
    salvo,
  }));
  if (linhas.length === 0 && localInicial) {
    return { linhas: [{ local: localInicial, prateleira: '', fileira: '', posicao: '', salvo: false }], padrao: localInicial };
  }
  const locais = linhas.map((l) => l.local);
  // No Editar o padrão é o gravado, mesmo nulo: a tela não escolhe um sozinha. No Novo (duplicar),
  // sem padrão informado, a primeira linha.
  if (padrao !== undefined) return { linhas, padrao: padrao != null && locais.includes(padrao) ? padrao : null };
  return { linhas, padrao: locais[0] ?? null };
}

/**
 * Tira uma linha. A não gravada sai sempre; a gravada só sem saldo, e entra em `removidos`. Se era o
 * padrão, o padrão passa para a primeira que sobrar.
 */
export function semLinha(e: EstadoArmazenagem, indice: number, saldos?: SaldoPorLocal): EstadoArmazenagem {
  const alvo = e.linhas[indice];
  if (!alvo || !podeTirar(alvo, saldos)) return e;
  const linhas = e.linhas.filter((_, i) => i !== indice);
  const padrao = e.padrao === alvo.local ? (linhas[0]?.local ?? null) : e.padrao;
  if (!alvo.salvo) return e.removidos ? { linhas, padrao, removidos: e.removidos } : { linhas, padrao };
  return { linhas, padrao, removidos: [...(e.removidos ?? []), alvo.local] };
}

/** Os campos do envio. Sem linha nenhuma, só o padrão vazio (que no Editar volta o padrão a nulo). */
export function camposDaArmazenagem(e: EstadoArmazenagem): Record<string, unknown> {
  const endereco = (l: LinhaArmazenagem) => ({ rack: l.prateleira, row: l.fileira, position: l.posicao });
  const removidos = e.removidos ?? [];
  // Local tirado e posto de volta na mesma edição ainda tem a linha gravada: vai como mudança, não
  // como criação, e não é apagado.
  const jaGravado = (l: LinhaArmazenagem) => l.salvo || removidos.includes(l.local);
  const novos = Object.fromEntries(e.linhas.filter((l) => !jaGravado(l)).map((l) => [l.local, endereco(l)]));
  const gravados = Object.fromEntries(e.linhas.filter(jaGravado).map((l) => [l.local, endereco(l)]));
  const remover = removidos.filter((local) => !e.linhas.some((l) => l.local === local));
  return {
    ...(Object.keys(novos).length ? { product_racks: novos } : {}),
    ...(Object.keys(gravados).length ? { product_racks_update: gravados } : {}),
    ...(remover.length ? { product_racks_remover: remover } : {}),
    local_padrao_id: e.padrao ?? '',
  };
}
