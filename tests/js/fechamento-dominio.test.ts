import { describe, expect, it } from 'vitest';
import { conferirAdiantamento, concluirComSt, prepararBaseFechamento, receberCotacaoSt, separarParcelas, sinalParaGravar, type ConsultaSt } from '@/Pages/Sells/_components/v3/fechamento-dominio';
import { ratear, type Parcela } from '@/Pages/Sells/_components/v3/parcelas-dominio';

const base = { liquido: 930, imposto: { id: 1, aliquota: 18 }, impostoDesligado: false, frete: 18, despesas: [] };
const consulta: ConsultaSt = {
  location_id: 98, contact_id: 9801,
  itens: [{ variation_id: 9801, quantidade: 1, valor_unitario: 930 }],
  total_sem_st: 1140.4, frete: 18,
};

describe('Fechamento independente da Venda V3', () => {
  it('CU-SELL-12 · 1.115,40 + despesa de 25 = 1.140,40, sem imposto sobre despesa', () => {
    const antes = prepararBaseFechamento(base);
    const depois = prepararBaseFechamento({ ...base, despesas: [{ descricao: 'Embalagem', valor: 25 }] });
    expect(antes.totalSemSt).toBe(1115.4);
    expect(depois.totalSemSt).toBe(1140.4);
    expect(depois.imposto).toBe(167.4);
    // Segunda prova independente em centavos: 93.000 + 16.740 + 1.800 + 2.500.
    expect(Math.round(depois.totalSemSt * 100)).toBe(93000 + 16740 + 1800 + 2500);
    expect(depois.campos.additional_expense_value_1).toBe(25);
    expect(depois.campos.additional_expense_key_4).toBe('');
    expect(() => prepararBaseFechamento({ ...base, despesas: Array(5).fill({ descricao: '', valor: 1 }) })).toThrow('quatro');
  });

  it('Configuração disable_order_tax remove imposto e ID; não usa 18% de cena', () => {
    const r = prepararBaseFechamento({ ...base, impostoDesligado: true });
    expect(r.imposto).toBe(0);
    expect(r.campos.tax_rate_id).toBeNull();
    expect(r.totalSemSt).toBe(948);
    expect(prepararBaseFechamento({ ...base, imposto: null }).totalSemSt).toBe(948);
  });

  it('UC-VST-02 · ST do servidor entra uma vez: venda 1.272,40 = base 1.140,40 + ST 132', () => {
    const cotacao = receberCotacaoSt(consulta, { icms_st: '132.00' });
    const r = concluirComSt(consulta, cotacao);
    expect(r).toEqual({ icms_st_valor: 132, final_total: 1272.4 });
    expect(Math.round(r.final_total * 100)).toBe(114040 + 13200);
    expect(r.final_total - r.icms_st_valor).toBeCloseTo(consulta.total_sem_st, 2);
  });

  it('UC-VST-01/06 · não infere ST por UF; resposta zero também precisa corresponder ao pedido atual', () => {
    const cotacao = receberCotacaoSt(consulta, { icms_st: '0.00' });
    expect(concluirComSt(consulta, cotacao).final_total).toBe(1140.4);
    expect(() => concluirComSt(consulta, null)).toThrow('Aguarde');
    for (const alterada of [
      { ...consulta, contact_id: 9802 }, { ...consulta, location_id: 99 },
      { ...consulta, total_sem_st: 1200 }, { ...consulta, frete: 20 },
      { ...consulta, itens: [{ ...consulta.itens[0], quantidade: 2 }] },
    ]) expect(() => concluirComSt(alterada, cotacao)).toThrow('atual');
    expect(() => receberCotacaoSt(consulta, { icms_st: '1.234,56' })).toThrow('inválida');
  });

  it('UC-V392 · arredonda só depois de despesas e ST, pelo domínio já existente', () => {
    const cotacao = receberCotacaoSt(consulta, { icms_st: '132.03' });
    expect(concluirComSt(consulta, cotacao, 0.05)).toEqual({
      icms_st_valor: 132.03, final_total: 1272.45, round_off_amount: 0.02,
    });
    // 114.040 + 13.203 = 127.243 centavos; múltiplo de 5 mais próximo = 127.245.
    expect(114040 + 13203 + 2).toBe(127245);
  });

  it('UC-SCOND-02/03 · vazio herda, zero dispensa e 40% não muda o total', () => {
    expect(sinalParaGravar('')).toBeNull();
    expect(sinalParaGravar('0')).toBe(0);
    expect(sinalParaGravar('40,5')).toBe(40.5);
    expect(sinalParaGravar('100')).toBe(100);
    for (const invalido of ['101', '-1', 'abc', '1.000', '40,555']) {
      expect(() => sinalParaGravar(invalido)).toThrow('sinal');
    }
  });

  it('Fechamento · adiantamento nunca excede o saldo nem o restante e não reduz o preço', () => {
    expect(conferirAdiantamento(40, 50, 60)).toBe(40);
    expect(10000 - Math.round(conferirAdiantamento(40, 50, 60) * 100)).toBe(6000);
    expect(() => conferirAdiantamento(50.01, 50, 100)).toThrow('saldo');
    expect(() => conferirAdiantamento(60.01, 100, 60)).toThrow('saldo');
    for (const n of [-1, Infinity, NaN]) expect(() => conferirAdiantamento(n, 100, 100)).toThrow('inválido');
  });

  it('CU-SELL-09 · três parcelas conservam 100; só 33,34 recebidos, 66,66 continuam a receber', () => {
    const valores = ratear(100, 3);
    const parcelas = valores.map((valor, i) => ({ valor: valor.toFixed(2).replace('.', ','), lanc: i === 0 ? 'RECEBIDA' : 'A RECEBER' })) as Parcela[];
    const r = separarParcelas(parcelas);
    expect(r.pago).toBe(33.34);
    expect(r.previsto).toBe(66.66);
    expect(Math.round(r.pago * 100) + Math.round(r.previsto * 100)).toBe(10000);
    expect(r.recebidas).toHaveLength(1);
    expect(r.aReceber).toHaveLength(2);
  });
});
