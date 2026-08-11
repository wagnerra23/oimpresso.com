/**
 * Prova do domínio da consulta de clientes — onda extra do preview `/sells/create-v3`.
 *
 * ⚠️ O QUE ESTE ARQUIVO GUARDA, E O QUE ELE NÃO PODE GUARDAR
 * A consulta **não calcula dinheiro** — então aqui não há prova de valor a fazer, e
 * inventar uma seria teatro. O que existe de risco Tier 0 nesta onda é o CONTRÁRIO:
 * a garantia de que escolher cliente **não reprecifica a venda**. Essa garantia mora
 * em duas metades:
 *   - a metade estrutural, testável e testada aqui: `criarClienteDeCadastroMinimo`
 *     devolve `tabela: null` (cai no padrão do balcão, não impõe tabela nova);
 *   - a metade de integração, MEDIDA e não testável em vitest: `tabelaCadastro`/
 *     `tabelaAtiva`/`tabelaTrocada` só aparecem no cartão "Tabela de preço" da Page,
 *     nunca em `linhaTotal`/`subtotal`/`total` (medido por `grep` em `CreateV3.tsx`).
 * Declaro a segunda como medição, não como teste — chamar de "provado" o que só foi
 * medido é a classe LC-08.
 *
 * As asserções de busca partem da DEFINIÇÃO (o que a copy do placeholder promete),
 * não do retorno da implementação — teste que só confere que `filtrarClientes`
 * devolve o que `filtrarClientes` devolve é tautológico (lápide de 2026-06-05).
 */

import { describe, expect, it } from 'vitest';

import {
  GRUPO_PADRAO,
  cadastroMinimoValido,
  criarClienteDeCadastroMinimo,
  filtrarClientes,
  proximoCodigo,
  rotuloIcmsCurto,
  rotuloIcmsLongo,
  tomIcms,
  type ClienteConsulta,
} from '@/Pages/Sells/_components/v3/cliente-consulta-dominio';

/** Base mínima de cena. Documentos SINTÉTICOS com DV medido inválido. */
const base = (over: Partial<ClienteConsulta> = {}): ClienteConsulta => ({
  cod: '0001',
  nome: 'Consumidor final',
  padrao: true,
  doc: '—',
  ie: 'ISENTO',
  contrib: 'nao',
  regime: 'Simples Nacional',
  fone: '—',
  email: '—',
  emailNfe: '—',
  contato: '—',
  endereco: 'Venda no balcão',
  cidade: 'Termas do Gravatal',
  uf: 'SC',
  grupo: 'Varejo',
  prazo: 'À vista',
  tabela: null,
  ...over,
});

const LISTA: ClienteConsulta[] = [
  base(),
  base({
    cod: '0142',
    nome: 'Autarquia de Saneamento Serra Verde',
    padrao: false,
    doc: '29.417.508/0001-62', // pii-allowlist — CNPJ de CENA, DV inválido (medido)
    ie: '255.618.240',
    contrib: 'isento',
    cidade: 'Joinville',
    grupo: 'Governo',
    tabela: 'Governo 2026 — pregão 041/2026',
  }),
  base({
    cod: '0288',
    nome: 'Atacado Vale do Itajaí Ltda',
    padrao: false,
    doc: '41.882.507/0001-44', // pii-allowlist — CNPJ de CENA, DV inválido (medido)
    ie: '254.099.771',
    contrib: 'sim',
    cidade: 'Blumenau',
    grupo: 'Atacado',
    tabela: 'Atacado — a partir de 50m²',
  }),
];

describe('filtrarClientes — a busca cumpre a copy do placeholder', () => {
  it('termo vazio (ou só espaço) devolve a lista inteira, na ordem original', () => {
    expect(filtrarClientes(LISTA, '')).toEqual(LISTA);
    expect(filtrarClientes(LISTA, '   ')).toEqual(LISTA);
  });

  it('acha por NOME, sem depender de caixa', () => {
    expect(filtrarClientes(LISTA, 'atacado').map((c) => c.cod)).toEqual(['0288']);
    expect(filtrarClientes(LISTA, 'ATACADO').map((c) => c.cod)).toEqual(['0288']);
  });

  it('acha por CÓDIGO', () => {
    expect(filtrarClientes(LISTA, '0142').map((c) => c.nome)).toEqual([
      'Autarquia de Saneamento Serra Verde',
    ]);
  });

  it('acha por CIDADE', () => {
    expect(filtrarClientes(LISTA, 'blumenau').map((c) => c.cod)).toEqual(['0288']);
  });

  /* As duas divergências CONSCIENTES do protótipo — cada uma existe porque a copy
     "Buscar por nome, CNPJ/CPF, cidade ou código…" promete, e o `includes` literal
     da fonte não entrega. */
  it('DIVERGÊNCIA 1 — documento casa SEM máscara (o operador digita do papel)', () => {
    expect(filtrarClientes(LISTA, '29417508').map((c) => c.cod)).toEqual(['0142']);
    expect(filtrarClientes(LISTA, '41882507000144').map((c) => c.cod)).toEqual(['0288']);
    // e continua achando COM máscara — a divergência soma, não substitui
    expect(filtrarClientes(LISTA, '29.417.508').map((c) => c.cod)).toEqual(['0142']);
  });

  it('DIVERGÊNCIA 2 — texto casa SEM acento (`itajai` acha `Itajaí`)', () => {
    expect(filtrarClientes(LISTA, 'itajai').map((c) => c.cod)).toEqual(['0288']);
    expect(filtrarClientes(LISTA, 'Itajaí').map((c) => c.cod)).toEqual(['0288']);
  });

  /* Controle NEGATIVO do eixo numérico: sem esse guard, `soDigitos('atacado')` daria
     `''` e `''.includes('')` casaria TODA linha — a busca devolveria a lista inteira
     para qualquer termo textual, que é o bug silencioso desta implementação. */
  it('controle negativo — termo sem dígito não vaza pelo eixo numérico', () => {
    expect(filtrarClientes(LISTA, 'zzzz')).toEqual([]);
    expect(filtrarClientes(LISTA, 'consumidor').map((c) => c.cod)).toEqual(['0001']);
  });

  it('termo que não casa nada devolve lista vazia — não a lista inteira', () => {
    expect(filtrarClientes(LISTA, '99.999.999/9999-99')).toEqual([]); // pii-allowlist — dígito repetido, rejeitado por qualquer validador de DV: não é documento
  });
});

describe('rótulos de ICMS — três estados, duas grafias', () => {
  it('a forma curta é a da tabela densa', () => {
    expect(rotuloIcmsCurto('sim')).toBe('contribuinte');
    expect(rotuloIcmsCurto('isento')).toBe('isento');
    expect(rotuloIcmsCurto('nao')).toBe('não contrib.');
  });

  it('a forma longa é a da grade de detalhes', () => {
    expect(rotuloIcmsLongo('sim')).toBe('Contribuinte');
    expect(rotuloIcmsLongo('isento')).toBe('Isento');
    expect(rotuloIcmsLongo('nao')).toBe('Não contribuinte');
  });

  it('o tom do pill distingue os três — isento não pode parecer contribuinte', () => {
    expect(tomIcms('sim')).toBe('success');
    expect(tomIcms('isento')).toBe('warning');
    expect(tomIcms('nao')).toBe('neutro');
    expect(new Set(['sim', 'isento', 'nao'].map((c) => tomIcms(c as never))).size).toBe(3);
  });
});

describe('proximoCodigo — sequencial com a largura preservada', () => {
  it('continua a sequência sem perder o zero à esquerda', () => {
    expect(proximoCodigo(LISTA)).toBe('0289');
  });

  it('lista vazia começa em 0001', () => {
    expect(proximoCodigo([])).toBe('0001');
  });

  it('usa o MAIOR código, não o último da lista', () => {
    const fora = [base({ cod: '0900' }), base({ cod: '0100' })];
    expect(proximoCodigo(fora)).toBe('0901');
  });

  it('não colide com nenhum código já existente', () => {
    const novo = proximoCodigo(LISTA);
    expect(LISTA.map((c) => c.cod)).not.toContain(novo);
  });
});

describe('cadastro mínimo — só o nome é obrigatório', () => {
  it('nome em branco (ou só espaço) não vale', () => {
    expect(cadastroMinimoValido({ nome: '' })).toBe(false);
    expect(cadastroMinimoValido({ nome: '   ' })).toBe(false);
  });

  it('nome preenchido vale, mesmo sem documento', () => {
    expect(cadastroMinimoValido({ nome: 'Padaria do Bairro' })).toBe(true);
  });
});

describe('criarClienteDeCadastroMinimo', () => {
  it('TIER 0 — cliente novo NÃO nasce com tabela de preço', () => {
    const c = criarClienteDeCadastroMinimo({ nome: 'Padaria do Bairro' }, LISTA);

    // `null` = cai no padrão do balcão. Qualquer string aqui seria reprecificar a
    // venda por um cadastro que ninguém conferiu.
    expect(c.tabela).toBeNull();
  });

  it('o que não foi perguntado entra como travessão — nunca inventado', () => {
    const c = criarClienteDeCadastroMinimo({ nome: 'Padaria do Bairro' }, LISTA);

    expect(c.doc).toBe('—');
    expect(c.cidade).toBe('—');
    expect(c.uf).toBe('—');
    expect(c.email).toBe('—');
    // sem documento não dá pra afirmar situação de ICMS
    expect(c.contrib).toBe('nao');
  });

  it('aparece na consulta imediatamente — é o que "volta já selecionado" exige', () => {
    const c = criarClienteDeCadastroMinimo({ nome: 'Padaria do Bairro' }, LISTA);
    const comNovo = [...LISTA, c];

    expect(filtrarClientes(comNovo, 'padaria').map((x) => x.cod)).toEqual([c.cod]);
    expect(filtrarClientes(comNovo, c.cod)).toHaveLength(1);
  });

  it('preserva o que FOI preenchido, sem os espaços das pontas', () => {
    const c = criarClienteDeCadastroMinimo(
      { nome: '  Padaria do Bairro  ', doc: ' 123 ', fone: ' (47) 9 ', grupo: 'Atacado' },
      LISTA,
    );

    expect(c.nome).toBe('Padaria do Bairro');
    expect(c.doc).toBe('123');
    expect(c.fone).toBe('(47) 9');
    expect(c.grupo).toBe('Atacado');
    // o contato assume o nome já limpo, não o cru
    expect(c.contato).toBe('Padaria do Bairro');
  });

  it('sem grupo escolhido, cai no primeiro — e nunca em vazio (Radix derruba o render)', () => {
    const c = criarClienteDeCadastroMinimo({ nome: 'Padaria do Bairro' }, LISTA);

    expect(c.grupo).toBe(GRUPO_PADRAO);
    expect(c.grupo.trim()).not.toBe('');
  });

  it('cliente criado nunca é o padrão do balcão', () => {
    const c = criarClienteDeCadastroMinimo({ nome: 'Padaria do Bairro' }, LISTA);
    expect(c.padrao).toBe(false);
  });
});
