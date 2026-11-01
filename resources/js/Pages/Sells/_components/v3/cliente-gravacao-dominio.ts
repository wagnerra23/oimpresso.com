// Cliente de verdade na Venda V3 gravando (grupo 3a da paridade · UC-V393..V395).
//
// A consulta de clientes da cena lista cadastros fixos; gravando, ela passa a buscar em
// `/contacts/customers` — o MESMO endpoint do `CustomerSearchAutocomplete` do Create, que
// filtra por `contacts.business_id` da sessão (ContactController@getCustomers, ADR 0093).
// Aqui mora só a tradução da resposta e o que a troca de cliente impede de gravar.
//
// ⚠️ VALOR: daqui sai só o `grupoPrecoId` do cliente. Quem reprecifica as linhas por ele é a
// Page, pela regra do Create (`tabela-preco-dominio.ts`, UC-V398).

import type { ClienteConsulta } from './cliente-consulta-dominio';
import type { PrazoDoCliente } from './config-business-dominio';

/** O que `/contacts/customers` devolve e a V3 usa. Os demais campos são ignorados. */
export type ClienteDaBusca = {
  id: number;
  text: string;
  mobile?: string | null;
  city?: string | null;
  state?: string | null;
  address_line_1?: string | null;
  zip_code?: string | null;
  supplier_business_name?: string | null;
  pay_term_number?: number | string | null;
  pay_term_type?: string | null;
  selling_price_group_id?: number | string | null;
  sinal_minimo_pct?: number | string | null;
  contact_id?: string | null;
};

/** O cliente da venda gravando: o cadastro para a tela e o que vai no payload. */
export type ClienteDaGravacao = {
  id: number;
  consulta: ClienteConsulta;
  prazo: PrazoDoCliente;
  /** Tabela de preço do cliente (própria ou do grupo); null = vale o padrão. */
  grupoPrecoId: number | null;
  /** `contacts.sinal_minimo_pct` — o Create preenche a venda com ele; '' = sem sinal. */
  sinal: string;
};

const vazio = (v: unknown): boolean => v === null || v === undefined || String(v).trim() === '';
const ou = (v: string | null | undefined, padrao = '—'): string => (vazio(v) ? padrao : String(v).trim());

/** `"Nome (CO0042)"` → `"CO0042"`; sem código entre parênteses, devolve o id. */
function codigoDe(c: ClienteDaBusca): string {
  if (!vazio(c.contact_id)) return String(c.contact_id);
  const m = /\(([^()]+)\)\s*$/.exec(c.text);
  return m?.[1] ?? String(c.id);
}

/**
 * Traduz um resultado de `/contacts/customers` para o cliente da gravação. O que a busca não
 * traz (IE, regime, e-mail) entra como `—`, nunca inventado — mesma regra do cadastro mínimo.
 * Prazo e sinal seguem o `handleCustomerSelect` do Create: prazo só com tipo `days`/`months`.
 */
export function clienteDaBusca(c: ClienteDaBusca): ClienteDaGravacao {
  const tipo = c.pay_term_type === 'days' || c.pay_term_type === 'months' ? c.pay_term_type : null;
  const grupo = vazio(c.selling_price_group_id) ? null : Number(c.selling_price_group_id);
  const nome = String(c.text).replace(/\s*\([^()]*\)\s*$/, '').trim() || String(c.text);
  const temTabela = grupo !== null && Number.isFinite(grupo) && grupo > 0;
  return {
    id: c.id,
    consulta: {
      cod: codigoDe(c),
      nome,
      padrao: false,
      doc: '—',
      ie: '—',
      contrib: 'nao',
      regime: '—',
      fone: ou(c.mobile),
      email: '—',
      emailNfe: '—',
      contato: ou(c.supplier_business_name, nome),
      endereco: ou([c.address_line_1, c.zip_code].filter((x) => !vazio(x)).join(' · ') || null),
      cidade: ou(c.city),
      uf: ou(c.state),
      grupo: '—',
      prazo: vazio(c.pay_term_number) || !tipo ? 'À vista' : `${c.pay_term_number} ${tipo === 'days' ? 'dias' : 'meses'}`,
      // O nome da tabela é a Page que dá (ela tem a lista); aqui a consulta só sabe o id.
      tabela: null,
    },
    prazo: { numero: vazio(c.pay_term_number) ? null : c.pay_term_number ?? null, tipo },
    grupoPrecoId: temTabela ? grupo : null,
    sinal: vazio(c.sinal_minimo_pct) ? '' : String(Number(c.sinal_minimo_pct)),
  };
}

/**
 * Corpo do POST /contacts para o cadastro mínimo — o mesmo do `QuickAddCustomerSheet` do
 * Create. Sem tabela de preço e sem prazo: cliente novo nasce no padrão.
 */
export function corpoNovoCliente(dados: { nome: string; doc?: string; fone?: string }): Record<string, unknown> {
  return {
    type: 'customer',
    contact_type_radio: 'customer',
    first_name: dados.nome.trim(),
    mobile: (dados.fone ?? '').trim() || null,
    cpf_cnpj: (dados.doc ?? '').trim() || null,
    pay_term_number: null,
    pay_term_type: null,
    credit_limit: '',
    opening_balance: '0',
  };
}
