/**
 * UC-V393..UC-V395 — cliente de verdade e comissionado na gravação da V3 (Sells/CreateV3.casos.md).
 *
 * As regras vêm do `handleCustomerSelect` do Sells/Create.tsx (prazo, sinal, tabela) e do
 * `sell/create.blade.php` (select de comissionado). A V3 não inventa regra.
 */

import { describe, expect, it } from 'vitest';

import {
  clienteDaBusca,
  corpoNovoCliente,
} from '@/Pages/Sells/_components/v3/cliente-gravacao-dominio';
import { bloqueioDaConfig, temSelectDeComissionado, type ConfigVenda } from '@/Pages/Sells/_components/v3/config-business-dominio';
import { montarPayloadVenda } from '@/Pages/Sells/_components/v3/gravacao-dominio';

const config: ConfigVenda = {
  dataEditavel: false,
  descontoDaVenda: true,
  descontoPadrao: 0,
  impostoDoPedido: true,
  precoMinimo: false,
  venderSemEstoque: true,
  comissionado: { tipo: null, obrigatorio: false },
  prazoObrigatorio: false,
  rascunho: true,
  aPrazo: true,
  recorrente: true,
  arredondamento: 0,
  somaNaLinha: false,
};

const venda = {
  locationId: 3,
  contactId: 10,
  transactionDate: '30/10/2026 10:00',
  itens: [{ productId: 7, variationId: 70, nome: 'Lona', sku: 'L1', qtd: '2', preco: '50,00', desc: '0', acr: '0', enableStock: false, tipoProduto: 'single' }],
  descTipo: 'percentual' as const,
  descVal: '0',
  acr: '0',
  frete: '0',
  pagamentos: [{ m: 'Dinheiro', v: '100,00' }],
  config,
};

describe('UC-V393 · a consulta escolhe o cliente de verdade, e ele vai no payload', () => {
  const daBusca = {
    id: 42,
    text: 'Gráfica Exemplo (CO0042)',
    mobile: '47999990000',
    city: 'Joinville',
    state: 'SC',
    pay_term_number: '30',
    pay_term_type: 'days',
    selling_price_group_id: null,
    sinal_minimo_pct: '20.00',
  };

  it('UC-V393 · traduz o resultado de /contacts/customers: id, código, prazo e sinal', () => {
    const c = clienteDaBusca(daBusca);
    expect(c.id).toBe(42);
    expect(c.consulta.cod).toBe('CO0042');
    expect(c.consulta.nome).toBe('Gráfica Exemplo');
    expect(c.consulta.cidade).toBe('Joinville');
    expect(c.prazo).toEqual({ numero: '30', tipo: 'days' });
    expect(c.sinal).toBe('20');
    expect(c.grupoPrecoId).toBeNull();
    // o que a busca não traz entra como —, nunca inventado
    expect(c.consulta.doc).toBe('—');
  });

  it('UC-V393 · prazo só vale com tipo days/months, como o handleCustomerSelect do Create', () => {
    expect(clienteDaBusca({ ...daBusca, pay_term_type: 'weeks' }).prazo.tipo).toBeNull();
    expect(clienteDaBusca({ ...daBusca, pay_term_number: null, pay_term_type: null }).prazo).toEqual({ numero: null, tipo: null });
  });

  it('UC-V393 · o cliente escolhido vai como contact_id, com o prazo e o sinal dele', () => {
    const c = clienteDaBusca(daBusca);
    const r = montarPayloadVenda({ ...venda, contactId: c.id, prazo: c.prazo, sinal: c.sinal });
    expect(r.ok).toBe(true);
    if (!r.ok) return;
    expect(r.payload.contact_id).toBe(42);
    expect(r.payload.pay_term_number).toBe('30');
    expect(r.payload.pay_term_type).toBe('days');
    expect(r.payload.sinal_minimo_pct).toBe(20);
    // trocar de cliente sem tabela não muda valor: mesmo total do cliente padrão
    const padrao = montarPayloadVenda(venda);
    expect(padrao.ok && r.total === padrao.total).toBe(true);
  });

  it('UC-V393 · a tabela do cliente vem como id (0 ou vazio = sem tabela)', () => {
    expect(clienteDaBusca({ ...daBusca, selling_price_group_id: 5 }).grupoPrecoId).toBe(5);
    expect(clienteDaBusca({ ...daBusca, selling_price_group_id: '0' }).grupoPrecoId).toBeNull();
    expect(clienteDaBusca({ ...daBusca, selling_price_group_id: null }).grupoPrecoId).toBeNull();
  });
});

describe('UC-V394 · o cadastro mínimo grava pelo mesmo POST /contacts do Create', () => {
  it('UC-V394 · cliente novo nasce sem prazo e sem tabela', () => {
    expect(corpoNovoCliente({ nome: '  Ana  ', doc: '', fone: '47 9' })).toEqual({
      type: 'customer',
      contact_type_radio: 'customer',
      first_name: 'Ana',
      mobile: '47 9',
      cpf_cnpj: null,
      pay_term_number: null,
      pay_term_type: null,
      credit_limit: '',
      opening_balance: '0',
    });
  });
});

describe('UC-V395 · comissionado escolhido destrava a gravação e vai no payload', () => {
  const obrigatorio = { ...config, comissionado: { tipo: 'user' as const, obrigatorio: true } };

  it('UC-V395 · o select só existe com sales_cmsn_agnt = user ou cmsn_agnt', () => {
    expect(temSelectDeComissionado(obrigatorio)).toBe(true);
    expect(temSelectDeComissionado({ ...config, comissionado: { tipo: 'cmsn_agnt', obrigatorio: false } })).toBe(true);
    expect(temSelectDeComissionado({ ...config, comissionado: { tipo: 'logged_in_user', obrigatorio: true } })).toBe(false);
    expect(temSelectDeComissionado(config)).toBe(false);
  });

  it('UC-V395 · obrigatório sem escolha bloqueia; com escolha, passa', () => {
    const semPrazo = { numero: null, tipo: null };
    expect(bloqueioDaConfig(obrigatorio, semPrazo)).toMatch(/comissionado/);
    expect(bloqueioDaConfig(obrigatorio, semPrazo, 0)).toMatch(/comissionado/);
    expect(bloqueioDaConfig(obrigatorio, semPrazo, 8)).toBeNull();
  });

  it('UC-V395 · o escolhido vai como commission_agent; sem escolha vai null', () => {
    const r = montarPayloadVenda({ ...venda, config: obrigatorio, comissionadoId: 8 });
    expect(r.ok && r.payload.commission_agent).toBe(8);
    const livre = montarPayloadVenda(venda);
    expect(livre.ok && livre.payload.commission_agent).toBeNull();
  });
});
