// Locais de armazenagem do produto (CU-PROD-20): em quais locais de estoque ele fica, o endereço
// dentro de cada um (prateleira, fileira, posição) e qual é o local padrão.
//
// O servidor guarda o endereço no `product_racks` (uma linha por local) e o padrão em
// `products.local_padrao_id`. O envio usa os mesmos campos do Blade: `product_racks` cria a linha
// do local e `product_racks_update` muda a que já existe. O cadastro não grava quantidade: o
// estoque entra pela tela de estoque inicial (decisão do #10544).

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

/** Tira uma linha não gravada. Se era o padrão, o padrão passa para a primeira que sobrar. */
export function semLinha(e: EstadoArmazenagem, indice: number): EstadoArmazenagem {
  const alvo = e.linhas[indice];
  if (!alvo || alvo.salvo) return e;
  const linhas = e.linhas.filter((_, i) => i !== indice);
  return { linhas, padrao: e.padrao === alvo.local ? (linhas[0]?.local ?? null) : e.padrao };
}

/** Os campos do envio. Sem linha nenhuma, só o padrão vazio (que no Editar volta o padrão a nulo). */
export function camposDaArmazenagem(e: EstadoArmazenagem): Record<string, unknown> {
  const endereco = (l: LinhaArmazenagem) => ({ rack: l.prateleira, row: l.fileira, position: l.posicao });
  const novos = Object.fromEntries(e.linhas.filter((l) => !l.salvo).map((l) => [l.local, endereco(l)]));
  const gravados = Object.fromEntries(e.linhas.filter((l) => l.salvo).map((l) => [l.local, endereco(l)]));
  return {
    ...(Object.keys(novos).length ? { product_racks: novos } : {}),
    ...(Object.keys(gravados).length ? { product_racks_update: gravados } : {}),
    local_padrao_id: e.padrao ?? '',
  };
}
