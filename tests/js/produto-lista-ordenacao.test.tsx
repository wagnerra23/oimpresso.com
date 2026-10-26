// Ordenação da lista de produtos pelo cabeçalho (Produto/Index) — com a tabela de VERDADE.
//
// ── POR QUE EXISTE ───────────────────────────────────────────────────────────
// Medido em produção em 2026-10-12: o rodapé dizia "clique no cabeçalho pra ordenar", mas o
// cabeçalho era texto puro e o clique não fazia nada. Dois defeitos, nenhum visível a typecheck,
// build ou CI:
//   1. as 8 colunas tinham `enableSorting: true` sem `accessorFn` — o TanStack só considera a
//      coluna ordenável com `accessorFn`, e a tabela só desenha o botão quando ele considera;
//   2. a tela mandava à tabela os filtros da URL, que omitem o padrão (`sort=produto`, `dir=asc`)
//      — e a tabela só inverte a direção quando recebe `dir: 'asc'`. O 2º clique repetia "asc".
//
// ── O QUE MEDE ───────────────────────────────────────────────────────────────
// `colunasDaLista` e `filtrosParaATabela` REAIS, dentro do `shared/DataTable` REAL:
//   - as 8 colunas ordenáveis têm botão no cabeçalho; as outras, não;
//   - o `id` de cada coluna ordenável é uma chave que o servidor aceita;
//   - clicar manda `sort` + `dir` ao servidor, e o 2º clique na mesma coluna inverte;
//   - a coluna ativa anuncia a direção (`aria-sort`), inclusive a padrão (Produto, crescente);
//   - sem permissão de preço as colunas de preço nem existem (logo não ordenam).
//
// Cobre UC-PIDX-13 (resources/js/Pages/Produto/Index.casos.md). O lado do servidor é o
// tests/Feature/Produto/ProdutoListaOrdenacaoTest.php.
// ⚠️ Nenhuma lane de CI roda este arquivo ainda (cada lane Vitest lista os próprios specs).
// Comando: npx vitest run tests/js/produto-lista-ordenacao.test.tsx

import { describe, it, expect, afterEach, beforeEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, within } from '@testing-library/react';
import { router } from '@inertiajs/react';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import { colunasDaLista, filtrosParaATabela } from '@/Pages/Produto/_components/lista/colunas';
import { visibilidadePadrao, colunasOpcionais, type LinhaProduto, type PermissoesLista } from '@/Pages/Produto/_components/lista/tipos';

vi.mock('@inertiajs/react', () => ({ router: { get: vi.fn(), visit: vi.fn() } }));

/** As chaves de `$ordenaveis` em ProductController::buildProdutoListaPaginada (compra/venda só com a permissão). */
const CHAVES_DO_SERVIDOR = ['produto', 'estoque', 'tipo', 'categoria', 'marca', 'imposto', 'sku', 'compra', 'venda'];

const ORDENAVEIS = ['Produto', 'Preço de compra un.', 'Preço de venda', 'Estoque atual', 'Tipo', 'Categoria', 'Marca', 'SKU'];
const NAO_ORDENAVEIS = ['Ação', 'Imagem', 'Locais', 'Imposto', 'IMEI / série'];

const linha = (id: number, nome: string, estoque: number): LinhaProduto => ({
  id, nome, sku: `SKU-${id}`, tipo: 'single', categoria: 'Camisetas', subcategoria: null, unidade: 'Unidade', unidade_curta: 'Un',
  marca: 'Hering', imposto: null, imagem: null, controla_estoque: true, estoque, alerta: null, abaixo_do_alerta: false,
  inativo: false, nao_para_venda: false, serie: false, locais: ['Matriz'], variacoes: 1, campos: {}, anexo: null,
  compra_min: 10, compra_max: 10, venda_min: 20, venda_max: 20,
});
const LINHAS = [linha(1, 'Camiseta', 5), linha(2, 'Blusa', 9)];
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
  etiquetas: true, view_purchase_price: true, access_default_selling_price: true,
};
const CONTEXTO = { empresa: 'Empresa de teste', grupos_de_preco: 1, emite_nota: true, woocommerce: false, papel: null, rotulos_campos: {} };

/** Todas as colunas opcionais ligadas (inclusive "IMEI / série", que nasce desligada). */
const todasVisiveis = (): Record<string, boolean> => {
  const v = visibilidadePadrao(colunasOpcionais({}));
  for (const k of Object.keys(v)) v[k] = true;
  return v;
};

function montar(ordem: { sort: string; dir: 'asc' | 'desc' }, permissions: PermissoesLista = TUDO) {
  const colunas = colunasDaLista({ visiveis: todasVisiveis(), densa: false, permissions, contexto: CONTEXTO, aoEscolher: () => undefined });
  // Os filtros da URL como a tela os monta: o padrão (produto, asc) NÃO vai na URL.
  const daUrl: Record<string, string | number> = { per_page: 25 };
  if (ordem.sort !== 'produto') daUrl.sort = ordem.sort;
  if (ordem.dir === 'desc') daUrl.dir = 'desc';
  render(
    <DataTable<LinhaProduto>
      caption="Produtos do catálogo"
      columns={colunas}
      data={LINHAS}
      pagination={PAG}
      endpoint="/products"
      filters={filtrosParaATabela(daUrl, ordem)}
      showSearch={false}
      density="grid"
      rowKey={(l) => l.id}
    />,
  );
  const cabecalho = (rotulo: string) => {
    const th = screen.getAllByRole('columnheader').find((h) => (h.textContent ?? '').replace(/[↕↑↓]/g, '').trim() === rotulo);
    if (!th) throw new Error(`cabeçalho "${rotulo}" não existe`);
    return th;
  };
  return { colunas, cabecalho };
}
const pedido = () => vi.mocked(router.get).mock.calls.at(-1)?.[1] as Record<string, unknown>;

beforeEach(() => vi.mocked(router.get).mockClear());
afterEach(() => cleanup());

describe('UC-PIDX-13 · quais colunas ordenam', () => {
  it('as 8 colunas ordenáveis têm botão no cabeçalho', () => {
    const { cabecalho } = montar({ sort: 'produto', dir: 'asc' });
    for (const rotulo of ORDENAVEIS) {
      expect(within(cabecalho(rotulo)).queryByRole('button'), `"${rotulo}" deveria ter botão de ordenar`).not.toBeNull();
    }
  });

  it('as demais não têm (anti-vácuo: elas existem na tabela)', () => {
    const { cabecalho } = montar({ sort: 'produto', dir: 'asc' });
    for (const rotulo of NAO_ORDENAVEIS) {
      expect(within(cabecalho(rotulo)).queryByRole('button'), `"${rotulo}" não deveria ordenar`).toBeNull();
    }
  });

  it('o id de cada coluna ordenável é uma chave que o servidor aceita', () => {
    const { colunas } = montar({ sort: 'produto', dir: 'asc' });
    const ids = colunas.filter((c) => c.enableSorting === true).map((c) => c.id);
    expect(ids).toEqual(['produto', 'compra', 'venda', 'estoque', 'tipo', 'categoria', 'marca', 'sku']);
    for (const id of ids) expect(CHAVES_DO_SERVIDOR).toContain(id);
  });

  it('sem permissão de preço as colunas de preço não existem', () => {
    const { colunas } = montar({ sort: 'produto', dir: 'asc' }, { ...TUDO, view_purchase_price: false, access_default_selling_price: false });
    const ids = colunas.map((c) => c.id);
    expect(ids).toContain('estoque');
    expect(ids).not.toContain('compra');
    expect(ids).not.toContain('venda');
  });
});

describe('UC-PIDX-13 · clicar no cabeçalho', () => {
  it('em outra coluna: pede essa coluna, crescente', () => {
    const { cabecalho } = montar({ sort: 'produto', dir: 'asc' });
    fireEvent.click(within(cabecalho('Estoque atual')).getByRole('button'));
    expect(router.get).toHaveBeenCalledTimes(1);
    expect(vi.mocked(router.get).mock.calls[0]?.[0]).toBe('/products');
    expect(pedido()).toMatchObject({ sort: 'estoque', dir: 'asc', per_page: 25 });
  });

  it('de novo na coluna ativa: inverte para decrescente', () => {
    const { cabecalho } = montar({ sort: 'estoque', dir: 'asc' });
    fireEvent.click(within(cabecalho('Estoque atual')).getByRole('button'));
    expect(pedido()).toMatchObject({ sort: 'estoque', dir: 'desc' });
  });

  it('na coluna ativa decrescente: volta para crescente', () => {
    const { cabecalho } = montar({ sort: 'estoque', dir: 'desc' });
    fireEvent.click(within(cabecalho('Estoque atual')).getByRole('button'));
    expect(pedido()).toMatchObject({ sort: 'estoque', dir: 'asc' });
  });

  it('na ordem padrão (Produto, crescente), clicar em Produto inverte — o padrão não vai na URL', () => {
    const { cabecalho } = montar({ sort: 'produto', dir: 'asc' });
    fireEvent.click(within(cabecalho('Produto')).getByRole('button'));
    expect(pedido()).toMatchObject({ sort: 'produto', dir: 'desc' });
  });
});

describe('UC-PIDX-13 · a coluna ativa anuncia a direção', () => {
  it('na ordem padrão, Produto aparece como crescente e as outras sem direção', () => {
    const { cabecalho } = montar({ sort: 'produto', dir: 'asc' });
    expect(cabecalho('Produto').getAttribute('aria-sort')).toBe('ascending');
    expect(cabecalho('Estoque atual').getAttribute('aria-sort')).toBeNull();
  });

  it('ordenado por estoque decrescente, só Estoque atual anuncia', () => {
    const { cabecalho } = montar({ sort: 'estoque', dir: 'desc' });
    expect(cabecalho('Estoque atual').getAttribute('aria-sort')).toBe('descending');
    expect(cabecalho('Produto').getAttribute('aria-sort')).toBeNull();
  });
});
