/**
 * Alçada do item — prova por DOIS CAMINHOS (REGRA MESTRE de valor).
 *
 * @covers-us UC-V375 UC-V376
 *
 * A REGRA MESTRE de `memory/proibicoes.md` nomeia **preço** e **desconto**, e exige
 * provar o resultado por dois caminhos INDEPENDENTES com números concretos. O
 * segundo caminho aqui é aritmética à mão em **centavos inteiros** — nunca a mesma
 * expressão em float, que provaria só que a função é igual a si mesma.
 *
 * A tela não grava (UC-V302), então nenhum valor deste diff chega ao banco. Mas o
 * número aparece para quem fecha a venda e decide se chama o supervisor — errado
 * aqui é venda fechada abaixo do piso, então a prova vale igual.
 */
import { describe, expect, it } from 'vitest';

import { PISO_DA_TABELA, abaixoDoPiso, alcadaDoItem } from '@/Pages/Sells/_components/v3/calculo-item';

/** Caminho B: centavos inteiros, sem tocar na função sob teste. */
/* piso em CENTAVOS INTEIROS: tabela_centavos x 85 / 100, arredondado a centavo.
   A 1a versao desta linha esquecia o arredondamento intermediario e devolvia
   58,565 onde a funcao devolve 58,57 — o teste reprovou e o errado era ELE, nao
   o codigo. Fica registrado porque e o caminho B fazendo o trabalho dele. */
const pisoAMao = (tabelaCentavos: number): number => Math.round((tabelaCentavos * 85) / 100) / 100;

describe('UC-V375 · piso e folga conferem à mão, em centavos', () => {
  it('a cena do controller: tabela 68,90 → piso 58,57 e folga 10,34', () => {
    // 6890 × 85 = 585.650 → /100 = 5856,5 centavos → 58,565 → exibe 58,57
    // 68,90 − 58,565 = 10,335 → submitSafe (com EPSILON) → 10,34
    const a = alcadaDoItem(68.9, 68.9);
    expect(a.minimo).toBeCloseTo(58.57, 2);
    expect(a.diferenca).toBeCloseTo(10.34, 2);
    expect(a.liberado).toBe(true);
    expect(a.descontoPct).toBeCloseTo(0, 4);
  });

  it('bate em centavos numa matriz de tabelas × preços', () => {
    const tabelas = [1, 10, 68.9, 74.5, 3.5, 199.99, 1234.56, 99_999.99];
    const fatores = [1, 0.9, 0.85, 0.84, 0.5, 1.2];

    let comparados = 0;
    for (const tabela of tabelas) {
      for (const f of fatores) {
        const venda = Math.round(tabela * f * 100) / 100;
        const a = alcadaDoItem(venda, tabela);

        expect(a.minimo).toBeCloseTo(pisoAMao(Math.round(tabela * 100)), 2);

        const descAMao = Math.max(0, ((Math.round(tabela * 100) - Math.round(venda * 100)) * 100) / Math.round(tabela * 100));
        expect(a.descontoPct).toBeCloseTo(descAMao, 2);

        comparados++;
      }
    }
    // controle positivo: sem o contador, laço que não roda passa por não-execução (LC-13)
    expect(comparados).toBe(tabelas.length * fatores.length);
  });

  it('preço ACIMA da tabela não vira desconto negativo', () => {
    expect(alcadaDoItem(80, 68.9).descontoPct).toBe(0);
  });

  it('tabela zero não divide por zero', () => {
    const a = alcadaDoItem(10, 0);
    expect(a.descontoPct).toBe(0);
    expect(Number.isFinite(a.minimo)).toBe(true);
  });

  it('a diferença é sempre positiva — é distância, não saldo', () => {
    expect(alcadaDoItem(50, 68.9).diferenca).toBeGreaterThan(0);
    expect(alcadaDoItem(68.9, 68.9).diferenca).toBeGreaterThan(0);
  });
});

describe('UC-V376 · o veredito da faixa NUNCA discorda do piso já usado no lançamento', () => {
  it('liberado === !abaixoDoPiso, inclusive na fronteira', () => {
    const tabela = 68.9;
    // varre 57,00 → 60,00 de centavo em centavo: cobre o piso cru (58,565), o
    // piso EXIBIDO (58,57) e a faixa entre os dois, que é onde os dois números
    // discordariam se o veredito comparasse com o arredondado.
    let comparados = 0;
    for (let c = 5700; c <= 6000; c++) {
      const venda = c / 100;
      expect(alcadaDoItem(venda, tabela).liberado).toBe(!abaixoDoPiso(venda, tabela));
      comparados++;
    }
    expect(comparados).toBe(301);
  });

  it('58,566 é liberado — e seria barrado se o veredito usasse o piso exibido', () => {
    // este é o caso concreto que motivou delegar em vez de comparar:
    //   piso cru      58,565  → 58,566 >= piso  → liberado ✅
    //   piso exibido  58,570  → 58,566 <  piso  → barraria ❌
    const a = alcadaDoItem(58.566, 68.9);
    expect(a.liberado).toBe(true);
    expect(a.minimo).toBeCloseTo(58.57, 2);
    expect(58.566 < a.minimo).toBe(true); // a armadilha existe, e o veredito escapa dela
  });

  it('o piso é 85% da tabela — a constante é a mesma do lançamento', () => {
    expect(PISO_DA_TABELA).toBe(0.85);
    expect(alcadaDoItem(100, 100).minimo).toBeCloseTo(85, 2);
  });
});
