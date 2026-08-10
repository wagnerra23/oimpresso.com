// Regras de cálculo do ITEM da venda — funções PURAS, sem React.
//
// POR QUE ARQUIVO SEPARADO (duas razões, e a segunda é a que importa)
// 1. Misturar helper com componente quebra o Fast Refresh do Vite
//    (`react-refresh/only-export-components`) — mesma razão do `numeros.ts`.
// 2. Estas são as fórmulas de VALOR e QUANTIDADE do lançamento — território
//    `[V0]` da REGRA MESTRE (memory/proibicoes.md). Num módulo sem JSX elas
//    podem ser exercidas sem montar árvore React, que é o que permite provar o
//    número por um caminho independente da tela.
//
// SEPARAÇÃO DE PAPÉIS
// `numeros.ts` = LOCALE (texto pt-BR ↔ número). Aqui = REGRA DE NEGÓCIO do
// item. Quem lê `parseBR`/`submitSafe` lá; quem lê área/preço lê aqui.

import { submitSafe } from './numeros';

/**
 * Área de UMA peça, decidida pela UNIDADE do cadastro.
 *
 * A unidade é o que define se a quantidade é derivada ou digitada — não um
 * checkbox de tela. `m²` = altura × largura · `m³` acrescenta a espessura ·
 * `m` (metro linear) usa só a largura · qualquer outra unidade devolve 1,
 * porque aí a quantidade é digitada direto e não há área a compor.
 *
 * ⚠️ NÃO passa por `submitSafe`, e essa é a única divergência consciente do
 * handoff. Lá a área é arredondada a 2 casas — mas `submitSafe` é o guard de
 * DINHEIRO (o que o front pode submeter), não de MEDIDA. Aplicado aqui, ele
 * zera toda peça menor que 0,005: uma placa de 0,50 × 0,10 × 0,02 m dá
 * 0,001 m³, que virava **0**, e com quantidade 0 o botão "Adicionar à venda"
 * fica desabilitado — o item simplesmente não entra na venda.
 *
 * O próprio handoff se denuncia ao exibir a área com `num(areaUn, 3)`: pede
 * 3 casas de um número que ele arredondou a 2. Achado pela prova de dois
 * caminhos (aritmética à mão × função real), não por leitura.
 *
 * O arredondamento continua existindo — mas no fim, sobre a quantidade
 * faturada, que é o número que vira valor.
 */
export function areaUnitaria(un: string, altura: number, largura: number, esp: number): number {
  if (un === 'm²') return altura * largura;
  if (un === 'm³') return altura * largura * esp;
  if (un === 'm') return largura;
  return 1;
}

/**
 * Casas decimais de MEDIDA (≠ casas de dinheiro).
 *
 * Dinheiro tem 2 (`submitSafe`); medida precisa de mais. Uma placa de 2 mm
 * numa peça de 0,50 × 0,004 m dá 0,002 m² — com 2 casas isso é ZERO, e item
 * com quantidade zero não entra na venda. 4 casas cobre o caso real de
 * recorte fino e de m³, e ainda arredonda o suficiente pra não propagar ruído
 * de float. O DISPLAY continua em 2 casas: precisão de cálculo e precisão de
 * exibição são decisões diferentes.
 */
const CASAS_DE_MEDIDA = 4;

function arredondarMedida(n: number): number {
  const f = 10 ** CASAS_DE_MEDIDA;
  return Math.round((n + Number.EPSILON) * f) / f;
}

/**
 * Quantidade FATURADA do item.
 *
 * Dimensional: peças × área da peça. Não-dimensional: o que foi digitado.
 * `pecas` negativo é tratado como 0 — quantidade negativa viraria total
 * negativo e, no dia em que isto gravar, estoque somando em vez de baixar.
 *
 * ⚠️ Arredonda por MEDIDA, não por dinheiro — o handoff usava `submitSafe`
 * aqui e zerava item fino (medido no harness: placa de 2 mm ficava com
 * `0,00 m²`, total `R$ 0,00` e o botão "Adicionar à venda" desabilitado).
 */
export function quantidadeFaturada(
  ehDimensional: boolean,
  pecas: number,
  areaPorPeca: number,
  qtdDigitada: number,
): number {
  if (!ehDimensional) return arredondarMedida(qtdDigitada);
  return arredondarMedida(Math.max(pecas, 0) * areaPorPeca);
}

/**
 * Unitário líquido — desconto e acréscimo incidem sobre o PREÇO, nunca sobre o
 * total. A ordem importa: aplicar sobre o total daria outro número quando a
 * quantidade é fracionada, que é o caso normal em item dimensional.
 */
export function unitarioLiquido(preco: number, descPct: number, acrPct: number): number {
  return submitSafe(preco * (1 - descPct / 100) * (1 + acrPct / 100));
}

/** Total da linha — a única multiplicação que fecha o item. */
export function totalDoItem(qtd: number, unitario: number): number {
  return submitSafe(qtd * unitario);
}

/**
 * Abaixo do piso da tabela: o handoff trava em 15% de folga, então preço menor
 * que 85% da tabela sai da alçada do vendedor e exige liberação.
 *
 * No limite EXATO o resultado depende de precisão binária — `68,90 × 0,85` dá
 * `58.565000000000005`, então digitar `58,565` cai como "abaixo". Fica assim
 * de propósito: no empate o erro é pedir liberação a mais, nunca deixar passar
 * preço baixo demais. Mexer nisso (tolerância epsilon) mudaria a semântica de
 * ALÇADA sem ninguém ter pedido — é decisão de negócio, não de precisão.
 */
export const PISO_DA_TABELA = 0.85;

export function abaixoDoPiso(precoDigitado: number, precoTabela: number): boolean {
  return precoDigitado < precoTabela * PISO_DA_TABELA;
}
