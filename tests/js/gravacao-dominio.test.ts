/**
 * UC-V380 / UC-V381 / UC-V403 — a V3 grava a venda pelo payload do Create (Sells/CreateV3.casos.md).
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
import {
  ENDERECO_VAZIO,
  codigoModFrete,
  textoDoEnderecoEntrega,
  type TransporteDaVenda,
} from '@/Pages/Sells/_components/v3/entrega-dominio';

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

  it('UC-V380 · desconto da linha com vírgula vai cheio (3,333%), e o total usa a mesma conta da linha', () => {
    const item = { ...fixture.entrada.itens[0], qtd: '7', preco: '12,345', desc: '3,333' };
    const r = montarPayloadVenda({ ...fixture.entrada, itens: [item], descVal: '0,00', frete: '0,00', pagamentos: [] });
    expect(r.ok).toBe(true);
    if (!r.ok) return;
    const linha = (r.payload.products as Record<string, number>[])[0];
    // String(3.333) lido como milhar daria 3333% — a classe do num_uf. Nem isso, nem 3,33.
    expect(linha.line_discount_amount).toBe(3.333);
    expect(linha.unit_price).toBe(12.345);
    // À mão: 7 × 12,345 = 86,415 − 3,333% (2,8802…) = 83,5348… → 83,53, arredondado só no fim.
    expect(r.total).toBe(83.53);
    expect(r.total).toBe(totalDoCreate([{ q: 7, p: 12.345, d: 3.333 }], 'fixed', 0, 0));
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

  it('UC-V381 · plano de parcelas recusa, na venda e no orçamento: o store não grava plano', () => {
    const motivo = 'O plano de parcelas ainda não é gravado — remova as parcelas para salvar.';
    expect(montarPayloadVenda({ ...fixture.entrada, parcelas: 3 })).toEqual({ ok: false, motivo });
    expect(montarPayloadVenda({ ...fixture.entrada, parcelas: 3, orcamento: true })).toEqual({ ok: false, motivo });
    // Controle: sem parcelas (0 ou ausente) a venda da fixture segue gravando com o mesmo total.
    const sem = montarPayloadVenda({ ...fixture.entrada, parcelas: 0 });
    expect(sem.ok && sem.payload.final_total).toBe(156.23);
  });

  it('UC-V381 · comissão por beneficiário recusa: o store só grava um comissionado', () => {
    expect(montarPayloadVenda({ ...fixture.entrada, beneficiarios: 2 })).toEqual({
      ok: false,
      motivo: 'A comissão por beneficiário ainda não é gravada — remova os beneficiários para salvar.',
    });
    expect(montarPayloadVenda({ ...fixture.entrada, beneficiarios: 0 }).ok).toBe(true);
  });
});

describe('UC-V403 · as notas digitadas vão na gravação, como no Create', () => {
  it('UC-V403 · a nota da venda vai em sale_note e additional_notes; a interna em staff_note', () => {
    const r = montarPayloadVenda({ ...fixture.entrada, notas: { venda: 'Instalação inclusa', interna: 'Retorno por WhatsApp' } });
    expect(r.ok).toBe(true);
    if (!r.ok) return;
    expect(r.payload.sale_note).toBe('Instalação inclusa');
    expect(r.payload.additional_notes).toBe('Instalação inclusa');
    expect(r.payload.staff_note).toBe('Retorno por WhatsApp');
    // A nota não mexe em valor: o total segue o da fixture.
    expect(r.payload.final_total).toBe(156.23);
  });

  it('UC-V403 · sem nota, os três campos vão vazios, como antes (a fixture do UC-V380 não muda)', () => {
    const r = montarPayloadVenda(fixture.entrada);
    expect(r.ok && [r.payload.sale_note, r.payload.additional_notes, r.payload.staff_note]).toEqual(['', '', '']);
  });

  it('UC-V403 · o orçamento também leva as notas', () => {
    const r = montarPayloadVenda({ ...fixture.entrada, orcamento: true, notas: { venda: 'Validade 15 dias', interna: '' } });
    expect(r.ok && r.payload.sale_note).toBe('Validade 15 dias');
  });
});

describe('UC-V383 · o desconto da venda respeita o teto do usuário', () => {
  // Regra do validateDiscount do Create.tsx: % compara direto; R$ compara com teto% do subtotal.
  // Subtotal da fixture: 53,73 + 100,00 = 153,73. Teto 3% de 153,73 = 4,6119.
  it('UC-V383 · desconto fixo de 5,00 passa do teto de 3% (4,61) e recusa', () => {
    const r = montarPayloadVenda({ ...fixture.entrada, descontoMaximo: 3 });
    expect(r).toEqual({ ok: false, motivo: 'Desconto acima do seu teto de 3% do subtotal.' });
  });

  it('UC-V383 · com teto de 4% (6,15) o mesmo desconto de 5,00 grava', () => {
    expect(montarPayloadVenda({ ...fixture.entrada, descontoMaximo: 4 }).ok).toBe(true);
  });

  it('UC-V383 · em %, 8% passa do teto de 7,5% e 7,5% não passa', () => {
    const acima = montarPayloadVenda({ ...fixture.entrada, descTipo: 'percentual', descVal: '8', descontoMaximo: 7.5 });
    const noTeto = montarPayloadVenda({ ...fixture.entrada, descTipo: 'percentual', descVal: '7,5', descontoMaximo: 7.5 });
    expect(acima).toEqual({ ok: false, motivo: 'Desconto acima do seu teto de 7,5%.' });
    expect(noTeto.ok).toBe(true);
  });

  it('UC-V383 · teto nulo ou zero não limita (como o Create)', () => {
    const grande = { ...fixture.entrada, descTipo: 'percentual' as const, descVal: '90', pagamentos: [] };
    expect(montarPayloadVenda({ ...grande, descontoMaximo: null }).ok).toBe(true);
    expect(montarPayloadVenda({ ...grande, descontoMaximo: 0 }).ok).toBe(true);
  });
});

describe('UC-V400 / UC-V401 · a V3 marca a origem e grava orçamento', () => {
  it('UC-V400 · o payload da V3 leva origem_tela=venda_v3 (o store põe a venda no pipeline)', () => {
    const r = montarPayloadVenda(fixture.entrada);
    expect(r.ok && r.payload.origem_tela).toBe('venda_v3');
  });

  it('UC-V401 · orçamento vai com status quotation, sem pagamento e com o mesmo total', () => {
    const r = montarPayloadVenda({ ...fixture.entrada, orcamento: true });
    expect(r.ok).toBe(true);
    if (!r.ok) return;
    expect(r.payload.status).toBe('quotation');
    expect(r.payload.payment).toEqual([{ amount: 0, method: 'cash', paid_on: '', account_id: null, note: '' }]);
    expect(r.payload.final_total).toBe(156.23);
    expect(r.payload.origem_tela).toBe('venda_v3');
  });

  it('UC-V401 · orçamento não recusa por meio de pagamento que a venda não grava', () => {
    const pix = { ...fixture.entrada, pagamentos: [{ m: 'PIX', v: '1,00' }] };
    expect(montarPayloadVenda(pix).ok).toBe(false);
    expect(montarPayloadVenda({ ...pix, orcamento: true }).ok).toBe(true);
  });
});

describe('UC-V413 · o transporte da entrega vai na gravação', () => {
  const transporte: TransporteDaVenda = {
    modFrete: 0,
    transportadoraId: 7,
    placa: 'ABC1D23',
    uf: 'SC',
    qtdVolumes: 2,
    numeracao: '1-2',
    especie: 'Caixa',
    pesoBruto: 12.5,
    pesoLiquido: 11.75,
    statusRemessa: 'shipped',
    rastreio: 'CT-e 123',
    enderecoEntrega: 'Rua da Obra, 10 · Joinville/SC',
    recebedor: 'Quem recebe',
  };

  it('UC-V413 · com transporte, o payload leva as chaves que o store grava, e o total não muda', () => {
    const r = montarPayloadVenda({ ...fixture.entrada, transporte });
    expect(r.ok).toBe(true);
    if (!r.ok) return;
    expect(r.payload).toMatchObject({
      mod_frete: 0,
      transportadora_id: 7,
      placa: 'ABC1D23',
      uf: 'SC',
      qtd_volumes: 2,
      numeracao_volumes: '1-2',
      especie: 'Caixa',
      peso_bruto: 12.5,
      peso_liquido: 11.75,
      shipping_status: 'shipped',
      shipping_details: 'CT-e 123',
      shipping_address: 'Rua da Obra, 10 · Joinville/SC',
      delivered_to: 'Quem recebe',
    });
    expect(r.total).toBe(156.23);
    expect(r.payload.final_total).toBe(fixture.payload.final_total);
    expect(r.payload.shipping_charges).toBe(fixture.payload.shipping_charges);
  });

  it('UC-V413 · "9 — Sem ocorrência de transporte" zera o grupo de transporte, mas guarda o endereço', () => {
    const r = montarPayloadVenda({ ...fixture.entrada, transporte: { ...transporte, modFrete: 9 } });
    expect(r.ok).toBe(true);
    if (!r.ok) return;
    expect(r.payload).toMatchObject({
      mod_frete: 9, transportadora_id: null, placa: '', uf: '', qtd_volumes: 0, especie: '',
      peso_bruto: 0, peso_liquido: 0, shipping_details: '',
      shipping_address: 'Rua da Obra, 10 · Joinville/SC',
    });
  });

  it('UC-V413 · controle: sem transporte, o payload é o da fixture, sem nenhuma chave nova', () => {
    const r = montarPayloadVenda(fixture.entrada);
    expect(r.ok && r.payload).toEqual(fixture.payload);
    expect(r.ok && 'mod_frete' in r.payload).toBe(false);
  });

  it('UC-V413 · o código do frete por conta é o modFrete da NF-e, e o endereço vira uma linha', () => {
    expect(codigoModFrete('1 — Contratação por conta do Destinatário (FOB)')).toBe(1);
    expect(codigoModFrete('9 — Sem ocorrência de transporte')).toBe(9);
    expect(
      textoDoEnderecoEntrega({
        ...ENDERECO_VAZIO, logradouro: 'Rua da Obra', numero: '10', complemento: 'Galpão 2',
        bairro: 'Centro', cidade: 'Joinville', uf: 'SC', cep: '89201-601',
      }),
    ).toBe('Rua da Obra, 10 - Galpão 2 · Centro · Joinville/SC · CEP 89201-601');
    expect(textoDoEnderecoEntrega(ENDERECO_VAZIO)).toBe('');
  });
});
