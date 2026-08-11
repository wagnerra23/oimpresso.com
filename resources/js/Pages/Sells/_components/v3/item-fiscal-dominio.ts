/**
 * Domínio fiscal do item — onda 4 do preview `/sells/create-v3`.
 *
 * Porte de `prototipo-ui/cowork/venda-v3/sells-item-detail.jsx`. São as regras que
 * decidem se a NF-e vai ser ACEITA ou REJEITADA pela SEFAZ — e rejeição não é um
 * detalhe de UI: é retrabalho de quem emite, com a venda parada no meio.
 *
 * Duas famílias, e elas são diferentes:
 *   1. FORMATO — o campo tem a cara que a lei exige (NCM tem 8 dígitos, CFOP tem 4…).
 *   2. COERÊNCIA — dois campos que, sozinhos, estão certos, mas juntos se contradizem.
 *      É o caso do CST × alíquota: CST 40 (isenta) com alíquota 18% passa em qualquer
 *      validação de formato e é rejeitada na hora pela SEFAZ.
 *
 * ⚠️ Isto NÃO é cálculo de valor — não multiplica, não soma dinheiro. Decide apenas se
 * o preenchimento é válido. O total do item continua vindo de `calculo-item.ts`, e a
 * tela segue sem gravar.
 *
 * Arquivo separado do componente por `react-refresh/only-export-components` (a catraca
 * `lint:baseline` mordeu isso na onda 2; a lição já vem aplicada desde a onda 3).
 */

import { parseBR } from './numeros';

/** Os 9 tributos que a tela lista, com a alíquota padrão de cena. IBS e CBS entram
 *  porque são os tributos da reforma — o protótipo já os desenha. */
export const IMPOSTOS = [
  { k: 'icms', l: 'ICMS', aliq: 18 },
  { k: 'ipi', l: 'IPI', aliq: 0 },
  { k: 'pis', l: 'PIS', aliq: 1.65 },
  { k: 'cofins', l: 'COFINS', aliq: 7.6 },
  { k: 'issqn', l: 'ISSQN', aliq: 0 },
  { k: 'ii', l: 'II', aliq: 0 },
  { k: 'is', l: 'IS', aliq: 0 },
  { k: 'ibs', l: 'IBS', aliq: 0.1 },
  { k: 'cbs', l: 'CBS', aliq: 0.9 },
];

export const CST_ICMS = [
  '00 — Tributada integralmente',
  '20 — Com redução de base',
  '40 — Isenta',
  '41 — Não tributada',
  '60 — ST cobrado anteriormente',
  '102 — Simples sem crédito',
];

export const LOCAIS_APLICACAO = ['Fachada', 'Interno', 'Veículo', 'Painel', 'Vitrine', 'Totem', 'Obra'];
export const TIPOS_IMPRESSAO = [
  'Digital — látex',
  'Digital — UV',
  'Offset',
  'Recorte eletrônico',
  'Sublimação',
  'Sem impressão',
];

export const ABAS = ['geral', 'producao', 'fluxo', 'tributacao', 'preco', 'anexos', 'observacao'] as const;
export type Aba = (typeof ABAS)[number];

export const ROTULO_DA_ABA: Record<Aba, string> = {
  geral: 'Geral',
  producao: 'Produção',
  fluxo: 'Fluxo de produção',
  tributacao: 'Tributação',
  preco: 'Preço',
  anexos: 'Anexos',
  observacao: 'Observação',
};

/** Só os dígitos — CNPJ/NCM/CFOP chegam com ponto, barra e traço da máscara. */
const soDigitos = (v: unknown): string => String(v ?? '').replace(/\D/g, '');

/* ─── 1. FORMATO ─────────────────────────────────────────────────────────── */

/** NCM: 8 dígitos, obrigatório na NF-e. A mensagem diz QUANTOS faltam, não só "inválido". */
export function validarNcm(v: string): string | null {
  const n = soDigitos(v);
  if (!n) return 'NCM é obrigatório na NF-e';
  if (n.length !== 8) return `NCM tem 8 dígitos — faltam ${Math.abs(8 - n.length)}`;
  return null;
}

/**
 * CFOP: 4 dígitos, e o PRIMEIRO carrega significado — é a natureza da operação.
 * 1/2/3 são entradas, 5/6/7 saídas. 4, 8 e 9 não existem como primeiro dígito.
 */
export function validarCfop(v: string): string | null {
  const n = soDigitos(v);
  if (!n) return 'CFOP é obrigatório';
  if (n.length !== 4) return 'CFOP tem 4 dígitos';
  if (!'123567'.includes(n[0]!)) return 'CFOP começa em 1, 2, 3, 5, 6 ou 7';
  return null;
}

/** CEST: 7 dígitos quando informado. Opcional — só existe para produto sujeito a ST. */
export function validarCest(v: string): string | null {
  const n = soDigitos(v);
  if (!n) return null;
  if (n.length !== 7) return 'CEST tem 7 dígitos';
  return null;
}

/** GTIN: 8, 12, 13 ou 14 dígitos (EAN-8, UPC-A, EAN-13, DUN-14). Opcional. */
export function validarGtin(v: string): string | null {
  const n = soDigitos(v);
  if (!n) return null;
  if (![8, 12, 13, 14].includes(n.length)) return 'GTIN tem 8, 12, 13 ou 14 dígitos';
  return null;
}

/** cBenef: 2 letras da UF + 6 dígitos (ex.: `SC123456`). Opcional. */
export function validarCbenef(v: string): string | null {
  const t = String(v ?? '').trim();
  if (!t) return null;
  if (!/^[A-Za-z]{2}\d{6}$/.test(t)) return 'cBenef: 2 letras da UF + 6 dígitos';
  return null;
}

export function validarAliquota(v: string): string | null {
  const n = parseBR(v);
  if (n < 0) return 'Alíquota não pode ser negativa';
  if (n > 100) return 'Alíquota acima de 100%';
  return null;
}

export function validarReducao(v: string): string | null {
  const n = parseBR(v);
  if (n < 0 || n > 100) return 'Redução vai de 0 a 100%';
  return null;
}

/* ─── 2. COERÊNCIA ───────────────────────────────────────────────────────── */

/**
 * CSTs que NÃO admitem alíquota: 40 (isenta), 41 (não tributada), 60 (ST cobrado
 * anteriormente) e 04 (não tributada). Todos declaram que não há imposto a recolher
 * naquela operação — informar alíquota junto é contradição.
 */
export const CST_SEM_ALIQUOTA = ['40', '41', '60', '04'];

/**
 * Coerência CST × alíquota — o erro que **passa no formato e é rejeitado pela SEFAZ**.
 *
 * Dois sentidos, e os dois importam:
 *   - CST que não admite alíquota **com** alíquota > 0 → contradição;
 *   - CST 00 (tributada integralmente) **sem** alíquota → também contradição, no
 *     sentido oposto: declara que tributa e não diz quanto.
 *
 * O código do CST chega como `'40 — Isenta'`, então os dois primeiros dígitos é que
 * valem. `102` (Simples) tem 3 dígitos e não entra em nenhuma das duas regras.
 */
export function erroDeCoerencia(cst: string, aliquota: string): string | null {
  const codigo = soDigitos(String(cst ?? '').trim().slice(0, 3));
  const prefixo = codigo.slice(0, 2);
  const a = parseBR(aliquota);

  // '102' começa com '10' — não pode ser confundido com um CST de 2 dígitos
  const temTresDigitos = codigo.length >= 3;

  if (!temTresDigitos && CST_SEM_ALIQUOTA.includes(prefixo) && a > 0) {
    return `CST ${prefixo} não admite alíquota — zere ou troque o CST`;
  }
  if (!temTresDigitos && prefixo === '00' && a <= 0) {
    return 'CST 00 é tributado integralmente — alíquota não pode ser zero';
  }
  return null;
}

/* ─── veredito da aba ────────────────────────────────────────────────────── */

export type CamposFiscais = {
  ncm: string;
  cfop: string;
  cest?: string;
  gtin?: string;
  cbenef?: string;
  cst: string;
  aliquota: string;
  reducao?: string;
};

/**
 * Todos os erros da aba Tributação, em ordem estável.
 *
 * Devolve a LISTA e não só o primeiro: quem preenche quer ver tudo que falta de uma
 * vez, não descobrir um erro por vez a cada tentativa de salvar.
 */
export function errosFiscais(c: CamposFiscais): string[] {
  const erros = [
    validarNcm(c.ncm),
    validarCfop(c.cfop),
    validarCest(c.cest ?? ''),
    validarGtin(c.gtin ?? ''),
    validarCbenef(c.cbenef ?? ''),
    validarAliquota(c.aliquota),
    validarReducao(c.reducao ?? '0'),
    erroDeCoerencia(c.cst, c.aliquota),
  ];
  return erros.filter((e): e is string => e !== null);
}

export function fiscalValido(c: CamposFiscais): boolean {
  return errosFiscais(c).length === 0;
}
