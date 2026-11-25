/**
 * Domínio de entrega e frete — onda 2 do preview `/sells/create-v3` (CU-SELL-11).
 *
 * Arquivo separado do componente **de propósito**: `react-refresh/only-export-components`
 * reprova quando um módulo exporta componente E constante/função no mesmo lugar, e a
 * catraca `lint:baseline:check` mordeu exatamente isso na primeira versão. Além do
 * fast-refresh, separar deixa a regra testável sem montar React.
 *
 * ⚠️ Nada aqui é VALOR ou ESTOQUE. O único número derivado é **peso** (kg), que é dado
 * de transporte da NF-e. O `frete` continua sendo state da Page, e a cadeia de total
 * (`vFrete = submitSafe(parseBR(frete))`) segue intocada por esta onda.
 */

import { parseBR, submitSafe } from './numeros';

/**
 * Os seis códigos de "frete por conta" são o campo `modFrete` da NF-e — domínio fiscal,
 * não invenção da tela. O `9` é o único que apaga o grupo de transporte inteiro.
 */
export const FRETE_CIF = '0 — Contratação por conta do Remetente (CIF)';

export const FRETE_POR_CONTA = [
  FRETE_CIF,
  '1 — Contratação por conta do Destinatário (FOB)',
  '2 — Contratação por conta de Terceiros',
  '3 — Transporte próprio por conta do Remetente',
  '4 — Transporte próprio por conta do Destinatário',
  '9 — Sem ocorrência de transporte',
];

export const ESPECIES = ['Caixa', 'Palete', 'Pacote', 'Rolo', 'Fardo', 'Tubo', 'Bobina', 'Volume'];
export const UFS = ['SC', 'PR', 'RS', 'SP', 'RJ', 'MG', 'BA', 'GO', 'DF'];
export const MODALIDADES = ['Rodoviário', 'Retirada no balcão', 'Frota própria', 'Motoboy', 'Correios / Sedex'];
export const STATUS_REMESSA = ['Pendente', 'Em separação', 'Despachado', 'Entregue', 'Devolvido'];

export type Transportadora = {
  cod: string;
  nome: string;
  doc: string;
  uf: string;
  cidade: string;
  placa: string;
  antt: string;
  modal: string;
};

/** Só o que o cálculo de peso precisa saber de um item — não o item inteiro. */
export type ItemPesavel = { un?: string; qtd: string; peso?: string };

/** `9 — Sem ocorrência de transporte`: a NF-e sai sem grupo de transporte. */
export function semOcorrenciaDeTransporte(fretePorConta: string): boolean {
  return fretePorConta.startsWith('9');
}

/** Peso presumido por unidade de medida quando o item não tem peso cadastrado. */
const PESO_PADRAO_POR_UNIDADE: Record<string, number> = { 'm²': 0.45, un: 0.08 };

/**
 * Peso bruto somado dos itens, em kg. **Não é valor nem estoque.**
 *
 * Item sem peso cadastrado entra como **zero** — a fonte de design declara isso
 * explicitamente, e é a escolha certa: somar zero é honesto, inventar massa não.
 */
export function pesoBrutoDosItens(itens: ItemPesavel[]): number {
  const pesoUnitario = (l: ItemPesavel): number =>
    l.peso !== undefined ? parseBR(l.peso) : (PESO_PADRAO_POR_UNIDADE[l.un ?? ''] ?? 0);

  return submitSafe(itens.reduce((s, l) => s + pesoUnitario(l) * parseBR(l.qtd), 0));
}

/**
 * Líquido = bruto − embalagem. Os 6% são estimativa de CENA do protótipo, não regra
 * fiscal — por isso o campo é editável assim que o usuário liga o peso manual.
 */
export const FATOR_LIQUIDO = 0.94;

export function pesoLiquidoEstimado(pesoBruto: number): number {
  return submitSafe(pesoBruto * FATOR_LIQUIDO);
}

/** Filtro da consulta de transportadoras: código, razão social, CNPJ ou cidade. */
export function filtrarTransportadoras(lista: Transportadora[], termo: string): Transportadora[] {
  const q = termo.trim().toLowerCase();
  if (!q) return lista;
  return lista.filter((t) => `${t.cod}${t.nome}${t.doc}${t.cidade}`.toLowerCase().includes(q));
}

/* ─── gravação do transporte (UC-V409) ──────────────────────────────────── */

/**
 * Status da remessa como o store grava (`transactions.shipping_status`): as cinco chaves de
 * `Util::shipping_statuses`. Gravando, a tela oferece estas, não as da cena.
 */
export const STATUS_REMESSA_GRAVADO: Array<[rotulo: string, chave: string]> = [
  ['Pedido', 'ordered'],
  ['Embalado', 'packed'],
  ['Enviado', 'shipped'],
  ['Entregue', 'delivered'],
  ['Cancelado', 'cancelled'],
];

export type EnderecoEntrega = {
  ibge: string;
  cidade: string;
  uf: string;
  cep: string;
  logradouro: string;
  numero: string;
  bairro: string;
  complemento: string;
  recebedor: string;
  telefone: string;
  email: string;
  ie: string;
};

export const ENDERECO_VAZIO: EnderecoEntrega = {
  ibge: '', cidade: '', uf: '', cep: '', logradouro: '', numero: '',
  bairro: '', complemento: '', recebedor: '', telefone: '', email: '', ie: '',
};

/**
 * O endereço de entrega vira o texto de `transactions.shipping_address` (o store não tem colunas
 * separadas). O recebedor vai à parte, em `delivered_to`. Vazio = usa o do cadastro do cliente.
 */
export function textoDoEnderecoEntrega(e: EnderecoEntrega): string {
  const rua = [e.logradouro, e.numero].filter((s) => s.trim()).join(', ');
  const linha1 = [rua, e.complemento].filter((s) => s.trim()).join(' - ');
  const cidade = [e.cidade, e.uf].filter((s) => s.trim()).join('/');
  const partes = [
    linha1,
    e.bairro,
    cidade,
    e.cep && `CEP ${e.cep}`,
    e.ibge && `IBGE ${e.ibge}`,
    e.telefone && `Tel. ${e.telefone}`,
    e.email,
    e.ie && `IE ${e.ie}`,
  ];
  return partes.filter((s) => s && s.trim()).join(' · ');
}

/** O que a venda grava do transporte. Nada aqui é valor: o frete segue no fechamento. */
export type TransporteDaVenda = {
  modFrete: number;
  transportadoraId: number | null;
  placa: string;
  uf: string;
  qtdVolumes: number;
  numeracao: string;
  especie: string;
  pesoBruto: number;
  pesoLiquido: number;
  statusRemessa: string;
  rastreio: string;
  enderecoEntrega: string;
  recebedor: string;
};

/** `0 — …` → 0. O código é o `modFrete` da NF-e. */
export function codigoModFrete(fretePorConta: string): number {
  const n = Number.parseInt(fretePorConta, 10);
  return Number.isNaN(n) ? 9 : n;
}

/** Campos do payload do `/pos`. Com `9` (sem transporte) o grupo de transporte vai zerado. */
export function camposDoTransporte(t: TransporteDaVenda): Record<string, unknown> {
  const sem = t.modFrete === 9;
  return {
    mod_frete: t.modFrete,
    transportadora_id: sem ? null : t.transportadoraId,
    placa: sem ? '' : t.placa,
    uf: sem ? '' : t.uf,
    qtd_volumes: sem ? 0 : t.qtdVolumes,
    numeracao_volumes: sem ? '' : t.numeracao,
    especie: sem ? '' : t.especie,
    peso_bruto: sem ? 0 : t.pesoBruto,
    peso_liquido: sem ? 0 : t.pesoLiquido,
    shipping_status: t.statusRemessa,
    shipping_details: sem ? '' : t.rastreio,
    shipping_address: t.enderecoEntrega,
    delivered_to: t.recebedor,
  };
}
