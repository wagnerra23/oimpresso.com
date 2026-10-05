// Cálculo de EXIBIÇÃO da devolução de venda (SellReturn/Add) — espelho do servidor.
//
// A Page NÃO grava valor calculado: ela envia ao SellReturnController@store os mesmos
// TEXTOS que o form Blade envia (quantidade digitada, preço e desconto no formato da
// empresa), e o servidor recalcula tudo em ProductUtil::calculateInvoiceTotal. Este arquivo
// só mostra ANTES de salvar o número que o servidor vai gravar (charter R2). Por isso ele
// copia a regra do servidor, não a do JS da Blade:
//   - numUf()  = Util::num_uf (app/Utils/Util.php) — mesma heurística pt-BR, linha a linha.
//   - desconto: 'percentage' = % do subtotal; qualquer outro tipo (inclusive "Nenhum", que
//     chega ao servidor como null e vira 'fixed' em addSellReturn) = valor fixo.
//   - imposto: % da taxa da venda sobre (subtotal − desconto).
// REGRA MESTRE (memory/proibicoes.md): mudar algo aqui não muda o que é gravado, mas muda o
// que o operador vê — mantenha igual ao servidor. Casos: Add.casos.md (UC-SRADD-*).

/** Espelho de Util::num_uf — texto do form → número. */
export function numUf(entrada: string | number | null | undefined): number {
  if (entrada === null || entrada === undefined || entrada === '') return 0;
  const limpo = String(entrada).replace(/[^0-9.,-]/g, '');
  if (limpo === '' || limpo === '-') return 0;

  const negativo = limpo.startsWith('-');
  const abs = negativo ? limpo.slice(1) : limpo;

  let normalizado: string;
  if (abs.includes(',')) {
    const ultimaVirgula = abs.lastIndexOf(',');
    const inteiro = abs.slice(0, ultimaVirgula).replace(/\./g, '');
    const decimal = abs.slice(ultimaVirgula + 1).replace(/\./g, '');
    normalizado = `${inteiro}.${decimal}`;
  } else if (abs.includes('.')) {
    const pontos = (abs.match(/\./g) || []).length;
    const depoisDoUltimo = abs.length - abs.lastIndexOf('.') - 1;
    normalizado = pontos === 1 && depoisDoUltimo !== 3 ? abs : abs.replace(/\./g, '');
  } else {
    normalizado = abs;
  }

  const n = parseFloat(normalizado);
  if (Number.isNaN(n)) return 0;
  return negativo ? -n : n;
}

export interface LinhaCalculo {
  quantidade_txt: string;
  preco_unitario_txt: string;
}

export interface TotaisDevolucao {
  subtotal: number;
  desconto: number;
  imposto: number;
  total: number;
}

/** Espelho de ProductUtil::calculateInvoiceTotal para o payload da devolução. */
export function calcularTotais(
  linhas: LinhaCalculo[],
  descontoTipo: string,
  descontoValorTxt: string,
  impostoPercentual: number | null,
): TotaisDevolucao {
  const subtotal = linhas.reduce((s, l) => s + numUf(l.quantidade_txt) * numUf(l.preco_unitario_txt), 0);
  const valorDesconto = numUf(descontoValorTxt);
  const desconto = descontoTipo === 'percentage' ? (valorDesconto / 100) * subtotal : valorDesconto;
  const imposto = impostoPercentual ? (impostoPercentual / 100) * (subtotal - desconto) : 0;

  return { subtotal, desconto, imposto, total: subtotal + imposto - desconto };
}
