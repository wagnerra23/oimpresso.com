// Preparação da onda 2. Sem consumidor na Page até a integração sequencial.
// Contratos: CreateV3.casos.md §Fechamento; Create.casos.md UC-VST / UC-SCOND.
// Valores já normalizados pelo domínio de itens/cliente; nunca aplica alíquota fiscal de cena.
import { submitSafe } from './numeros';
import { arredondarTotal } from './config-business-dominio';
import { RECEBIDA, somaDasParcelas, type Parcela } from './parcelas-dominio';

export type DespesaVenda = { descricao: string; valor: number };
export type BaseFechamento = {
  /** Itens líquidos, depois do desconto do pedido; antes de imposto, frete e despesas. */
  liquido: number;
  imposto: { id: number; aliquota: number } | null;
  impostoDesligado: boolean;
  frete: number;
  despesas: DespesaVenda[];
};

function naoNegativo(n: number): number {
  if (!Number.isFinite(n) || n < 0) throw new Error('Valor inválido no fechamento.');
  return n;
}

/** CU-SELL-12: despesa entra DEPOIS do imposto e não compõe a base dele. */
export function prepararBaseFechamento(v: BaseFechamento) {
  if (v.despesas.length > 4) throw new Error('A venda aceita até quatro despesas.');
  const liquido = naoNegativo(v.liquido);
  const taxId = !v.impostoDesligado && v.imposto ? v.imposto.id : null;
  if (taxId !== null && (!Number.isInteger(taxId) || taxId <= 0)) throw new Error('Imposto inválido.');
  const imposto = taxId === null ? 0 : liquido * naoNegativo(v.imposto!.aliquota) / 100;
  const despesas = v.despesas.reduce((s, d) => s + submitSafe(naoNegativo(d.valor)), 0);
  const campos: Record<string, string | number | null> = { tax_rate_id: taxId };
  for (let i = 0; i < 4; i++) {
    campos[`additional_expense_key_${i + 1}`] = v.despesas[i]?.descricao ?? '';
    campos[`additional_expense_value_${i + 1}`] = submitSafe(v.despesas[i]?.valor ?? 0);
  }
  return {
    imposto: submitSafe(imposto), despesas: submitSafe(despesas),
    totalSemSt: submitSafe(liquido + imposto + naoNegativo(v.frete) + despesas), campos,
  };
}

/** Corpo EXATO de SellIcmsStController, sem business_id vindo do navegador. */
export type ConsultaSt = {
  location_id: number | null;
  contact_id: number | null;
  itens: { variation_id: number; quantidade: number; valor_unitario: number }[];
  total_sem_st: number;
  frete: number;
};
export type CotacaoSt = { chave: string; valor: number };

export function chaveConsultaSt(consulta: ConsultaSt): string {
  return JSON.stringify(consulta);
}

/** O retorno usa decimal de máquina, NÃO parseBR ("1.234" aqui é 1,234). */
export function receberCotacaoSt(consulta: ConsultaSt, resposta: { icms_st: string }): CotacaoSt {
  if (!/^\d+\.\d{2}$/.test(resposta.icms_st)) throw new Error('Resposta de ICMS ST inválida.');
  return { chave: chaveConsultaSt(consulta), valor: naoNegativo(Number(resposta.icms_st)) };
}

/** UC-VST-01/02/06: só usa cotação do carrinho atual; zero também exige resposta. */
export function concluirComSt(consulta: ConsultaSt, cotacao: CotacaoSt | null, arredondamento = 0) {
  if (!cotacao || cotacao.chave !== chaveConsultaSt(consulta)) {
    throw new Error('Aguarde o ICMS ST do carrinho atual.');
  }
  const st = submitSafe(naoNegativo(cotacao.valor));
  const resultado = arredondarTotal(submitSafe(naoNegativo(consulta.total_sem_st) + st), arredondamento);
  return {
    icms_st_valor: st, final_total: resultado.total,
    ...(resultado.diferenca !== 0 ? { round_off_amount: resultado.diferenca } : {}),
  };
}

/** UC-SCOND-02/03: vazio herda no servidor; zero é uma escolha explícita. Não soma ao total. */
export function sinalParaGravar(entrada: string): number | null {
  const texto = entrada.trim();
  if (!texto) return null;
  if (!/^\d+(?:[,.]\d{1,2})?$/.test(texto)) throw new Error('Informe um sinal entre 0 e 100%.');
  const valor = Number(texto.replace(',', '.'));
  if (valor > 100) throw new Error('Informe um sinal entre 0 e 100%.');
  return valor;
}

/** Adiantamento é pagamento já disponível do cliente, não desconto nem sinal exigido. */
export function conferirAdiantamento(valor: number, saldoCliente: number, faltaReceber: number): number {
  naoNegativo(valor); naoNegativo(saldoCliente); naoNegativo(faltaReceber);
  const centavos = (n: number) => Math.round(submitSafe(n) * 100);
  if (centavos(valor) > Math.min(centavos(saldoCliente), centavos(faltaReceber))) {
    throw new Error('Adiantamento acima do saldo do cliente ou do que falta receber.');
  }
  return submitSafe(valor);
}

/**
 * CU-SELL-09: A RECEBER nunca entra em payment[] (o store registraria dinheiro recebido).
 * A integração deve persistir o plano pelo contrato financeiro, ainda pendente, separadamente.
 * Recebidas editadas com ID precisam do adaptador de edição da onda 1; não recriar pagamentos.
 */
export function separarParcelas(parcelas: Parcela[]) {
  const recebidas = parcelas.filter((p) => p.lanc === RECEBIDA);
  const aReceber = parcelas.filter((p) => p.lanc !== RECEBIDA);
  return { recebidas, aReceber, pago: somaDasParcelas(recebidas), previsto: somaDasParcelas(aReceber) };
}
