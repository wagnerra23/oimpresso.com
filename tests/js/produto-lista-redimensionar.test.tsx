// Lista de produtos — arrastar a borda da coluna para redimensionar.
//
// Cobre UC-PIDX-16 (resources/js/Pages/Produto/Index.casos.md), com as colunas de verdade
// (`colunasDaLista`) na tabela de verdade. Referência medida no protótipo em 2026-10-13
// (produto-blade.jsx · `ListaProdutos`, arrasto real): alça em Produto, Locais, Preço de compra,
// Preço de venda, Estoque atual, Tipo, Categoria, Marca e Imposto; nenhuma em Ação, Imagem e na
// última coluna (SKU, no padrão). +50px levou Produto de 300 para 350.
//
// ⚠️ Nenhuma lane de CI roda este arquivo ainda (cada lane Vitest lista os próprios specs).
// Comando: npx vitest run tests/js/produto-lista-redimensionar.test.tsx

import { describe, it, expect, afterEach, beforeEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';
import { router } from '@inertiajs/react';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import { colunasDaLista, filtrosParaATabela } from '@/Pages/Produto/_components/lista/colunas';
import { visibilidadePadrao, colunasOpcionais, type LinhaProduto, type PermissoesLista } from '@/Pages/Produto/_components/lista/tipos';

vi.mock('@inertiajs/react', () => ({ router: { get: vi.fn(), visit: vi.fn() } }));

const linha = (id: number, nome: string): LinhaProduto => ({
  id, nome, sku: `SKU-${id}`, tipo: 'single', categoria: 'Camisetas', subcategoria: null, unidade: 'Unidade', unidade_curta: 'Un',
  marca: 'Hering', imposto: null, imagem: null, controla_estoque: true, estoque: 5, alerta: null, abaixo_do_alerta: false,
  inativo: false, nao_para_venda: false, serie: false, locais: ['Matriz'], variacoes: 1, campos: {}, anexo: null,
  compra_min: 10, compra_max: 10, venda_min: 20, venda_max: 20,
});
const LINHAS = [linha(1, 'Camiseta'), linha(2, 'Blusa')];
const PAG: PaginatorShape<LinhaProduto> = {
  data: LINHAS, total: 2, current_page: 1, last_page: 1, from: 1, to: 2,
  links: [
    { url: null, label: '&laquo; Anterior', active: false },
    { url: '/products?page=1', label: '1', active: true },
    { url: null, label: 'Próxima &raquo;', active: false },
  ],
};
const TUDO: PermissoesLista = {
  create: true, update: true, delete: true, opening_stock: true, view: true, stock_report: true,
  etiquetas: true, comprar: true, view_purchase_price: true, access_default_selling_price: true,
};
const CONTEXTO = { empresa: 'Empresa de teste', grupos_de_preco: 1, woocommerce: false, papel: null, rotulos_campos: {} };

/** As colunas que a tela mostra de saída (o padrão do menu "Colunas"). */
const PADRAO = ['Ação', 'Imagem', 'Produto', 'Locais', 'Preço de compra un.', 'Preço de venda', 'Estoque atual', 'Tipo', 'Categoria', 'Marca', 'Imposto', 'SKU'];
const COM_ALCA = ['Produto', 'Locais', 'Preço de compra un.', 'Preço de venda', 'Estoque atual', 'Tipo', 'Categoria', 'Marca', 'Imposto'];
const SEM_ALCA = ['Ação', 'Imagem', 'SKU'];

function Lista({ visiveis }: { visiveis: Record<string, boolean> }) {
  const colunas = colunasDaLista({ visiveis, densa: false, permissions: TUDO, contexto: CONTEXTO, aoEscolher: () => undefined });
  return (
    <DataTable<LinhaProduto>
      caption="Produtos do catálogo"
      columns={colunas}
      data={LINHAS}
      pagination={PAG}
      endpoint="/products"
      filters={filtrosParaATabela({ per_page: 25 }, { sort: 'produto', dir: 'asc' })}
      showSearch={false}
      density="grid"
      resizable
      rowKey={(l) => l.id}
    />
  );
}

const rotulo = (th: Element) => (th.textContent ?? '').replace(/[↕↑↓]/g, '').trim();
const cabecalhos = () => screen.getAllByRole('columnheader');
const cabecalho = (r: string) => {
  const th = cabecalhos().find((h) => rotulo(h) === r);
  if (!th) throw new Error(`cabeçalho "${r}" não existe`);
  return th as HTMLElement;
};
const alcaDe = (r: string) => cabecalho(r).querySelector<HTMLElement>('[data-slot="datatable-redimensionar"]');
const larguras = () => Array.from(document.querySelectorAll('colgroup col')).map((c) => (c as HTMLElement).style.width);

function arrastar(r: string, larguraRenderizada: number, de: number, ate: number) {
  const th = cabecalho(r);
  th.getBoundingClientRect = () => ({ width: larguraRenderizada, height: 28, top: 0, left: 0, right: larguraRenderizada, bottom: 28, x: 0, y: 0, toJSON: () => ({}) });
  const alca = alcaDe(r);
  if (!alca) throw new Error(`"${r}" não tem alça`);
  fireEvent.mouseDown(alca, { clientX: de });
  fireEvent.mouseMove(document, { clientX: ate });
  fireEvent.mouseUp(document);
}

beforeEach(() => vi.clearAllMocks());
afterEach(() => cleanup());

describe('UC-PIDX-16 · arrastar a borda da coluna', () => {
  it('as colunas e as larguras de saída são as do protótipo', () => {
    render(<Lista visiveis={visibilidadePadrao(colunasOpcionais({}))} />);
    expect(cabecalhos().map(rotulo)).toEqual(PADRAO);
    expect(larguras()).toEqual(['96px', '74px', '300px', '150px', '150px', '150px', '130px', '110px', '160px', '120px', '110px', '120px']);
  });

  it('têm alça as 9 colunas do meio; Ação, Imagem e a última (SKU) não', () => {
    render(<Lista visiveis={visibilidadePadrao(colunasOpcionais({}))} />);
    for (const r of COM_ALCA) expect(alcaDe(r), `"${r}" deveria ter alça`).not.toBeNull();
    for (const r of SEM_ALCA) expect(alcaDe(r), `"${r}" não deveria ter alça`).toBeNull();
  });

  it('+50 em Produto: 300 vira 350 e nenhuma outra coluna muda', () => {
    render(<Lista visiveis={visibilidadePadrao(colunasOpcionais({}))} />);

    arrastar('Produto', 300, 784, 834);

    expect(larguras()).toEqual(['96px', '74px', '350px', '150px', '150px', '150px', '130px', '110px', '160px', '120px', '110px', '120px']);
  });

  it('encolher Locais além do piso para em 48', () => {
    render(<Lista visiveis={visibilidadePadrao(colunasOpcionais({}))} />);

    arrastar('Locais', 150, 984, 784);

    expect(larguras()[3]).toBe('48px');
  });

  it('arrastar não muda a ordenação da lista', () => {
    render(<Lista visiveis={visibilidadePadrao(colunasOpcionais({}))} />);

    arrastar('Produto', 300, 784, 834);

    expect(router.get).not.toHaveBeenCalled();
    expect(cabecalho('Produto').getAttribute('aria-sort')).toBe('ascending');
  });

  it('desligar "Imagem" no menu Colunas não leva a largura arrastada de Produto para outra coluna', () => {
    const padrao = visibilidadePadrao(colunasOpcionais({}));
    const { rerender } = render(<Lista visiveis={padrao} />);
    arrastar('Produto', 300, 784, 834);

    rerender(<Lista visiveis={{ ...padrao, imagem: false }} />);

    expect(cabecalhos().map(rotulo)).toEqual(PADRAO.filter((r) => r !== 'Imagem'));
    expect(larguras()).toEqual(['96px', '350px', '150px', '150px', '150px', '130px', '110px', '160px', '120px', '110px', '120px']);
  });

  it('com SKU desligado, a última passa a ser Imposto e é ela que fica sem alça', () => {
    const padrao = visibilidadePadrao(colunasOpcionais({}));
    render(<Lista visiveis={{ ...padrao, sku: false }} />);
    expect(alcaDe('Imposto')).toBeNull();
    expect(alcaDe('Marca')).not.toBeNull();
  });
});
