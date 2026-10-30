/**
 * UC-V380 / UC-V381 — a V3 grava a venda pelo payload do Create (Sells/CreateV3.casos.md).
 *
 * Três caminhos para o MESMO total, e nenhum lê o outro:
 *   1. conta à mão (no `_leia` da fixture): 156,23;
 *   2. a fórmula do `totalGeral` do Create.tsx, reescrita AQUI a partir das linhas 323-360
 *      (main @ e322a8117a) — não importa a tela viva (UC-V303), copia a conta;
 *   3. `montarPayloadVenda`, que é o que a V3 posta.
 * O Pest (`SellsCreateV3GravarContratoTest`) posta o `payload` desta fixture em `/pos` e
 * confere o gravado contra a mesma venda feita pelo payload do Create.
 */

import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

import { montarPayloadVenda, totalParaGravar, type VendaParaGravar } from '@/Pages/Sells/_components/v3/gravacao-dominio';

const fixture = JSON.parse(readFileSync('tests/fixtures/sells-v3/venda-simples.json', 'utf8')) as {
  entrada: VendaParaGravar;
  total: number;
  payload: Record<string, unknown>;
};

/** Create.tsx `totalGeral` com os números já em float, como o useForm guarda. */
function totalDoCreate(produtos: { q: number; p: number; d: number }[], tipo: 'percentage' | 'fixed', valor: number, frete: number) {
  const sub = produtos.reduce((acc, x) => acc + Math.max(x.q * x.p - (x.q * x.p * x.d) / 100, 0), 0);
  const desc = tipo === 'percentage' ? (sub * valor) / 100 : valor;
  return Math.round(Math.max(sub - desc - 0 + frete + 0, 0) * 100) / 100;
}

describe('UC-V380 · a V3 monta o payload do Create', () => {
  it('UC-V380 · o pedido da fixture vira exatamente o payload esperado, com total 156,23', () => {
    const r = montarPayloadVenda(fixture.entrada);
    expect(r.ok).toBe(true);
    if (!r.ok) return;
    expect(r.payload).toEqual(fixture.payload);
    expect(r.total).toBe(156.23);
    expect(fixture.total).toBe(156.23);
  });

  it('UC-V380 · o total bate com a fórmula do Create em pedidos com desconto percentual e frete', () => {
    const casos: { venda: VendaParaGravar; create: number }[] = [
      { venda: { ...fixture.entrada }, create: totalDoCreate([{ q: 3, p: 19.9, d: 10 }, { q: 2, p: 50, d: 0 }], 'fixed', 5, 7.5) },
      {
        venda: { ...fixture.entrada, descTipo: 'percentual', descVal: '7,5', frete: '0,00' },
        create: totalDoCreate([{ q: 3, p: 19.9, d: 10 }, { q: 2, p: 50, d: 0 }], 'percentage', 7.5, 0),
      },
      {
        venda: { ...fixture.entrada, itens: [{ ...fixture.entrada.itens[0], qtd: '1,333', preco: '1.234,56', desc: '12,5' }], descVal: '0,00' },
        create: totalDoCreate([{ q: 1.333, p: 1234.56, d: 12.5 }], 'fixed', 0, 7.5),
      },
    ];
    for (const c of casos) expect(totalParaGravar(c.venda)).toBe(c.create);
    // 3ª linha à mão: 1,333 × 1.234,56 = 1.645,668 − 12,5% = 1.439,9600 + 7,50 = 1.447,46
    expect(casos[2].create).toBe(1447.46);
  });

  it('UC-V380 · o total vai com no máximo 2 casas, nunca float de 5 casas (incidente num_uf)', () => {
    const r = montarPayloadVenda({ ...fixture.entrada, descTipo: 'percentual', descVal: '3', frete: '0,00' });
    expect(r.ok).toBe(true);
    if (!r.ok) return;
    const t = r.payload.final_total as number;
    expect(Math.round(t * 100) / 100).toBe(t);
  });
});

describe('UC-V381 · o que o store não sabe gravar é recusado, não descartado', () => {
  it('UC-V381 · acréscimo na linha ou na venda recusa', () => {
    const linha = montarPayloadVenda({ ...fixture.entrada, itens: [{ ...fixture.entrada.itens[0], acr: '5' }] });
    const venda = montarPayloadVenda({ ...fixture.entrada, acr: '2,00' });
    expect(linha).toEqual({ ok: false, motivo: 'Acréscimo ainda não é gravado — zere o acréscimo para salvar.' });
    expect(venda.ok).toBe(false);
  });

  it('UC-V381 · PIX recusa com o nome do método; item sem id do cadastro recusa', () => {
    expect(montarPayloadVenda({ ...fixture.entrada, pagamentos: [{ m: 'PIX', v: '1,00' }] })).toEqual({
      ok: false,
      motivo: 'PIX ainda não é gravado — use Dinheiro ou Cartão.',
    });
    const semId = montarPayloadVenda({ ...fixture.entrada, itens: [{ ...fixture.entrada.itens[0], variationId: null }] });
    expect(semId).toEqual({ ok: false, motivo: 'Produto A não veio do cadastro de produtos.' });
  });

  it('UC-V381 · venda sem item recusa; sem pagamento vai com a linha zerada do Create (venda a prazo)', () => {
    expect(montarPayloadVenda({ ...fixture.entrada, itens: [] }).ok).toBe(false);
    const r = montarPayloadVenda({ ...fixture.entrada, pagamentos: [] });
    expect(r.ok && r.payload.payment).toEqual([{ amount: 0, method: 'cash', paid_on: '', account_id: null, note: '' }]);
  });
});
