/**
 * UC-V387 / UC-V388 — configurações do business na gravação da V3 (Sells/CreateV3.casos.md).
 *
 * Cada caso cita a regra do Blade/pos.js de onde veio; a V3 não inventa regra.
 */

import { describe, expect, it } from 'vitest';

import {
  bloqueioDaConfig,
  linhaParaSomar,
  type ConfigVenda,
  type LinhaLancavel,
} from '@/Pages/Sells/_components/v3/config-business-dominio';
import { montarPayloadVenda } from '@/Pages/Sells/_components/v3/gravacao-dominio';

const base: ConfigVenda = {
  dataEditavel: false,
  descontoDaVenda: true,
  descontoPadrao: 0,
  impostoDoPedido: true,
  precoMinimo: false,
  venderSemEstoque: false,
  comissionado: { tipo: null, obrigatorio: false },
  prazoObrigatorio: false,
  rascunho: true,
  aPrazo: true,
  recorrente: true,
  arredondamento: 0,
  somaNaLinha: true,
};
const semPrazo = { numero: null, tipo: null };

describe('UC-V387 · comissionado e prazo obrigatórios impedem a gravação', () => {
  it('UC-V387 · comissionado obrigatório com o select do Blade (user / cmsn_agnt) bloqueia', () => {
    for (const tipo of ['user', 'cmsn_agnt'] as const) {
      const c = { ...base, comissionado: { tipo, obrigatorio: true } };
      expect(bloqueioDaConfig(c, semPrazo)).toMatch(/comissionado/);
    }
  });

  it('UC-V387 · comissionado = usuário logado não bloqueia: o store põe o próprio usuário', () => {
    const c = { ...base, comissionado: { tipo: 'logged_in_user' as const, obrigatorio: true } };
    expect(bloqueioDaConfig(c, semPrazo)).toBeNull();
  });

  it('UC-V387 · comissionado desligado ou não obrigatório não bloqueia', () => {
    expect(bloqueioDaConfig({ ...base, comissionado: { tipo: null, obrigatorio: true } }, semPrazo)).toBeNull();
    expect(bloqueioDaConfig({ ...base, comissionado: { tipo: 'user', obrigatorio: false } }, semPrazo)).toBeNull();
  });

  it('UC-V387 · prazo obrigatório bloqueia sem prazo no cliente e libera com prazo', () => {
    const c = { ...base, prazoObrigatorio: true };
    expect(bloqueioDaConfig(c, semPrazo)).toMatch(/prazo/);
    expect(bloqueioDaConfig(c, { numero: '', tipo: 'days' })).toMatch(/prazo/);
    expect(bloqueioDaConfig(c, { numero: 30, tipo: null })).toMatch(/prazo/);
    expect(bloqueioDaConfig(c, { numero: 30, tipo: 'days' })).toBeNull();
    expect(bloqueioDaConfig(base, semPrazo)).toBeNull();
  });

  it('UC-V387 · o prazo do cliente vai no payload; sem prazo segue vazio como antes', () => {
    const venda = {
      locationId: 1,
      contactId: 2,
      transactionDate: '30/10/2026 10:00',
      itens: [
        { productId: 5, variationId: 6, nome: 'X', sku: 'X', qtd: '1', preco: '10,00', desc: '0', acr: '0', enableStock: false, tipoProduto: 'single' },
      ],
      descTipo: 'percentual' as const,
      descVal: '0',
      acr: '0',
      frete: '0',
      pagamentos: [],
    };
    const com = montarPayloadVenda({ ...venda, prazo: { numero: 30, tipo: 'days' } });
    const sem = montarPayloadVenda(venda);
    expect(com.ok && com.payload.pay_term_number).toBe(30);
    expect(com.ok && com.payload.pay_term_type).toBe('days');
    expect(sem.ok && sem.payload.pay_term_number).toBe('');
    expect(sem.ok && sem.payload.pay_term_type).toBe('days');
  });
});

const linha = (o: Partial<LinhaLancavel>): LinhaLancavel => ({
  variationId: 6,
  medidas: null,
  qtd: '1',
  preco: '10,00',
  desc: '0',
  acr: '0',
  ...o,
});

describe('UC-V388 · lançar o mesmo produto soma na linha conforme item_addition_method', () => {
  const itens = [linha({ variationId: 9 }), linha({ variationId: 6, qtd: '2' })];

  it('UC-V388 · método 1: o mesmo produto, mesmo preço, cai na linha existente', () => {
    expect(linhaParaSomar(itens, linha({}), true)).toBe(1);
  });

  it('UC-V388 · método 0: sempre linha nova', () => {
    expect(linhaParaSomar(itens, linha({}), false)).toBe(-1);
  });

  it('UC-V388 · preço, desconto ou acréscimo diferentes abrem linha nova (somar gravaria pelo preço antigo)', () => {
    expect(linhaParaSomar(itens, linha({ preco: '9,90' }), true)).toBe(-1);
    expect(linhaParaSomar(itens, linha({ desc: '5' }), true)).toBe(-1);
    expect(linhaParaSomar(itens, linha({ acr: '2' }), true)).toBe(-1);
    expect(linhaParaSomar(itens, linha({ preco: '10' }), true)).toBe(1); // mesmo valor, outra grafia
  });

  it('UC-V388 · item com medidas (dimensional) e item sem variação não somam', () => {
    expect(linhaParaSomar(itens, linha({ medidas: '1× 0,50×0,10m' }), true)).toBe(-1);
    expect(linhaParaSomar([linha({ medidas: '1× 0,50×0,10m' })], linha({}), true)).toBe(-1);
    expect(linhaParaSomar(itens, linha({ variationId: null }), true)).toBe(-1);
  });
});
