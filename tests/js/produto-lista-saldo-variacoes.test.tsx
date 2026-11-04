// Consulta de produto (Produto/Index): o saldo de cada variação aparece na própria lista (UC-PIDX-37 · D7).
// A conta do servidor (soma das variações = saldo da linha) é provada em
// tests/Feature/Produto/ProdutoListaSaldoVariacoesTest.php; aqui, o que a tela faz com ela.
//
// Cobre UC-PIDX-37 (resources/js/Pages/Produto/Index.casos.md).
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, within } from '@testing-library/react';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import { colunasDaLista } from '@/Pages/Produto/_components/lista/colunas';
import { visibilidadePadrao, colunasOpcionais, type LinhaProduto, type PermissoesLista } from '@/Pages/Produto/_components/lista/tipos';

vi.mock('@inertiajs/react', () => ({ router: { get: vi.fn(), visit: vi.fn() } }));

const base = (parte: Partial<LinhaProduto>): LinhaProduto => ({
  id: 1, nome: 'Camiseta', sku: 'CAM', tipo: 'variable', categoria: null, subcategoria: null, unidade: 'Unidade', unidade_curta: 'Un',
  marca: null, imposto: null, imagem: null, controla_estoque: true, estoque: 12, alerta: null, abaixo_do_alerta: false,
  inativo: false, nao_para_venda: false, serie: false, locais: ['Matriz'], variacoes: 3, campos: {}, anexo: null,
  ...parte,
});

const VARIAVEL = base({
  saldo_variacoes: [
    { nome: 'Cor - Azul', sku: 'CAM-1', saldo: 7 },
    { nome: 'Cor - Preto', sku: 'CAM-2', saldo: 5 },
    { nome: 'Cor - Branco', sku: 'CAM-3', saldo: 0 },
  ],
});
const SIMPLES = base({ id: 2, nome: 'Caneca', sku: 'CAN', tipo: 'single', estoque: 4, variacoes: 1 });

const TUDO: PermissoesLista = {
  create: true, update: true, delete: true, opening_stock: true, view: true, stock_report: true,
  etiquetas: true, view_purchase_price: true, access_default_selling_price: true,
};
const CONTEXTO = { empresa: 'Empresa de teste', grupos_de_preco: 1, emite_nota: true, woocommerce: false, papel: null, rotulos_campos: {} };

function montar(linhas: LinhaProduto[], aoClicarLinha = vi.fn(), densa = false) {
  const colunas = colunasDaLista({ visiveis: visibilidadePadrao(colunasOpcionais({})), densa, permissions: TUDO, contexto: CONTEXTO, aoEscolher: () => undefined });
  const pag: PaginatorShape<LinhaProduto> = { data: linhas, total: linhas.length, current_page: 1, last_page: 1, from: 1, to: linhas.length, links: [] };
  render(
    <DataTable<LinhaProduto>
      caption="Produtos do catálogo"
      columns={colunas}
      data={linhas}
      pagination={pag}
      endpoint="/products"
      showSearch={false}
      rowKey={(l) => l.id}
      onRowClick={aoClicarLinha}
    />,
  );
  return aoClicarLinha;
}

afterEach(() => cleanup());

describe('UC-PIDX-37 · saldo por variação na lista', () => {
  it('produto variável tem o gatilho "3 variações" e o clique mostra cada variação, com o total igual ao saldo da linha', () => {
    const abriuPainel = montar([VARIAVEL]);
    const gatilho = screen.getByRole('button', { name: /saldo por variação de camiseta/i });
    expect(within(gatilho).getByText('3 variações')).toBeTruthy();

    fireEvent.click(gatilho);
    const painel = screen.getByRole('dialog', { name: /saldo por variação de camiseta/i });
    const linhas = within(painel).getAllByRole('row');
    expect(linhas.map((r) => r.textContent)).toEqual([
      'Cor - AzulCAM-17 Un',
      'Cor - PretoCAM-25 Un',
      'Cor - BrancoCAM-30 Un',
      'Total12 Un',
    ]);
    // O clique no gatilho não abre o painel do produto.
    expect(abriuPainel).not.toHaveBeenCalled();
  });

  it('produto simples não tem o gatilho', () => {
    montar([SIMPLES]);
    expect(screen.queryByRole('button', { name: /saldo por variação/i })).toBeNull();
    // Controle positivo: o saldo continua na célula.
    expect(screen.getByText('4 Un')).toBeTruthy();
  });

  it('variável que não controla estoque (sem saldo_variacoes) não tem o gatilho', () => {
    montar([base({ controla_estoque: false, estoque: null })]);
    expect(screen.queryByRole('button', { name: /saldo por variação/i })).toBeNull();
  });

  it('na densidade compacta o gatilho continua, sem a 2ª linha', () => {
    montar([VARIAVEL], vi.fn(), true);
    const gatilho = screen.getByRole('button', { name: /saldo por variação de camiseta/i });
    expect(within(gatilho).queryByText('3 variações')).toBeNull();
    expect(within(gatilho).getByText('12 Un')).toBeTruthy();
  });
});
