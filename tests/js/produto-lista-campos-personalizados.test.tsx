// Colunas dos campos personalizados na lista de produtos (Produto/Index) — com a tabela de VERDADE.
//
// ── POR QUE EXISTE ───────────────────────────────────────────────────────────
// A grade Blade tem uma coluna para cada campo personalizado 1 a 7, e só mostra as que a empresa
// rotulou (`product/index.blade.php`: `visible: $('#cf_N').text().length > 0`, com o rótulo vindo de
// `custom_labels.product.custom_field_N`). A lista React já fazia o mesmo, mas nada a defendia: o
// único teste dos campos era o da busca (`ProdutoListaConsultaTest`).
//
// ── O QUE MEDE ───────────────────────────────────────────────────────────────
// `colunasOpcionais` + `colunasDaLista` REAIS dentro do `shared/DataTable` REAL:
//   - só o campo rotulado entra no menu "Colunas", com o rótulo da empresa, e começa desligado
//     (protótipo `produto-blade.jsx`: `PB_COLS_PADRAO.cf1 = false`);
//   - ligado, a coluna mostra o valor do produto, e traço quando o produto não tem;
//   - campo sem rótulo não vira coluna, nem se a visibilidade dele estiver ligada.
//
// Cobre UC-PIDX-24 (resources/js/Pages/Produto/Index.casos.md).
// ⚠️ Nenhuma lane de CI roda este arquivo ainda (cada lane Vitest lista os próprios specs).
// Comando: npx vitest run tests/js/produto-lista-campos-personalizados.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, within } from '@testing-library/react';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import { colunasDaLista, filtrosParaATabela } from '@/Pages/Produto/_components/lista/colunas';
import { visibilidadePadrao, colunasOpcionais, type LinhaProduto, type PermissoesLista } from '@/Pages/Produto/_components/lista/tipos';

vi.mock('@inertiajs/react', () => ({ router: { get: vi.fn(), visit: vi.fn() } }));

const linha = (id: number, nome: string, campos: Record<string, string | null>): LinhaProduto => ({
  id, nome, sku: `SKU-${id}`, tipo: 'single', categoria: 'Camisetas', subcategoria: null, unidade: 'Unidade', unidade_curta: 'Un',
  marca: 'Hering', imposto: null, imagem: null, controla_estoque: true, estoque: 3, alerta: null, abaixo_do_alerta: false,
  inativo: false, nao_para_venda: false, serie: false, locais: ['Matriz'], variacoes: 1, campos, anexo: null,
});
const LINHAS = [
  linha(1, 'Com cor', { '1': 'Azul marinho', '2': 'Valor sem rótulo', '3': null }),
  linha(2, 'Sem cor', { '1': null, '2': null, '3': null }),
];
const PAG: PaginatorShape<LinhaProduto> = {
  data: LINHAS, total: 2, current_page: 1, last_page: 1, from: 1, to: 2,
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
// A empresa rotulou o campo 1 ("Cor") e o 3 ("Coleção"); o 2 ficou sem rótulo.
const ROTULOS: Record<string, string | null> = { '1': 'Cor', '2': null, '3': 'Coleção', '4': null, '5': null, '6': null, '7': null };
const CONTEXTO = { empresa: 'Empresa de teste', grupos_de_preco: 1, emite_nota: true, woocommerce: false, papel: null, rotulos_campos: ROTULOS };

function montar(visiveis: Record<string, boolean>) {
  const colunas = colunasDaLista({ visiveis, densa: false, permissions: PERMISSOES, contexto: CONTEXTO, aoEscolher: () => undefined });
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
const cabecalhos = () => screen.getAllByRole('columnheader').map((th) => th.textContent?.trim() ?? '');
const linhaDe = (nome: string) => {
  const tr = screen.getAllByRole('row').find((r) => within(r).queryByText(nome) !== null);
  if (!tr) throw new Error(`linha "${nome}" não existe`);
  return tr;
};

afterEach(() => cleanup());

describe('UC-PIDX-24 · colunas dos campos personalizados', () => {
  it('só os campos rotulados entram no menu Colunas, com o rótulo da empresa, desligados', () => {
    const opcionais = colunasOpcionais(ROTULOS).filter((c) => c.id.startsWith('cf'));
    expect(opcionais.map((c) => [c.id, c.rotulo, c.padrao])).toEqual([
      ['cf1', 'Cor', false],
      ['cf3', 'Coleção', false],
    ]);
  });

  it('na visibilidade padrão nenhuma coluna de campo aparece', () => {
    montar(visibilidadePadrao(colunasOpcionais(ROTULOS)));
    // Pré-condição anti-vácuo: a tabela montou com as colunas fixas.
    expect(cabecalhos()).toContain('Imagem');
    expect(cabecalhos()).not.toContain('Cor');
    expect(cabecalhos()).not.toContain('Coleção');
  });

  it('ligada, a coluna mostra o valor do produto, e traço quando não tem', () => {
    montar({ ...visibilidadePadrao(colunasOpcionais(ROTULOS)), cf1: true });
    expect(cabecalhos()).toContain('Cor');
    expect(within(linhaDe('Com cor')).getByText('Azul marinho')).toBeTruthy();
    const indice = cabecalhos().indexOf('Cor');
    const celulaSem = within(linhaDe('Sem cor')).getAllByRole('cell')[indice];
    expect(celulaSem.textContent?.trim()).toBe('—');
  });

  it('campo sem rótulo não vira coluna, mesmo com a visibilidade ligada', () => {
    montar({ ...visibilidadePadrao(colunasOpcionais(ROTULOS)), cf2: true });
    expect(screen.queryByText('Valor sem rótulo')).toBeNull();
  });
});
