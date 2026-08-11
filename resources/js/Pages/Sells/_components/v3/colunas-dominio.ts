/**
 * Domínio das colunas do grid — onda 6 do preview `/sells/create-v3`.
 *
 * Porte de `prototipo-ui/cowork/venda-v3/sells-colunas.jsx`. Escolher e reordenar as
 * colunas do grid de itens, incluindo as fiscais.
 *
 * ⚠️ Nenhum cálculo de valor aqui — só quais colunas aparecem e em que ordem.
 *
 * O QUE PRECISA SOBREVIVER A DADO PODRE
 * A preferência mora em `localStorage`, que é entrada NÃO CONFIÁVEL: o usuário pode
 * editar, uma versão futura pode remover uma coluna, e um JSON truncado é possível.
 * Por isso o `carregar` é defensivo em quatro frentes — JSON inválido, tipo errado,
 * chave que não existe mais e a ausência de coluna fixa. Uma tela de venda que quebra
 * porque o `localStorage` tem lixo é pior que uma tela sem preferência salva.
 */

export const CHAVE_LOCALSTORAGE = 'oimpresso.vendas.sdd.colunas';

export type GrupoDeColuna = 'ident' | 'medida' | 'valor' | 'fiscal' | 'producao' | 'estoque';

export type DefinicaoDeColuna = {
  k: string;
  label: string;
  grupo: GrupoDeColuna;
  /** Fixa = a linha não existe sem ela. Não pode ser removida nem reordenada para fora. */
  fixa?: boolean;
  /** Entra na configuração inicial de quem nunca mexeu. */
  padrao?: boolean;
  largura?: number;
};

export const GRUPOS: { k: GrupoDeColuna; label: string }[] = [
  { k: 'ident', label: 'Identificação' },
  { k: 'medida', label: 'Medidas' },
  { k: 'valor', label: 'Valores' },
  { k: 'fiscal', label: 'Classificação fiscal' },
  { k: 'producao', label: 'Produção' },
  { k: 'estoque', label: 'Estoque' },
];

export const COLUNAS: DefinicaoDeColuna[] = [
  // fixas — a linha não existe sem elas
  { k: 'produto', label: 'Produto / serviço', grupo: 'ident', fixa: true, largura: 260 },
  { k: 'qtd', label: 'Quant.', grupo: 'valor', fixa: true, largura: 90 },
  { k: 'preco', label: 'R$ Valor', grupo: 'valor', fixa: true, largura: 110 },
  { k: 'total', label: 'R$ Total', grupo: 'valor', fixa: true, largura: 110 },

  // padrão — vêm ligadas
  { k: 'desc', label: 'Desc. %', grupo: 'valor', padrao: true, largura: 90 },
  { k: 'acr', label: 'Acrésc. %', grupo: 'valor', padrao: true, largura: 90 },

  // identificação
  { k: 'sku', label: 'SKU', grupo: 'ident', largura: 120 },
  { k: 'ean', label: 'Código de barras', grupo: 'ident', largura: 140 },
  { k: 'fabrica', label: 'Cód. de fábrica', grupo: 'ident', largura: 130 },
  { k: 'categoria', label: 'Categoria', grupo: 'ident', largura: 140 },

  // medidas
  { k: 'un', label: 'Unidade', grupo: 'medida', largura: 80 },
  { k: 'altura', label: 'Altura (m)', grupo: 'medida', largura: 100 },
  { k: 'largura', label: 'Largura (m)', grupo: 'medida', largura: 100 },
  { k: 'esp', label: 'Espessura (m)', grupo: 'medida', largura: 110 },
  { k: 'area', label: 'Área un.', grupo: 'medida', largura: 100 },
  { k: 'pecas', label: 'Peças', grupo: 'medida', largura: 80 },

  // fiscal
  { k: 'ncm', label: 'NCM', grupo: 'fiscal', largura: 110 },
  { k: 'cfop', label: 'CFOP', grupo: 'fiscal', largura: 90 },
  { k: 'cst', label: 'CST', grupo: 'fiscal', largura: 90 },
  { k: 'aliq', label: 'Alíquota', grupo: 'fiscal', largura: 100 },
  { k: 'origem', label: 'Origem', grupo: 'fiscal', largura: 120 },

  // produção
  { k: 'acabamento', label: 'Acabamento', grupo: 'producao', largura: 150 },
  { k: 'aplicacao', label: 'Aplicação', grupo: 'producao', largura: 130 },
  { k: 'impressao', label: 'Impressão', grupo: 'producao', largura: 150 },
  { k: 'executante', label: 'Executante', grupo: 'producao', largura: 150 },
  { k: 'prazo', label: 'Prazo', grupo: 'producao', largura: 110 },

  // estoque
  { k: 'estoque', label: 'Saldo', grupo: 'estoque', largura: 100 },
  { k: 'saldoApos', label: 'Saldo após', grupo: 'estoque', largura: 110 },
  { k: 'lote', label: 'Lote', grupo: 'estoque', largura: 110 },
  { k: 'deposito', label: 'Depósito', grupo: 'estoque', largura: 130 },
];

export const CHAVES_FIXAS = COLUNAS.filter((c) => c.fixa).map((c) => c.k);

export function definicaoDe(k: string): DefinicaoDeColuna | undefined {
  return COLUNAS.find((c) => c.k === k);
}

/** Configuração de quem nunca mexeu: fixas + as marcadas como padrão, na ordem do catálogo. */
export function colunasPadrao(): string[] {
  return COLUNAS.filter((c) => c.fixa || c.padrao).map((c) => c.k);
}

/**
 * Sanea uma lista vinda de fora (localStorage, prop, URL).
 *
 * Quatro defesas, e cada uma corresponde a um jeito real de o dado chegar podre:
 *   - não é array → cai no padrão;
 *   - chave que não existe mais (coluna removida numa versão) → descartada;
 *   - chave repetida → mantém a primeira;
 *   - coluna FIXA ausente → reinserida, porque a linha não existe sem ela.
 */
export function sanearColunas(entrada: unknown): string[] {
  if (!Array.isArray(entrada)) return colunasPadrao();

  const vistas = new Set<string>();
  const validas = entrada.filter((k): k is string => {
    if (typeof k !== 'string' || vistas.has(k) || !definicaoDe(k)) return false;
    vistas.add(k);
    return true;
  });

  if (validas.length === 0) return colunasPadrao();

  // fixa que faltou volta na frente — sem ela o grid não tem produto, quantidade ou total
  const faltantes = CHAVES_FIXAS.filter((k) => !vistas.has(k));
  return [...faltantes, ...validas];
}

/** Lê a preferência salva. Nunca lança: `localStorage` pode estar bloqueado ou com lixo. */
export function carregarColunas(storage?: Pick<Storage, 'getItem'>): string[] {
  try {
    const bruto = (storage ?? window.localStorage).getItem(CHAVE_LOCALSTORAGE);
    if (!bruto) return colunasPadrao();
    return sanearColunas(JSON.parse(bruto));
  } catch {
    return colunasPadrao();
  }
}

/** Grava a preferência. Silencioso em erro — modo anônimo e cota cheia não podem quebrar a venda. */
export function salvarColunas(chaves: string[], storage?: Pick<Storage, 'setItem'>): void {
  try {
    (storage ?? window.localStorage).setItem(CHAVE_LOCALSTORAGE, JSON.stringify(chaves));
  } catch {
    /* preferência de coluna não vale derrubar a tela */
  }
}

/** Move uma coluna. Fixa não sai do lugar, e índice fora da lista é ignorado. */
export function moverColuna(chaves: string[], de: number, para: number): string[] {
  if (de === para) return chaves;
  if (de < 0 || de >= chaves.length || para < 0 || para >= chaves.length) return chaves;

  const alvo = chaves[de]!;
  if (definicaoDe(alvo)?.fixa) return chaves;
  if (definicaoDe(chaves[para]!)?.fixa) return chaves;

  const resto = chaves.filter((_, i) => i !== de);
  return [...resto.slice(0, para), alvo, ...resto.slice(para)];
}

/** Liga/desliga uma coluna. Fixa não desliga — é o que impede o grid ficar sem total. */
export function alternarColuna(chaves: string[], k: string): string[] {
  if (!definicaoDe(k)) return chaves;
  if (chaves.includes(k)) {
    if (definicaoDe(k)?.fixa) return chaves;
    return chaves.filter((x) => x !== k);
  }
  return [...chaves, k];
}
