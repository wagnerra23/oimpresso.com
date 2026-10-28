// Painel de detalhe do produto (Produto/Index → PainelDetalhe.tsx) — o que é COMPORTAMENTO.
//
// ── POR QUE EXISTE ───────────────────────────────────────────────────────────
// Em 2026-10-11 o painel foi medido contra o `DetalheDrawer` do protótipo
// (prototipo-ui/cowork/Felipe/produto-blade.jsx) e ajustado: faixa do topo com o fechar, títulos de
// seção, caixa da imagem mesmo sem foto, "Padrão" no lugar do nome interno DUMMY e rodapé na ordem
// do protótipo. Cores, fonte e medidas NÃO se provam em jsdom — foram medidas no navegador de
// produção (ver produto-index-visual-comparison.md). O que dá para provar aqui é o MECANISMO que
// as entrega: o painel monta dentro do `.cockpit`, que é onde os tokens do Design System vivem.
//
// ── O QUE MEDE (componente REAL) ─────────────────────────────────────────────
//   - com um `.cockpit` na página o painel monta DENTRO dele; sem, no <body> (como antes);
//   - o botão "Fechar painel" existe e fecha;
//   - sem foto aparece "Sem imagem"; com foto, a imagem;
//   - produto único mostra a variação como "Padrão", nunca "DUMMY";
//   - as abas trazem o título de seção do protótipo;
//   - a aba Estoque mostra os 3 indicadores com os totais que o servidor mandou (nada é somado aqui),
//     e "—" + aviso quando o valor vem null (sem permissão não é zero);
//   - o rodapé sai na ordem do protótipo, sem "Comprar" (a consulta não é a tela de compras);
//   - a composição traz o "Total líquido" que o servidor somou, o preço de venda da composição, os
//     preços de cada item (as colunas da Blade) e as imagens dos itens; a aba Variações, as imagens
//     das variações. Sem permissão, a parte de preço some (nunca zero).
//
// Cobre UC-PIDX-09, UC-PIDX-11, UC-PIDX-12, UC-PIDX-17, UC-PIDX-19, UC-PIDX-20, UC-PIDX-21, UC-PIDX-25 e UC-PIDX-33 (resources/js/Pages/Produto/Index.casos.md).
// ⚠️ Nenhuma lane de CI roda este arquivo ainda (cada lane Vitest lista os próprios specs).
// Comando: npx vitest run tests/js/produto-painel-detalhe.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { useState } from 'react';
import { render, screen, cleanup, fireEvent, waitFor, within } from '@testing-library/react';
import PainelDetalheProduto from '@/Pages/Produto/_components/lista/PainelDetalhe';
import type { AbaDoDetalhe, DetalheProdutoDados } from '@/Pages/Produto/_components/lista/tipos';
// Formatador de moeda da própria tela: o que se prova aqui é QUAL número aparece, não o formato.
import { brl } from '@/Pages/Produto/Unificado/_components/catalogo';

const detalhe = (parte: Partial<DetalheProdutoDados> = {}): DetalheProdutoDados => ({
  id: 7,
  nome: 'Camiseta básica',
  sku: 'CAM-001',
  tipo: 'single',
  inativo: false,
  imagem: null,
  dados: {
    codigo_de_barras: 'C128',
    unidade: 'Unidade',
    unidade_curta: 'Un',
    marca: 'Hering',
    categoria: 'Camisetas',
    subcategoria: '',
    imposto: '',
    tipo_imposto: 'inclusive',
    peso: '',
    controla_estoque: true,
    alerta: null,
    validade: null,
    locais: ['Matriz'],
    nao_para_venda: false,
    garantia: null,
    tempo_preparo: null,
    descricao: '',
    campos: [],
  },
  variacoes: [{ nome: 'DUMMY', sku: 'CAM-001', imagens: [], grupos: [{ grupo: 'Atacado', preco: null }] }],
  composicao: [],
  composicao_total_exc: null,
  estoque: [],
  estoque_totais: null,
  fiscal: { ncm: '', cest: '', cfop_interno: '', cfop_externo: '', cst_csosn: '', cst_pis: '', cst_cofins: '', cst_ipi: '', origem: '0', origem_por_produto: false, link_modulo: null },
  prateleiras: { colunas: { prateleira: false, fileira: false, posicao: false }, linhas: [] },
  ...parte,
});

/** O painel com o dono da aba do lado de fora, como em `Produto/Index.tsx`. */
function Painel({
  d, abaInicial = 'dados', emiteNota = true, aoFechar, irPara,
}: { d: DetalheProdutoDados; abaInicial?: AbaDoDetalhe; emiteNota?: boolean; aoFechar: () => void; irPara: (u: string) => void }) {
  const [aba, setAba] = useState<AbaDoDetalhe>(abaInicial);
  return (
    <PainelDetalheProduto
      aberto
      detalhe={d}
      aba={aba}
      aoMudarAba={setAba}
      aoFechar={aoFechar}
      podeEditar
      podePrecos
      emiteNota={emiteNota}
      irPara={irPara}
    />
  );
}

function abrir(d: DetalheProdutoDados, opcoes: { abaInicial?: AbaDoDetalhe; emiteNota?: boolean } = {}) {
  const aoFechar = vi.fn();
  const irPara = vi.fn();
  render(<Painel d={d} abaInicial={opcoes.abaInicial} emiteNota={opcoes.emiteNota} aoFechar={aoFechar} irPara={irPara} />);
  return { aoFechar, irPara, painel: within(screen.getByRole('dialog')) };
}

afterEach(() => {
  cleanup();
  document.querySelectorAll('.cockpit').forEach((e) => e.remove());
});

describe('UC-PIDX-09 · o painel recebe os tokens do Design System', () => {
  it('com um `.cockpit` na página, o painel monta dentro dele', async () => {
    const cockpit = document.createElement('div');
    cockpit.className = 'cockpit';
    document.body.appendChild(cockpit);

    abrir(detalhe());

    await waitFor(() => expect(cockpit.querySelector('[role="dialog"]')).not.toBeNull());
    expect(within(cockpit).getByText('Camiseta básica')).toBeTruthy();
  });

  it('sem `.cockpit`, monta no <body> como o Sheet sempre fez (não quebra fora do shell)', () => {
    abrir(detalhe());
    expect(screen.getByRole('dialog').closest('.cockpit')).toBeNull();
    expect(document.body.contains(screen.getByRole('dialog'))).toBe(true);
  });
});

describe('UC-PIDX-09 · moldura do painel', () => {
  it('a faixa do código tem a borda de baixo do Drawer do DS — a linha antes do título', () => {
    const { painel } = abrir(detalhe());
    const faixa = painel.getByText('CAM-001', { selector: 'span' }).closest('div') as HTMLElement;
    // A faixa é o pai do grupo código+selo; é nela que fica a borda.
    const alvo = faixa.parentElement?.contains(painel.getByRole('button', { name: 'Fechar painel' })) ? faixa.parentElement! : faixa;
    expect(alvo.className).toContain('border-b');
    expect(alvo.className).toContain('border-[var(--border)]');
    expect(alvo.className).not.toContain('pb-[15px]');
  });

  it('tem o botão "Fechar painel", e ele fecha', () => {
    const { aoFechar, painel } = abrir(detalhe());
    fireEvent.click(painel.getByRole('button', { name: 'Fechar painel' }));
    expect(aoFechar).toHaveBeenCalledTimes(1);
  });

  it('o subtítulo junta categoria, marca e unidade, pulando o que está vazio', () => {
    const { painel } = abrir(detalhe());
    expect(painel.getByText('Camisetas · Hering · unidade Un')).toBeTruthy();
  });

  it('sem foto mostra "Sem imagem"; com foto, a imagem do produto', () => {
    const semFoto = abrir(detalhe());
    expect(semFoto.painel.getByText('Sem imagem')).toBeTruthy();
    cleanup();

    const comFoto = abrir(detalhe({ imagem: '/uploads/img/cam.png' }));
    expect(comFoto.painel.queryByText('Sem imagem')).toBeNull();
    expect(comFoto.painel.getByAltText('Imagem de Camiseta básica').getAttribute('src')).toBe('/uploads/img/cam.png');
  });
});

describe('UC-PIDX-09 · abas', () => {
  it('produto único mostra a variação como "Padrão", nunca o nome interno DUMMY', () => {
    const { painel } = abrir(detalhe());
    fireEvent.click(painel.getByRole('tab', { name: /Variações/ }));

    expect(painel.getByRole('heading', { name: 'Variações' })).toBeTruthy();
    expect(painel.getByRole('heading', { name: 'Preços por grupo' })).toBeTruthy();
    expect(painel.getAllByText('Padrão').length).toBe(2); // tabela de preços e tabela de grupos
    expect(painel.queryByText('DUMMY')).toBeNull();
  });

  it('variação com nome próprio aparece com o nome dela', () => {
    const { painel } = abrir(detalhe({ tipo: 'variable', variacoes: [{ nome: 'P/Preto', sku: 'CAM-P', imagens: [], grupos: [] }] }));
    fireEvent.click(painel.getByRole('tab', { name: /Variações/ }));
    expect(painel.getByText('P/Preto')).toBeTruthy();
    expect(painel.queryByText('Padrão')).toBeNull();
  });

  it('cada aba traz o título de seção do protótipo', () => {
    const { painel } = abrir(detalhe());
    fireEvent.click(painel.getByRole('tab', { name: 'Estoque' }));
    expect(painel.getByRole('heading', { name: 'Detalhes de estoque' })).toBeTruthy();
    fireEvent.click(painel.getByRole('tab', { name: 'Fiscal' }));
    expect(painel.getByRole('heading', { name: 'Dados fiscais' })).toBeTruthy();
    fireEvent.click(painel.getByRole('tab', { name: 'Prateleira' }));
    expect(painel.getByRole('heading', { name: 'Detalhes de prateleira' })).toBeTruthy();
  });
});

describe('UC-PIDX-17 · o painel abre na aba que quem abriu pediu', () => {
  const comFiscal = () => detalhe({ fiscal: { ncm: '49019900', cest: '2806400', cfop_interno: '5102', cfop_externo: '6102', cst_csosn: '102', cst_pis: '07', cst_cofins: '07', cst_ipi: '53', origem: '0', origem_por_produto: false, link_modulo: null } });

  it('pedida a aba Fiscal, os dados fiscais já estão na tela, sem clicar em aba', () => {
    const { painel } = abrir(comFiscal(), { abaInicial: 'fiscal' });
    expect(painel.getByRole('tab', { name: 'Fiscal' }).getAttribute('aria-selected')).toBe('true');
    expect(painel.getByText('Dados fiscais')).toBeTruthy();
    expect(painel.getByText('49019900')).toBeTruthy();
    expect(painel.getByText('5102')).toBeTruthy();
  });

  it('sem pedido, abre em Dados (o "Ver" de sempre)', () => {
    const { painel } = abrir(comFiscal());
    expect(painel.getByRole('tab', { name: 'Dados' }).getAttribute('aria-selected')).toBe('true');
    expect(painel.queryByText('49019900')).toBeNull();
  });

  it('o painel não guarda aba própria: obedece à aba que o dono manda, e só avisa o clique', () => {
    const aoMudarAba = vi.fn();
    const props = { aberto: true, detalhe: comFiscal(), aoMudarAba, aoFechar: () => undefined, podeEditar: true, podePrecos: true, emiteNota: true, irPara: () => undefined };
    const { rerender } = render(<PainelDetalheProduto {...props} aba="fiscal" />);
    const painel = within(screen.getByRole('dialog'));
    expect(painel.getByText('49019900')).toBeTruthy();

    // Clicar numa aba só avisa; sem o dono trocar, o painel continua onde estava.
    fireEvent.click(painel.getByRole('tab', { name: 'Dados' }));
    expect(aoMudarAba).toHaveBeenCalledWith('dados');
    expect(painel.getByText('49019900')).toBeTruthy();

    // O dono manda outra aba (é o que acontece ao abrir outro produto pelo "Ver").
    rerender(<PainelDetalheProduto {...props} aba="dados" />);
    expect(painel.getByRole('tab', { name: 'Dados' }).getAttribute('aria-selected')).toBe('true');
    expect(painel.queryByText('49019900')).toBeNull();
  });

  it('pedida uma aba que este usuário não vê (Estoque sem permissão de ver o relatório), cai em Dados', () => {
    const { painel } = abrir(detalhe({ estoque: null }), { abaInicial: 'estoque' });
    expect(painel.getByRole('tab', { name: 'Dados' }).getAttribute('aria-selected')).toBe('true');
  });
});

describe('UC-PIDX-19 · o imposto fica junto do preço, na aba Dados', () => {
  // Decisão [F] 2026-10-26: imposto não é segredo (sai impresso na nota) e a venda cobra o preço com
  // imposto, com nota ou sem. Então a aba Dados mostra o preço cobrado e, só quando o produto tem
  // imposto, o sem imposto e o imposto aplicável. A aba Fiscal não repete o imposto.
  /** O valor (`<b>`) do campo com aquele rótulo, dentro do painel. */
  const campo = (painel: ReturnType<typeof within>, rotulo: string) =>
    (painel.getByText(rotulo).closest('.pb-dt') as HTMLElement).querySelector('b')?.textContent;
  const frase = 'O preço de venda já inclui o imposto aplicável. É o valor que a venda cobra, com nota ou sem.';
  const comImposto = (tipo_imposto = 'exclusive') => {
    const base = detalhe();
    return detalhe({
      dados: { ...base.dados, imposto: 'ICMS 18%', tipo_imposto },
      variacoes: [{ nome: 'DUMMY', sku: 'CAM-001', imagens: [], compra_exc: 10, compra_inc: 11.8, lucro_percentual: 50, venda_exc: 42, venda_inc: 49.56, grupos: [] }],
    });
  };
  const semImposto = () =>
    detalhe({ variacoes: [{ nome: 'DUMMY', sku: 'CAM-001', imagens: [], compra_exc: 10, compra_inc: 10, lucro_percentual: 50, venda_exc: 42, venda_inc: 42, grupos: [] }] });

  it('com imposto: preço cobrado (com imposto), preço sem imposto, o imposto e a frase', () => {
    const { painel } = abrir(comImposto());
    // Discriminante: 49,56 é o com imposto; um painel que mostrasse o sem imposto daria 42,00.
    expect(campo(painel, 'Preço de venda')).toBe(brl(49.56));
    expect(campo(painel, 'Preço sem imposto')).toBe(brl(42));
    expect(campo(painel, 'Imposto aplicável')).toBe('ICMS 18% · exclusivo');
    expect(painel.getByText(frase)).toBeTruthy();
  });

  it('o tipo de imposto vai junto do nome; sem tipo no cadastro, só o nome', () => {
    const { painel } = abrir(comImposto('inclusive'));
    expect(campo(painel, 'Imposto aplicável')).toBe('ICMS 18% · inclusivo');
    cleanup();
    const { painel: p2 } = abrir(comImposto(''));
    expect(campo(p2, 'Imposto aplicável')).toBe('ICMS 18%');
  });

  it('sem imposto: só o preço de venda, sem a linha do imposto nem a frase', () => {
    const { painel } = abrir(semImposto());
    expect(campo(painel, 'Preço de venda')).toBe(brl(42)); // anti-vácuo: o preço está lá
    expect(painel.queryByText('Preço sem imposto')).toBeNull();
    expect(painel.queryByText('Imposto aplicável')).toBeNull();
    expect(painel.queryByText(frase)).toBeNull();
  });

  it('produto com várias variações mostra a faixa do preço cobrado', () => {
    const base = comImposto();
    const v = base.variacoes[0]!;
    const { painel } = abrir({ ...base, variacoes: [v, { ...v, sku: 'CAM-002', venda_exc: 50, venda_inc: 59 }] });
    expect(campo(painel, 'Preço de venda')).toBe(`${brl(49.56)} – ${brl(59)}`);
    expect(campo(painel, 'Preço sem imposto')).toBe(`${brl(42)} – ${brl(50)}`);
  });

  it('sem permissão de ver o preço, as linhas de preço somem; o imposto continua (não é segredo)', () => {
    const base = comImposto();
    const { painel } = abrir({ ...base, variacoes: [{ nome: 'DUMMY', sku: 'CAM-001', imagens: [], grupos: [] }] });
    expect(painel.queryByText('Preço de venda')).toBeNull();
    expect(painel.queryByText('Preço sem imposto')).toBeNull();
    expect(campo(painel, 'Imposto aplicável')).toBe('ICMS 18% · exclusivo');
  });

  it('a aba Fiscal não repete o imposto', () => {
    const { painel } = abrir(comImposto(), { abaInicial: 'fiscal' });
    expect(painel.getByText('Dados fiscais')).toBeTruthy(); // anti-vácuo: está na aba Fiscal
    expect(painel.queryByText('Imposto aplicável')).toBeNull();
    expect(painel.queryByText('Tipo de imposto na venda')).toBeNull();
  });

  it('a tabela de variações separa "sem" e "com imposto" só quando o produto tem imposto', () => {
    const cabecalho = (painel: ReturnType<typeof within>) =>
      within(painel.getByRole('table', { name: 'Variações e preços' })).getAllByRole('columnheader').map((c) => c.textContent);
    const { painel } = abrir(comImposto(), { abaInicial: 'variacoes' });
    expect(cabecalho(painel)).toEqual(['Variação', 'SKU', 'Compra sem imposto', 'Compra com imposto', '% lucro', 'Venda sem imposto', 'Venda com imposto']);
    cleanup();
    const { painel: p2 } = abrir(semImposto(), { abaInicial: 'variacoes' });
    expect(cabecalho(p2)).toEqual(['Variação', 'SKU', 'Compra', '% lucro', 'Venda']);
    const linha = within(p2.getByRole('table', { name: 'Variações e preços' })).getAllByRole('row')[1] as HTMLElement;
    expect(Array.from(linha.querySelectorAll('td')).map((td) => td.textContent)).toEqual(['Padrão', 'CAM-001', brl(10), '50 %', brl(42)]);
  });
});

describe('UC-PIDX-25 · aba Fiscal: origem, ajuda e "Abrir no módulo Fiscal"', () => {
  const comFiscal = (f: Partial<DetalheProdutoDados['fiscal']>) => {
    const base = detalhe();
    return detalhe({ fiscal: { ...base.fiscal, ...f } });
  };
  const campo = (rotulo: string) =>
    (screen.getByText(rotulo).closest('.pb-dt') as HTMLElement).querySelector('b')?.textContent;
  const ajuda = () => document.querySelector('[data-contract=produto-detalhe-fiscal-ajuda]')?.textContent ?? '';

  it('mostra a origem que a nota usa, com o nome da tabela A, e avisa que vale para todos', () => {
    abrir(comFiscal({ origem: '0' }), { abaInicial: 'fiscal' });
    expect(campo('Origem da mercadoria')).toBe('0 — Nacional');
    expect(ajuda()).toContain('A origem é a mesma em todas as notas');
  });

  it('a origem fecha a aba Fiscal; o imposto não se repete aqui (fica na aba Dados)', () => {
    abrir(comFiscal({}), { abaInicial: 'fiscal' });
    const rotulos = Array.from(document.querySelectorAll('[role=dialog] .pb-dt > span')).map((e) => e.textContent);
    expect(rotulos.at(-1)).toBe('Origem da mercadoria');
    expect(rotulos).not.toContain('Imposto aplicável');
  });

  it('código fora da tabela A vira traço, não um rótulo inventado', () => {
    abrir(comFiscal({ origem: '9' }), { abaInicial: 'fiscal' });
    expect(campo('Origem da mercadoria')).toBe('—');
  });

  it('com origem por produto, o aviso de "mesma em todas as notas" some', () => {
    abrir(comFiscal({ origem: '1', origem_por_produto: true }), { abaInicial: 'fiscal' });
    expect(campo('Origem da mercadoria')).toBe('1 — Estrangeira — importação direta');
    expect(ajuda()).not.toContain('mesma em todas as notas');
  });

  /** A linha do topo da aba: o produto está pronto para nota? */
  const pronto = () => document.querySelector('[data-contract=produto-fiscal-pronto]')?.textContent ?? '';

  it('sem NCM, a aba começa dizendo que a nota sai com o NCM padrão — e não chama o produto de serviço', () => {
    abrir(comFiscal({ ncm: '0' }), { abaInicial: 'fiscal' });
    expect(pronto()).toContain('Sem NCM');
    expect(pronto()).toContain('A nota sai com o NCM padrão da empresa');
    expect(pronto()).not.toContain('Pronto para nota');
    expect(ajuda()).not.toMatch(/Serviço: sai em NFS-e/);
    // A explicação da regra do NCM não aparece sem NCM: quem fala é a linha do topo.
    expect(ajuda()).not.toContain('primeiro a exceção do produto');
  });

  it('com NCM, a aba começa com "Pronto para nota"', () => {
    abrir(comFiscal({ ncm: '49019900' }), { abaInicial: 'fiscal' });
    expect(pronto()).toContain('Pronto para nota');
    expect(pronto()).not.toContain('Sem NCM');
  });

  it('com NCM, a ajuda explica a ordem da regra; com CEST, avisa que o CFOP não muda sozinho', () => {
    abrir(comFiscal({ ncm: '49019900', cest: '' }), { abaInicial: 'fiscal' });
    expect(ajuda()).toContain('primeiro a exceção do produto, depois a regra do NCM');
    expect(ajuda()).not.toContain('CEST');
    cleanup();
    abrir(comFiscal({ ncm: '49019900', cest: '2806400' }), { abaInicial: 'fiscal' });
    expect(ajuda()).toContain('a nota não troca o CFOP sozinha');
  });

  it('sem link do servidor (sem módulo ou sem permissão), o botão não existe', () => {
    abrir(comFiscal({ link_modulo: null }), { abaInicial: 'fiscal' });
    expect(screen.queryByRole('button', { name: 'Abrir no módulo Fiscal' })).toBeNull();
  });

  it('com link, o botão leva para a Tributação com o produto', () => {
    const url = '/nfe-brasil/tributacao?produto=7#simulador';
    const { irPara } = abrir(comFiscal({ link_modulo: url }), { abaInicial: 'fiscal' });
    fireEvent.click(screen.getByRole('button', { name: 'Abrir no módulo Fiscal' }));
    expect(irPara).toHaveBeenCalledWith(url);
  });
});

describe('UC-PIDX-25 · a aba Fiscal só existe em empresa que emite nota', () => {
  // Decisão [F] 2026-10-26: o teste é ter o módulo Fiscal no pacote, o mesmo do botão "Abrir no
  // módulo Fiscal". Quem vende sem nota não precisa de NCM, CEST nem CFOP.
  const comNcm = () => {
    const base = detalhe();
    return detalhe({ fiscal: { ...base.fiscal, ncm: '49019900' } });
  };

  it('sem módulo Fiscal no pacote, não há aba Fiscal', () => {
    const { painel } = abrir(comNcm(), { emiteNota: false });
    expect(painel.getByRole('tab', { name: 'Prateleira' })).toBeTruthy(); // anti-vácuo: as abas estão lá
    expect(painel.queryByRole('tab', { name: 'Fiscal' })).toBeNull();
  });

  it('pedida a aba Fiscal sem o módulo, abre em Dados e não mostra dado fiscal', () => {
    const { painel } = abrir(comNcm(), { abaInicial: 'fiscal', emiteNota: false });
    expect(painel.getByRole('tab', { name: 'Dados' }).getAttribute('aria-selected')).toBe('true');
    expect(painel.queryByText('49019900')).toBeNull();
  });

  it('com o módulo, a aba existe', () => {
    const { painel } = abrir(comNcm(), { emiteNota: true });
    expect(painel.getByRole('tab', { name: 'Fiscal' })).toBeTruthy();
  });
});

describe('UC-PIDX-12 · indicadores da aba Estoque', () => {
  const linha = (local: string, estoque: number) => ({
    sku: 'CAM-001', produto: 'Camiseta básica', variacao: '', categoria: 'Camisetas', local, preco: 20, estoque, unidade: 'Un',
    valor_compra: null, valor_venda: null, lucro_potencial: null, vendido: 0, transferido: 0, ajustado: 0, alerta: false,
  });
  const totais = (parte: Partial<NonNullable<DetalheProdutoDados['estoque_totais']>> = {}) => ({
    linhas: 2, estoque: 10, vendido: 0, transferido: 0, ajustado: 0, valor_compra: 174, valor_venda: 200, lucro_potencial: 26, ...parte,
  });
  const comEstoque = (t: DetalheProdutoDados['estoque_totais']) => {
    const d = detalhe({ estoque: [linha('Matriz', 7), linha('Filial', 3)], estoque_totais: t });
    d.dados.alerta = 5;
    return d;
  };
  const indicadores = (painel: ReturnType<typeof within>) => {
    fireEvent.click(painel.getByRole('tab', { name: 'Estoque' }));
    const caixa = screen.getByRole('dialog').querySelector<HTMLElement>('[data-contract="produto-detalhe-estoque-indicadores"]');
    return caixa ? Array.from(caixa.children).map((c) => (c.textContent ?? '').replace(/\s+/g, ' ').trim()) : null;
  };

  it('mostra os três totais que o servidor mandou, com as legendas', () => {
    const { painel } = abrir(comEstoque(totais()));
    expect(indicadores(painel)).toEqual([
      'Estoque atual10Un · alerta em 5',
      `Valor pelo custo${brl(174)}pelo preço pago nas compras`,
      `Valor pela venda${brl(200)}lucro potencial ${brl(26)}`,
    ]);
  });

  it('o número é o do servidor, não uma soma feita na tela', () => {
    // Totais de propósito DIFERENTES da soma das linhas (7 + 3): se a tela somasse, mostraria 10.
    const { painel } = abrir(comEstoque(totais({ estoque: 99, valor_compra: 1234.5 })));
    const cartoes = indicadores(painel) ?? [];
    expect(cartoes[0]).toBe('Estoque atual99Un · alerta em 5');
    expect(cartoes[1]).toBe(`Valor pelo custo${brl(1234.5)}pelo preço pago nas compras`);
  });

  it('valor null (sem permissão) vira "—" com o aviso, nunca zero', () => {
    const { painel } = abrir(comEstoque(totais({ valor_compra: null, valor_venda: null, lucro_potencial: null })));
    const cartoes = indicadores(painel) ?? [];
    expect(cartoes[0]).toBe('Estoque atual10Un · alerta em 5');
    expect(cartoes[1]).toBe('Valor pelo custo—sem permissão para ver valores do estoque');
    expect(cartoes[2]).toBe('Valor pela venda—sem permissão para ver valores do estoque');
    expect(painel.queryByText(brl(0), { exact: false })).toBeNull();
  });

  it('sem alerta definido a legenda traz só a unidade', () => {
    const d = comEstoque(totais());
    d.dados.alerta = null;
    const { painel } = abrir(d);
    expect((indicadores(painel) ?? [])[0]).toBe('Estoque atual10Un');
  });

  it('sem os totais (servidor não mandou) os indicadores não aparecem e a tabela continua', () => {
    const { painel } = abrir(comEstoque(null));
    expect(indicadores(painel)).toBeNull();
    expect(painel.getByText('Matriz')).toBeTruthy();
  });
});

describe('UC-PIDX-33 · quantidade vendida por local na aba Estoque', () => {
  const linha = (local: string, estoque: number, vendido: number) => ({
    sku: 'CAM-001', produto: 'Camiseta básica', variacao: '', categoria: 'Camisetas', local, preco: 20, estoque, unidade: 'Un',
    valor_compra: null, valor_venda: null, lucro_potencial: null, vendido, transferido: 0, ajustado: 0, alerta: false,
  });
  const tabela = (painel: ReturnType<typeof within>) => {
    fireEvent.click(painel.getByRole('tab', { name: 'Estoque' }));
    return within(painel.getByRole('table', { name: 'Estoque por local' }));
  };

  it('a tabela tem a coluna "Vendido", antes de "Situação"', () => {
    const { painel } = abrir(detalhe({ estoque: [linha('Matriz', 7, 4)] }));
    const cabecalho = tabela(painel).getAllByRole('columnheader').map((c) => c.textContent);
    expect(cabecalho).toEqual(['Local', 'Saldo', 'Alerta', 'Vendido', 'Situação']);
  });

  it('cada local mostra o vendido que o servidor mandou para ele, com a unidade', () => {
    // Números diferentes do saldo e entre si: se a tela trocasse de coluna ou somasse, não bateria.
    const { painel } = abrir(detalhe({ estoque: [linha('Matriz', 7, 4), linha('Filial', 3, 0)] }));
    const linhas = tabela(painel).getAllByRole('row').slice(1).map((r) =>
      within(r).getAllByRole('cell').map((c) => (c.textContent ?? '').replace(/\s+/g, ' ').trim()));
    expect(linhas.map((l) => [l[0], l[3]])).toEqual([['Matriz', '4 Un'], ['Filial', '0 Un']]);
  });
});

describe('UC-PIDX-11 · rodapé', () => {
  const rotulosDoRodape = (painel: ReturnType<typeof within>) =>
    painel
      .getAllByRole('button')
      .map((b) => (b.textContent ?? '').trim())
      .filter((t) => ['Editar produto', 'Comprar', 'Preços por grupo', 'Imprimir', 'Fechar'].includes(t));

  it('sai na ordem do protótipo, sem "Comprar"', () => {
    // Discriminante: produto ativo e com controle de estoque, o caso em que o "Comprar" aparecia até
    // 2026-10-26. A consulta não é a tela de compras.
    const { painel, irPara } = abrir(detalhe());
    expect(rotulosDoRodape(painel)).toEqual(['Editar produto', 'Preços por grupo', 'Imprimir', 'Fechar']);
    expect(painel.queryByRole('button', { name: /compr/i })).toBeNull();
    expect(irPara).not.toHaveBeenCalled();
  });
});

// Produto composto com todas as permissões: a variação dele (o preço da composição) e dois itens.
// O total do servidor (123,45) é DIFERENTE da soma dos itens (20 + 10), para provar que a tela mostra
// o número que chegou e não soma nada.
const composto = (parte: Partial<DetalheProdutoDados> = {}): DetalheProdutoDados =>
  detalhe({
    tipo: 'combo',
    variacoes: [
      { nome: 'DUMMY', sku: 'KIT-1', imagens: [], compra_exc: 30, compra_inc: 33, lucro_percentual: 50, venda_exc: 45, venda_inc: 49.5, grupos: [] },
    ],
    composicao: [
      {
        nome: 'Caneca branca', sku: 'CAN-1', quantidade: 2, unidade: 'Un', imagens: ['/uploads/media/caneca.jpg'],
        compra_exc: 10, compra_inc: 11, lucro_percentual: 40, venda_exc: 14, venda_inc: 15.4, total_exc: 20,
      },
      {
        nome: 'Caixa presente', sku: 'CX-1', quantidade: 1, unidade: 'Un', imagens: [],
        compra_exc: 10, compra_inc: 12, lucro_percentual: 20, venda_exc: 12, venda_inc: 14, total_exc: 10,
      },
    ],
    composicao_total_exc: 123.45,
    ...parte,
  });

/** O mesmo composto visto por quem não tem nenhuma permissão de preço: as chaves não vêm. */
const compostoSemPrecos = (): DetalheProdutoDados =>
  composto({
    variacoes: [{ nome: 'DUMMY', sku: 'KIT-1', imagens: [], grupos: [] }],
    composicao: [
      { nome: 'Caneca branca', sku: 'CAN-1', quantidade: 2, unidade: 'Un', imagens: [] },
      { nome: 'Caixa presente', sku: 'CX-1', quantidade: 1, unidade: 'Un', imagens: [] },
    ],
    composicao_total_exc: null,
  });

describe('UC-PIDX-20 · composição: total, preço da composição e preços dos itens', () => {
  it('a linha "Total líquido" mostra o total que o servidor mandou, não uma soma feita na tela', () => {
    const { painel } = abrir(composto(), { abaInicial: 'composicao' });
    const rodape = painel.getByText('Total líquido').closest('tfoot') as HTMLElement;
    expect(rodape).not.toBeNull();
    expect(within(rodape).getByText(brl(123.45))).toBeTruthy();
    expect(within(rodape).queryByText(brl(30))).toBeNull();
  });

  it('sem permissão de ver o custo não há linha de total (nem zero)', () => {
    const { painel } = abrir(compostoSemPrecos(), { abaInicial: 'composicao' });
    expect(painel.getByText('Caneca branca')).toBeTruthy(); // anti-vácuo: a tabela está lá
    expect(painel.queryByText('Total líquido')).toBeNull();
  });

  it('mostra primeiro o preço cobrado (com imposto), depois o sem imposto e o % de lucro', () => {
    const { painel } = abrir(composto(), { abaInicial: 'composicao' });
    const linha = painel.getByText(/Preço de venda padrão da composição/).closest('p') as HTMLElement;
    expect(linha.textContent).toContain(`Preço de venda padrão da composição: ${brl(49.5)} (sem imposto ${brl(45)})`);
    expect(linha.textContent).toContain('% de lucro 50 %');
  });

  it('composição sem imposto: um preço só, sem o "sem imposto"', () => {
    const v = composto().variacoes[0]!;
    const { painel } = abrir(composto({ variacoes: [{ ...v, venda_inc: 45 }] }), { abaInicial: 'composicao' });
    const linha = painel.getByText(/Preço de venda padrão da composição/).closest('p') as HTMLElement;
    expect(linha.textContent).toContain(`Preço de venda padrão da composição: ${brl(45)}`);
    expect(linha.textContent).not.toContain('sem imposto');
  });

  it('sem permissão de ver o preço de venda, a linha do preço da composição não aparece', () => {
    const { painel } = abrir(compostoSemPrecos(), { abaInicial: 'composicao' });
    expect(painel.queryByText(/Preço de venda padrão da composição/)).toBeNull();
  });

  it('"Preços dos itens" traz, para cada item, as colunas de preço da Blade', () => {
    const { painel } = abrir(composto(), { abaInicial: 'composicao' });
    expect(painel.getByRole('heading', { name: 'Preços dos itens' })).toBeTruthy();
    const tabela = painel.getByRole('table', { name: 'Preços dos itens da composição' });
    expect(within(tabela).getAllByRole('columnheader').map((th) => th.textContent)).toEqual([
      'Item', 'Compra com imposto', '% lucro', 'Venda sem imposto', 'Venda com imposto',
    ]);
    const caneca = within(tabela).getByText('Caneca branca').closest('tr') as HTMLElement;
    expect(Array.from(caneca.querySelectorAll('td')).map((td) => td.textContent)).toEqual([
      'Caneca branca', brl(11), '40 %', brl(14), brl(15.4),
    ]);
  });

  it('itens sem imposto: uma coluna de compra e uma de venda, sem "sem/com imposto"', () => {
    const itens = composto().composicao.map((c) => ({ ...c, compra_inc: c.compra_exc, venda_inc: c.venda_exc }));
    const { painel } = abrir(composto({ composicao: itens }), { abaInicial: 'composicao' });
    const precos = painel.getByRole('table', { name: 'Preços dos itens da composição' });
    expect(within(precos).getAllByRole('columnheader').map((th) => th.textContent)).toEqual(['Item', 'Compra', '% lucro', 'Venda']);
    const comp = painel.getByRole('table', { name: 'Composição' });
    expect(within(comp).getAllByRole('columnheader').map((th) => th.textContent)).toEqual(['Produto', 'SKU', 'Qtd', 'Compra', 'Total']);
  });

  it('sem nenhuma permissão de preço, "Preços dos itens" não aparece', () => {
    const { painel } = abrir(compostoSemPrecos(), { abaInicial: 'composicao' });
    expect(painel.queryByRole('heading', { name: 'Preços dos itens' })).toBeNull();
  });
});

describe('UC-PIDX-20 · imagens das variações e dos itens', () => {
  it('a composição mostra as imagens dos itens que têm imagem, com o nome do item', () => {
    const { painel } = abrir(composto(), { abaInicial: 'composicao' });
    const secao = painel.getByRole('region', { name: 'Imagens dos itens' });
    const imagens = within(secao).getAllByRole('img');
    expect(imagens.map((i) => i.getAttribute('alt'))).toEqual(['Imagem 1 de Caneca branca']);
    expect(imagens[0]?.getAttribute('src')).toBe('/uploads/media/caneca.jpg');
    expect(within(secao).queryByText('Caixa presente')).toBeNull();
  });

  it('a aba Variações mostra as imagens das variações, com o nome que a tela usa', () => {
    const { painel } = abrir(
      detalhe({ variacoes: [{ nome: 'DUMMY', sku: 'CAM-001', imagens: ['/a.jpg', '/b.jpg'], grupos: [] }] }),
      { abaInicial: 'variacoes' },
    );
    const secao = painel.getByRole('region', { name: 'Imagens das variações' });
    expect(within(secao).getAllByRole('img').map((i) => i.getAttribute('alt'))).toEqual([
      'Imagem 1 de Padrão', 'Imagem 2 de Padrão',
    ]);
  });

  it('sem nenhuma imagem, a seção não aparece', () => {
    const { painel } = abrir(detalhe(), { abaInicial: 'variacoes' });
    expect(painel.getByRole('heading', { name: 'Variações' })).toBeTruthy(); // anti-vácuo
    expect(painel.queryByRole('region', { name: 'Imagens das variações' })).toBeNull();
  });
});

describe('UC-PIDX-21 · produto que não controla estoque', () => {
  const semControle = (): DetalheProdutoDados => {
    const d = detalhe({ estoque: null });
    d.dados.controla_estoque = false;
    return d;
  };

  it('tem a aba Estoque, com o aviso de que não gerencia estoque', () => {
    const { painel } = abrir(semControle());
    fireEvent.click(painel.getByRole('tab', { name: 'Estoque' }));
    expect(painel.getByRole('heading', { name: 'Detalhes de estoque' })).toBeTruthy();
    expect(painel.getByText('Este produto não gerencia estoque.')).toBeTruthy();
    // Nada de saldo, indicadores ou histórico: o servidor não manda estoque desse produto.
    expect(painel.queryByRole('table', { name: 'Estoque por local' })).toBeNull();
    expect(painel.queryByRole('button', { name: 'Abrir histórico de estoque' })).toBeNull();
  });

  it('pedida a aba Estoque (ação da lista), abre nela, com o aviso', () => {
    const { painel } = abrir(semControle(), { abaInicial: 'estoque' });
    expect(painel.getByRole('tab', { name: 'Estoque' }).getAttribute('aria-selected')).toBe('true');
    expect(painel.getByText('Este produto não gerencia estoque.')).toBeTruthy();
  });

  it('produto que controla estoque, sem permissão de ver o relatório, continua sem a aba', () => {
    const { painel } = abrir(detalhe({ estoque: null }));
    expect(painel.getByRole('tab', { name: 'Dados' })).toBeTruthy(); // anti-vácuo
    expect(painel.queryByRole('tab', { name: 'Estoque' })).toBeNull();
  });

  it('produto que controla estoque não mostra o aviso', () => {
    const { painel } = abrir(detalhe(), { abaInicial: 'estoque' });
    expect(painel.getByRole('heading', { name: 'Detalhes de estoque' })).toBeTruthy(); // anti-vácuo
    expect(painel.queryByText('Este produto não gerencia estoque.')).toBeNull();
  });
});
