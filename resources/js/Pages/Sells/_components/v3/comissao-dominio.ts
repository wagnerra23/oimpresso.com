/**
 * Domínio de comissão — onda 5 do preview `/sells/create-v3` (CU-SELL-09).
 *
 * Porte de `prototipo-ui/cowork/venda-v3/sells-comissao.jsx`. A fonte abre com a
 * justificativa de por que isto não é um campo "Comissionista", e ela vale citar:
 * quem VENDEU, quem TROUXE o cliente e quem EXECUTOU raramente são a mesma pessoa,
 * e cada um tem regra própria.
 *
 * As dimensões que um select único não dava:
 *   1. vários beneficiários na mesma venda, de tipos diferentes;
 *   2. BASE por beneficiário — bruto, líquido de desconto ou margem;
 *   3. REGRA — percentual, valor fixo ou faixa progressiva;
 *   4. GATILHO de direito — emissão, faturamento ou recebimento;
 *   5. estorno proporcional na devolução;
 *   6. snapshot da regra no momento da venda.
 *
 * ⚠️ TIER 0 — VALOR. Comissão é dinheiro que a empresa deve a alguém. Provado por dois
 * caminhos em `tests/js/comissao-dominio.test.ts`. A tela segue sem gravar.
 *
 * A escolha de BASE não é preferência de tela — é incentivo: comissão sobre **bruto**
 * paga o vendedor para dar desconto (o desconto sai do bolso da empresa e não do dele);
 * sobre **margem**, alinha. E o GATILHO por **recebimento** é o que impede pagar comissão
 * de venda que o cliente nunca pagou.
 */

import { parseBR, submitSafe } from './numeros';

export const TIPOS_BENEFICIARIO = [
  { k: 'funcionario', l: 'Funcionário (vendedor interno)', pgto: 'folha de pagamento', doc: 'CLT' },
  { k: 'representante', l: 'Representante (externo)', pgto: 'título a pagar', doc: 'RPA / nota' },
  { k: 'agencia', l: 'Agência / parceiro', pgto: 'título a pagar', doc: 'nota de serviço' },
  { k: 'tecnico', l: 'Técnico / instalador', pgto: 'folha ou produção', doc: 'CLT / autônomo' },
] as const;

export type TipoBeneficiario = (typeof TIPOS_BENEFICIARIO)[number]['k'];

export const BASES = [
  { value: 'liquido', label: 'Líquido de desconto' },
  { value: 'bruto', label: 'Bruto (antes do desconto)' },
  { value: 'margem', label: 'Margem (venda − custo)' },
] as const;

export type BaseComissao = (typeof BASES)[number]['value'];

export const GATILHOS = [
  { value: 'recebimento', label: 'A cada parcela recebida' },
  { value: 'faturamento', label: 'No faturamento da venda' },
  { value: 'emissao', label: 'Na emissão da venda' },
] as const;

export type Gatilho = (typeof GATILHOS)[number]['value'];

/** Faixa progressiva por volume. `ate: null` é a última — sem teto. */
export const FAIXAS = [
  { ate: 5000, pct: 2 },
  { ate: 20000, pct: 3 },
  { ate: null, pct: 4 },
];

export type Beneficiario = {
  k: number;
  tipo: TipoBeneficiario;
  nome: string;
  base: BaseComissao;
  regra: 'percentual' | 'fixo' | 'faixa';
  pct: string;
  valor: string;
};

export type TotaisDaVenda = { bruto: number; liquido: number; margem: number };

/**
 * Faixa aplicável ao valor-base.
 *
 * ⚠️ É faixa por **valor total**, não progressiva por fatia: quem cai na faixa de 4%
 * recebe 4% sobre tudo, não 2% até 5k + 3% até 20k + 4% no resto. O protótipo é
 * explícito nisso, e mudar o modelo mudaria o que a empresa paga.
 */
export function percentualDaFaixa(base: number): number {
  const faixa = FAIXAS.find((f) => f.ate === null || base <= f.ate) ?? FAIXAS[FAIXAS.length - 1]!;
  return faixa.pct;
}

/** A base de cálculo do beneficiário, escolhida por ELE — não uma da venda inteira. */
export function baseDoBeneficiario(b: Pick<Beneficiario, 'base'>, tot: TotaisDaVenda): number {
  if (b.base === 'bruto') return tot.bruto;
  if (b.base === 'margem') return tot.margem;
  return tot.liquido;
}

/** Comissão de UM beneficiário. Fixo ignora a base; percentual e faixa incidem sobre ela. */
export function comissaoDoBeneficiario(b: Beneficiario, tot: TotaisDaVenda): number {
  const base = baseDoBeneficiario(b, tot);
  if (b.regra === 'fixo') return submitSafe(parseBR(b.valor));
  const pct = b.regra === 'faixa' ? percentualDaFaixa(base) : parseBR(b.pct);
  return submitSafe((base * pct) / 100);
}

export function comissaoTotal(bens: Beneficiario[], tot: TotaisDaVenda): number {
  return submitSafe(bens.reduce((s, b) => s + comissaoDoBeneficiario(b, tot), 0));
}

/** Quanto a comissão representa da venda — o número que dispara o alerta de margem. */
export function percentualSobreALiquida(comissao: number, tot: TotaisDaVenda): number {
  if (tot.liquido <= 0) return 0;
  return (comissao / tot.liquido) * 100;
}

/**
 * Comissão LIBERADA quando o gatilho é `recebimento`.
 *
 * Proporcional ao que o cliente de fato pagou: cada parcela recebida libera a fatia
 * dela no total. É o que impede a empresa pagar comissão de venda inadimplente.
 * Nos outros gatilhos, o direito nasce inteiro no evento (faturamento/emissão).
 */
export function comissaoLiberada(
  comissao: number,
  gatilho: Gatilho,
  parcelas: { valor: string; lanc: string }[],
  totalDaVenda: number,
): number {
  if (gatilho !== 'recebimento') return comissao;
  if (totalDaVenda <= 0) return 0;

  const recebido = parcelas
    .filter((p) => p.lanc === 'RECEBIDA')
    .reduce((s, p) => s + parseBR(p.valor), 0);

  return submitSafe((comissao * recebido) / totalDaVenda);
}

/**
 * Estorno proporcional (clawback) na devolução.
 *
 * Devolveu metade da venda, estorna metade da comissão. Sem isto, devolução vira
 * prejuízo dobrado: a empresa devolve o dinheiro ao cliente E mantém a comissão paga.
 */
export function estornoProporcional(comissao: number, valorDevolvido: number, totalDaVenda: number): number {
  if (totalDaVenda <= 0 || valorDevolvido <= 0) return 0;
  const proporcao = Math.min(1, valorDevolvido / totalDaVenda);
  return submitSafe(comissao * proporcao);
}

/** Teto de sanidade: comissão acima disso da margem come mais da metade do lucro. */
export const ALERTA_SOBRE_MARGEM = 50;

export function comeMaisQueMetadeDaMargem(comissao: number, tot: TotaisDaVenda): boolean {
  if (tot.margem <= 0) return comissao > 0;
  return (comissao / tot.margem) * 100 > ALERTA_SOBRE_MARGEM;
}
