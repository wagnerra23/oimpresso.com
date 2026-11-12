// UC-PIDX-47 (Index.casos.md) e UC-PSHOW-08 (Show.casos.md) — o caminho até o Editar só com os locais.
//
// Contrato (CU-PROD-20, proposta #10765): quem tem `product.armazenagem` sem `product.update` vê
// "Locais de armazenagem" no menu da linha, no rodapé do painel de detalhe e na ficha, e não vê
// "Editar". Quem tem `product.update` segue com "Editar" e não ganha o item novo. Produto sem estoque
// controlado, ou excluído, não tem o item.
import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, within } from '@testing-library/react';

vi.mock('@inertiajs/react', async () => {
  const React = await import('react');
  return {
    Head: () => null,
    Deferred: ({ children }: { children: React.ReactNode }) => children,
    Link: ({ href, children, ...resto }: { href: string; children: React.ReactNode }) =>
      React.createElement('a', { href, ...resto }, children),
    router: { visit: () => {} },
  };
});
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: unknown }) => children }));
import AcoesProduto from '@/Pages/Produto/_components/lista/AcoesProduto';
import PainelDetalheProduto from '@/Pages/Produto/_components/lista/PainelDetalhe';
import ProdutoShow from '@/Pages/Produto/Show';
import type { DetalheProdutoDados, LinhaProduto, PermissoesLista } from '@/Pages/Produto/_components/lista/tipos';

const LINHA: LinhaProduto = {
  id: 7, nome: 'Camiseta', sku: 'CAM-7', tipo: 'single', categoria: 'Camisetas', subcategoria: null, unidade: 'Unidade', unidade_curta: 'Un',
  marca: 'Hering', imposto: null, imagem: null, controla_estoque: true, estoque: 5, alerta: null, abaixo_do_alerta: false,
  inativo: false, nao_para_venda: false, serie: false, locais: ['Matriz'], variacoes: 1, campos: {}, anexo: null,
  compra_min: 10, compra_max: 10, venda_min: 20, venda_max: 20,
};
const ESTOQUISTA: PermissoesLista = {
  create: false, update: false, delete: false, opening_stock: false, view: true, armazenagem: true,
};
const ITEM = 'Locais de armazenagem';

function abrirMenu(permissoes: PermissoesLista, linha: LinhaProduto = LINHA) {
  const aoEscolher = vi.fn();
  render(<AcoesProduto linha={linha} permissoes={permissoes} gruposDePreco={0} emiteNota={false} aoEscolher={aoEscolher} />);
  fireEvent.keyDown(screen.getByRole('button', { name: `Ações do produto ${linha.nome}` }), { key: 'Enter' });
  return { aoEscolher, itens: () => screen.getAllByRole('menuitem').map((i) => i.textContent) };
}

const detalhe = (parte: Partial<DetalheProdutoDados> = {}): DetalheProdutoDados => ({
  id: 7, nome: 'Camiseta básica', sku: 'CAM-001', tipo: 'single', inativo: false, imagem: null,
  dados: {
    codigo_de_barras: 'C128', unidade: 'Unidade', unidade_curta: 'Un', marca: 'Hering', categoria: 'Camisetas',
    subcategoria: '', imposto: '', tipo_imposto: 'inclusive', peso: '', controla_estoque: true, alerta: null,
    validade: null, locais: ['Matriz'], nao_para_venda: false, garantia: null, tempo_preparo: null, descricao: '', campos: [],
  },
  variacoes: [], composicao: [], composicao_total_exc: null, estoque: [], estoque_totais: null,
  fiscal: { ncm: '', cest: '', cfop_interno: '', cfop_externo: '', cst_csosn: '', cst_pis: '', cst_cofins: '', cst_ipi: '', origem: '0', origem_por_produto: false, link_modulo: null },
  prateleiras: { colunas: { prateleira: false, fileira: false, posicao: false }, linhas: [] },
  ...parte,
});

function abrirPainel(d: DetalheProdutoDados, podeEditar: boolean, podeArmazenagem: boolean) {
  const irPara = vi.fn();
  render(
    <PainelDetalheProduto
      aberto detalhe={d} aba="dados" aoMudarAba={() => {}} aoFechar={() => {}}
      podeEditar={podeEditar} podeArmazenagem={podeArmazenagem} podePrecos={false} emiteNota={false} irPara={irPara}
    />,
  );
  return { irPara, painel: within(screen.getByRole('dialog')) };
}

afterEach(() => {
  cleanup();
  document.querySelectorAll('.cockpit').forEach((e) => e.remove());
});

describe('UC-PIDX-47 · menu da linha', () => {
  it('só com product.armazenagem: tem "Locais de armazenagem", não tem "Editar", e o item leva à ação armazenagem', () => {
    const { aoEscolher, itens } = abrirMenu(ESTOQUISTA);
    expect(itens()).toContain(ITEM);
    expect(itens()).not.toContain('Editar');
    fireEvent.click(screen.getByRole('menuitem', { name: ITEM }));
    expect(aoEscolher).toHaveBeenCalledWith('armazenagem', LINHA);
  });

  it('com product.update: segue "Editar" e não ganha o item novo', () => {
    const { itens } = abrirMenu({ ...ESTOQUISTA, update: true });
    expect(itens()).toContain('Editar');
    expect(itens()).not.toContain(ITEM);
  });

  it('produto sem estoque controlado ou excluído não tem o item (anti-vácuo: o menu abriu com "Ver")', () => {
    let m = abrirMenu(ESTOQUISTA, { ...LINHA, controla_estoque: false });
    expect(m.itens()).toContain('Ver');
    expect(m.itens()).not.toContain(ITEM);
    cleanup();
    m = abrirMenu(ESTOQUISTA, { ...LINHA, excluido: true });
    expect(m.itens()).not.toContain(ITEM);
  });
});

describe('UC-PIDX-47 · rodapé do painel de detalhe', () => {
  it('só com a permissão de locais: o botão leva ao Editar do produto', () => {
    const { irPara, painel } = abrirPainel(detalhe(), false, true);
    expect(painel.queryByRole('button', { name: /Editar produto/ })).toBeNull();
    fireEvent.click(painel.getByRole('button', { name: /Locais de armazenagem/ }));
    expect(irPara).toHaveBeenCalledWith('/products/7/edit');
  });

  it('com product.update aparece "Editar produto" e não o botão de locais; sem estoque controlado, nenhum dos dois', () => {
    let p = abrirPainel(detalhe(), true, false);
    expect(p.painel.getByRole('button', { name: /Editar produto/ })).toBeTruthy();
    expect(p.painel.queryByRole('button', { name: /Locais de armazenagem/ })).toBeNull();
    cleanup();
    p = abrirPainel(detalhe({ dados: { ...detalhe().dados, controla_estoque: false } }), false, true);
    expect(p.painel.queryByRole('button', { name: /Locais de armazenagem/ })).toBeNull();
  });
});

describe('UC-PSHOW-08 · ficha do produto', () => {
  const produto = {
    id: 7, name: 'Camiseta', sku: 'CAM-7', type: 'single', category: null, subCategory: null, brand: null,
    unit: 'Unidade', enableStock: true, alertQuantity: null, productDescription: null, image: null,
  };
  function ficha(permissions: Record<string, boolean>, extra: Record<string, unknown> = {}) {
    return render(<ProdutoShow {...({ product: { ...produto, ...extra }, rackDetails: [], variations: [], permissions } as never)} />);
  }

  it('só com a permissão de locais: o link "Locais de armazenagem" leva ao Editar, sem "Editar"', () => {
    ficha({ update: false, delete: false, armazenagem: true });
    const link = screen.getByRole('link', { name: /Locais de armazenagem/ });
    expect(link.getAttribute('href')).toBe('/products/7/edit');
    expect(screen.queryByRole('link', { name: /^Editar$/ })).toBeNull();
  });

  it('com product.update aparece "Editar" e não o link de locais; produto sem estoque controlado não tem o link', () => {
    ficha({ update: true, delete: false, armazenagem: true });
    expect(screen.getByRole('link', { name: /Editar/ })).toBeTruthy();
    expect(screen.queryByRole('link', { name: /Locais de armazenagem/ })).toBeNull();
    cleanup();
    ficha({ update: false, delete: false, armazenagem: true }, { enableStock: false });
    expect(screen.queryByRole('link', { name: /Locais de armazenagem/ })).toBeNull();
  });
});
