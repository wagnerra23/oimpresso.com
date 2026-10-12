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
//   - o rodapé sai na ordem do protótipo, e "Comprar" some sem permissão ou sem controle de estoque.
//
// Cobre UC-PIDX-09, UC-PIDX-11 e UC-PIDX-12 (resources/js/Pages/Produto/Index.casos.md).
// ⚠️ Nenhuma lane de CI roda este arquivo ainda (cada lane Vitest lista os próprios specs).
// Comando: npx vitest run tests/js/produto-painel-detalhe.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor, within } from '@testing-library/react';
import PainelDetalheProduto from '@/Pages/Produto/_components/lista/PainelDetalhe';
import type { DetalheProdutoDados } from '@/Pages/Produto/_components/lista/tipos';
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
  variacoes: [{ nome: 'DUMMY', sku: 'CAM-001', grupos: [{ grupo: 'Atacado', preco: null }] }],
  composicao: [],
  estoque: [],
  estoque_totais: null,
  fiscal: { ncm: '', cest: '', cfop_interno: '', cfop_externo: '', cst_csosn: '', cst_pis: '', cst_cofins: '', cst_ipi: '' },
  prateleiras: { colunas: { prateleira: false, fileira: false, posicao: false }, linhas: [] },
  ...parte,
});

function abrir(d: DetalheProdutoDados, opcoes: { podeComprar?: boolean } = {}) {
  const aoFechar = vi.fn();
  const irPara = vi.fn();
  render(
    <PainelDetalheProduto
      aberto
      detalhe={d}
      aoFechar={aoFechar}
      podeEditar
      podePrecos
      podeComprar={opcoes.podeComprar ?? true}
      irPara={irPara}
    />,
  );
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
    const { painel } = abrir(detalhe({ tipo: 'variable', variacoes: [{ nome: 'P/Preto', sku: 'CAM-P', grupos: [] }] }));
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

describe('UC-PIDX-11 · rodapé', () => {
  const rotulosDoRodape = (painel: ReturnType<typeof within>) =>
    painel
      .getAllByRole('button')
      .map((b) => (b.textContent ?? '').trim())
      .filter((t) => ['Editar produto', 'Comprar', 'Preços por grupo', 'Imprimir', 'Fechar'].includes(t));

  it('sai na ordem do protótipo', () => {
    const { painel } = abrir(detalhe());
    expect(rotulosDoRodape(painel)).toEqual(['Editar produto', 'Comprar', 'Preços por grupo', 'Imprimir', 'Fechar']);
  });

  it('"Comprar" leva à nova compra com o produto', () => {
    const { painel, irPara } = abrir(detalhe());
    fireEvent.click(painel.getByRole('button', { name: 'Comprar' }));
    expect(irPara).toHaveBeenCalledWith('/purchases/create?produto=7');
  });

  it('"Comprar" some sem a permissão, e some para produto sem controle de estoque ou inativo', () => {
    expect(rotulosDoRodape(abrir(detalhe(), { podeComprar: false }).painel)).not.toContain('Comprar');
    cleanup();
    const semEstoque = detalhe();
    semEstoque.dados.controla_estoque = false;
    expect(rotulosDoRodape(abrir(semEstoque).painel)).not.toContain('Comprar');
    cleanup();
    expect(rotulosDoRodape(abrir(detalhe({ inativo: true })).painel)).not.toContain('Comprar');
  });
});
