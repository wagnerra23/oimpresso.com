/**
 * UC-V397..UC-V399 — tabela de preço na gravação da V3 (Sells/CreateV3.casos.md) · REGRA MESTRE.
 *
 * Regra do Sells/Create.tsx: `precoDaBusca` (preço do grupo na busca com `price_group`, senão o
 * preço base), `handlePriceGroupChange` (troca refaz o preço das linhas, a que não volta fica) e
 * `handleCustomerSelect` (cliente com tabela aplica a dele, sem tabela mantém a atual).
 *
 * Prova por dois caminhos (o 2º é o Pest UC-V398 em SellsCreateV3GravarContratoTest.php):
 *   conta à mão, venda da fixture com a tabela que dá 15,00 ao Produto A e nada ao B:
 *   3 × 15,00 − 10% = 40,50 · 2 × 50,00 = 100,00 · 140,50 − 5,00 + frete 7,50 = 143,00
 *   (sem tabela, a mesma venda dá 156,23).
 */

import { readFileSync } from 'node:fs';

import { describe, expect, it } from 'vitest';

import { fmtBR } from '@/Pages/Sells/_components/v3/numeros';
import {
  aplicarPrecos,
  nomeDaTabela,
  parametrosDaBusca,
  precoDaBusca,
  tabelaAposCliente,
} from '@/Pages/Sells/_components/v3/tabela-preco-dominio';
import { montarPayloadVenda, totalParaGravar, type VendaParaGravar } from '@/Pages/Sells/_components/v3/gravacao-dominio';

const fixture = JSON.parse(readFileSync('tests/fixtures/sells-v3/venda-simples.json', 'utf8')) as { entrada: VendaParaGravar };

describe('UC-V397 · as tabelas são as do servidor, e a busca leva a tabela', () => {
  const tabelas = [
    { id: 0, nome: 'Preço de venda padrão' },
    { id: 7, nome: 'Atacado' },
  ];

  it('UC-V397 · nome da tabela: da lista; 0/nenhuma = padrão; fora da lista = pelo id', () => {
    expect(nomeDaTabela(tabelas, 7)).toBe('Atacado');
    expect(nomeDaTabela(tabelas, null)).toBe('Preço de venda padrão');
    expect(nomeDaTabela([{ id: 7, nome: 'Atacado' }], null)).toBe('Preço padrão');
    expect(nomeDaTabela(tabelas, 9)).toBe('Tabela #9');
  });

  it('UC-V397 · a busca só manda price_group quando há tabela (Create: if (newGroupId))', () => {
    expect(parametrosDaBusca('lona', 3, 7).get('price_group')).toBe('7');
    expect(parametrosDaBusca('lona', 3, null).has('price_group')).toBe(false);
    expect(parametrosDaBusca('lona', 3, 0).has('price_group')).toBe(false);
  });

  it('UC-V397 · precoDaBusca: preço do grupo quando vem, senão o preço base', () => {
    expect(precoDaBusca({ selling_price: '19.9000', variation_group_price: '15.0000' })).toBe(15);
    expect(precoDaBusca({ selling_price: '19.9000', variation_group_price: null })).toBe(19.9);
    // fixo 0 vem como "0.0000" e vale (alinhado ao Blade em 2026-10-02)
    expect(precoDaBusca({ selling_price: '19.9000', variation_group_price: '0.0000' })).toBe(0);
  });

  it('UC-V397 · a tabela vai no payload nas duas chaves, como o Create', () => {
    const r = montarPayloadVenda({ ...fixture.entrada, tabelaId: 7 });
    expect(r.ok && r.payload.price_group).toBe(7);
    expect(r.ok && r.payload.default_price_group).toBe(7);
    const sem = montarPayloadVenda(fixture.entrada);
    expect(sem.ok && sem.payload.price_group).toBeNull();
  });
});

describe('UC-V398 · trocar a tabela reprecifica as linhas pela regra do Create', () => {
  const linhas = fixture.entrada.itens.map((l) => ({ ...l, preco: l.preco }));

  it('UC-V398 · 156,23 → 143,00 com a tabela que dá 15,00 ao Produto A (B fica no preço base)', () => {
    // O que a busca devolve com price_group: A com preço do grupo, B sem (cai no selling_price).
    const novos = new Map([
      [201, precoDaBusca({ selling_price: '19.90', variation_group_price: '15.00' })],
      [202, precoDaBusca({ selling_price: '50.00', variation_group_price: null })],
    ]);
    const r = aplicarPrecos(linhas, novos, fmtBR);
    expect(r.mantidas).toBe(0);
    expect(r.itens.map((l) => l.preco)).toEqual(['15,00', '50,00']);

    const antes = totalParaGravar(fixture.entrada);
    const depois = totalParaGravar({ ...fixture.entrada, itens: r.itens });
    expect(antes).toBe(156.23);
    expect(depois).toBe(143);

    const p = montarPayloadVenda({ ...fixture.entrada, itens: r.itens, tabelaId: 7, pagamentos: [{ m: 'Dinheiro', v: '143,00' }] });
    expect(p.ok).toBe(true);
    if (!p.ok) return;
    expect(p.total).toBe(143);
    expect((p.payload.products as { unit_price: number }[]).map((l) => l.unit_price)).toEqual([15, 50]);
  });

  it('UC-V398 · linha que a busca não devolve fica com o preço e é contada', () => {
    const r = aplicarPrecos(linhas, new Map([[201, 15]]), fmtBR);
    expect(r.itens.map((l) => l.preco)).toEqual(['15,00', '50,00']);
    expect(r.mantidas).toBe(1);
    const semVariacao = aplicarPrecos([{ variationId: null, preco: '9,00' }], new Map([[1, 1]]), fmtBR);
    expect(semVariacao.itens[0].preco).toBe('9,00');
    expect(semVariacao.mantidas).toBe(1);
  });
});

describe('UC-V399 · cliente com tabela aplica a dele; sem tabela mantém a atual', () => {
  it('UC-V399 · regra do handleCustomerSelect', () => {
    expect(tabelaAposCliente(null, 7)).toBe(7);
    expect(tabelaAposCliente(3, 7)).toBe(7);
    expect(tabelaAposCliente(3, null)).toBe(3);
    expect(tabelaAposCliente(null, null)).toBeNull();
  });
});
