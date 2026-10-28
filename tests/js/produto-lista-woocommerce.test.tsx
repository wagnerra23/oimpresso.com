// Marca do WooCommerce na célula Produto da lista de produtos (Produto/Index) — com a tabela de VERDADE.
//
// ── POR QUE EXISTE ───────────────────────────────────────────────────────────
// A lista Blade punha o ícone `fa-wordpress` sob o nome do produto que sincroniza com o
// WooCommerce (`ProductController@index`, editColumn 'product': `$is_woocommerce &&
// ! $row->woocommerce_disable_sync`). A lista React recebia o mesmo dado (`woocommerce_sync`,
// só quando o módulo está instalado) e não o desenhava.
//
// ── O QUE MEDE ───────────────────────────────────────────────────────────────
// `colunasDaLista` REAL dentro do `shared/DataTable` REAL:
//   - produto com `woocommerce_sync: true` mostra a marca, com nome acessível;
//   - `woocommerce_sync: false` (sincronização desligada) não mostra;
//   - sem o campo (módulo não instalado) não mostra;
//   - no modo compacto (sem a 2ª linha) a marca continua.
//
// Cobre UC-PIDX-23 (resources/js/Pages/Produto/Index.casos.md).
// ⚠️ Nenhuma lane de CI roda este arquivo ainda (cada lane Vitest lista os próprios specs).
// Comando: npx vitest run tests/js/produto-lista-woocommerce.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, within } from '@testing-library/react';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import { colunasDaLista, filtrosParaATabela } from '@/Pages/Produto/_components/lista/colunas';
import { visibilidadePadrao, colunasOpcionais, type LinhaProduto, type PermissoesLista } from '@/Pages/Produto/_components/lista/tipos';

vi.mock('@inertiajs/react', () => ({ router: { get: vi.fn(), visit: vi.fn() } }));

const linha = (id: number, nome: string, woo: boolean | undefined): LinhaProduto => ({
  id, nome, sku: `SKU-${id}`, tipo: 'single', categoria: 'Camisetas', subcategoria: null, unidade: 'Unidade', unidade_curta: 'Un',
  marca: 'Hering', imposto: null, imagem: null, controla_estoque: true, estoque: 3, alerta: null, abaixo_do_alerta: false,
  inativo: false, nao_para_venda: false, serie: false, locais: ['Matriz'], variacoes: 1, campos: {}, anexo: null,
  ...(woo === undefined ? {} : { woocommerce_sync: woo }),
});
const LINHAS = [linha(1, 'Sincroniza', true), linha(2, 'Desligado', false), linha(3, 'Sem modulo', undefined)];
const PAG: PaginatorShape<LinhaProduto> = {
  data: LINHAS, total: 3, current_page: 1, last_page: 1, from: 1, to: 3,
  links: [
    { url: null, label: '&laquo; Anterior', active: false },
    { url: '/products?page=1', label: '1', active: true },
    { url: null, label: 'Próxima &raquo;', active: false },
  ],
};
const PERMISSOES: PermissoesLista = {
  create: true, update: true, delete: true, opening_stock: true, view: true, stock_report: true,
  etiquetas: true, view_purchase_price: true, access_default_selling_price: true,
};
const CONTEXTO = { empresa: 'Empresa de teste', grupos_de_preco: 1, emite_nota: true, woocommerce: true, papel: null, rotulos_campos: {} };

function montar(densa: boolean) {
  const colunas = colunasDaLista({
    visiveis: visibilidadePadrao(colunasOpcionais({})), densa, permissions: PERMISSOES, contexto: CONTEXTO, aoEscolher: () => undefined,
  });
  render(
    <DataTable<LinhaProduto>
      caption="Produtos do catálogo"
      columns={colunas}
      data={LINHAS}
      pagination={PAG}
      endpoint="/products"
      filters={filtrosParaATabela({ per_page: 25 }, { sort: 'produto', dir: 'asc' })}
      showSearch={false}
      density="grid"
      rowKey={(l) => l.id}
    />,
  );
}
const linhaDe = (nome: string) => {
  const tr = screen.getAllByRole('row').find((r) => within(r).queryByText(nome) !== null);
  if (!tr) throw new Error(`linha "${nome}" não existe`);
  return tr;
};

afterEach(() => cleanup());

describe('UC-PIDX-23 · marca do WooCommerce na célula Produto', () => {
  it('mostra a marca só no produto que sincroniza', () => {
    montar(false);
    expect(within(linhaDe('Sincroniza')).getByRole('img', { name: 'Sincroniza com o WooCommerce' })).toBeTruthy();
    expect(within(linhaDe('Desligado')).queryByRole('img', { name: 'Sincroniza com o WooCommerce' })).toBeNull();
    expect(within(linhaDe('Sem modulo')).queryByRole('img', { name: 'Sincroniza com o WooCommerce' })).toBeNull();
  });

  it('no modo compacto a marca continua', () => {
    montar(true);
    expect(within(linhaDe('Sincroniza')).getByRole('img', { name: 'Sincroniza com o WooCommerce' })).toBeTruthy();
  });
});
