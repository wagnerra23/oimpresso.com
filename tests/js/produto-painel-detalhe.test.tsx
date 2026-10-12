// Painel de detalhe do produto (Produto/Index → PainelDetalhe.tsx) — o que é COMPORTAMENTO.
//
// ── POR QUE EXISTE ───────────────────────────────────────────────────────────
// Em 2026-10-11 o painel foi medido contra o `DetalheDrawer` do protótipo
// (prototipo-ui/cowork/Felipe/produto-blade.jsx) e ajustado: faixa do topo com o fechar, títulos de
// seção, caixa da imagem mesmo sem foto, "Padrão" no lugar do nome interno DUMMY e rodapé na ordem
// do protótipo. Cores, fonte e medidas são CSS (`produto-painel.css`) e NÃO se provam em jsdom —
// essas foram medidas no navegador de produção (ver produto-index-visual-comparison.md).
//
// ── O QUE MEDE (componente REAL) ─────────────────────────────────────────────
//   - o botão "Fechar painel" existe e fecha;
//   - sem foto aparece "Sem imagem"; com foto, a imagem;
//   - produto único mostra a variação como "Padrão", nunca "DUMMY";
//   - as abas trazem o título de seção do protótipo;
//   - o rodapé sai na ordem do protótipo, e "Comprar" some sem permissão ou sem controle de estoque.
//
// Cobre UC-PIDX-09 e UC-PIDX-11 (resources/js/Pages/Produto/Index.casos.md).
// ⚠️ Nenhuma lane de CI roda este arquivo ainda (cada lane Vitest lista os próprios specs).
// Comando: npx vitest run tests/js/produto-painel-detalhe.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, within } from '@testing-library/react';
import PainelDetalheProduto from '@/Pages/Produto/_components/lista/PainelDetalhe';
import type { DetalheProdutoDados } from '@/Pages/Produto/_components/lista/tipos';

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

afterEach(cleanup);

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
