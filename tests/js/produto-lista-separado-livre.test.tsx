// Consulta de produto (Produto/Index): separado para vendas e livre na coluna Estoque (UC-PIDX-38 · D5 · CV4).
// A conta (reservas ativas; livre = saldo − separado) é provada em
// tests/Feature/Produto/ProdutoListaSeparadoLivreTest.php; aqui, o que a tela mostra.
//
// Cobre UC-PIDX-38 (resources/js/Pages/Produto/Index.casos.md).
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import { colunasDaLista } from '@/Pages/Produto/_components/lista/colunas';
import { visibilidadePadrao, colunasOpcionais, separadoTexto, type LinhaProduto, type PermissoesLista } from '@/Pages/Produto/_components/lista/tipos';

vi.mock('@inertiajs/react', () => ({ router: { get: vi.fn(), visit: vi.fn() } }));

const base = (parte: Partial<LinhaProduto>): LinhaProduto => ({
  id: 1, nome: 'Lona 440g', sku: 'LONA', tipo: 'single', categoria: null, subcategoria: null, unidade: 'Metro quadrado', unidade_curta: 'm²',
  marca: null, imposto: null, imagem: null, controla_estoque: true, estoque: 50, alerta: null, abaixo_do_alerta: false,
  inativo: false, nao_para_venda: false, serie: false, locais: ['Matriz'], variacoes: 1, campos: {}, anexo: null,
  ...parte,
});

const TUDO: PermissoesLista = {
  create: true, update: true, delete: true, opening_stock: true, view: true, stock_report: true,
  etiquetas: true, view_purchase_price: true, access_default_selling_price: true,
};
const CONTEXTO = { empresa: 'Empresa de teste', grupos_de_preco: 1, emite_nota: true, woocommerce: false, papel: null, rotulos_campos: {} };

function montar(linhas: LinhaProduto[]) {
  const colunas = colunasDaLista({ visiveis: visibilidadePadrao(colunasOpcionais({})), densa: false, permissions: TUDO, contexto: CONTEXTO, aoEscolher: () => undefined });
  const pag: PaginatorShape<LinhaProduto> = { data: linhas, total: linhas.length, current_page: 1, last_page: 1, from: 1, to: linhas.length, links: [] };
  const { container } = render(
    <DataTable<LinhaProduto> caption="Produtos do catálogo" columns={colunas} data={linhas} pagination={pag} endpoint="/products" showSearch={false} rowKey={(l) => l.id} />,
  );
  return container;
}

afterEach(() => cleanup());

describe('UC-PIDX-38 · separado para vendas e livre', () => {
  it('com reserva, a célula mostra o separado e o livre abaixo do saldo', () => {
    const c = montar([base({ separado: 20, livre: 30 })]);
    const linha = c.querySelector('[data-contract="produto-lista-separado"]');
    expect(linha?.textContent).toBe('20 m² separados30 m² livres');
    expect(linha?.getAttribute('title')).toBe('20 m² separados para vendas · 30 m² livres');
    // Controle positivo: o saldo continua lá.
    expect(screen.getByText('50 m²')).toBeTruthy();
  });

  it('sem reserva não há a linha', () => {
    const c = montar([base({})]);
    expect(c.querySelector('[data-contract="produto-lista-separado"]')).toBeNull();
    expect(screen.getByText('50 m²')).toBeTruthy();
  });

  it('livre zero ou negativo fica em destaque', () => {
    const c = montar([base({ estoque: 2, separado: 3, livre: -1 })]);
    const linha = c.querySelector('[data-contract="produto-lista-separado"]');
    expect(linha?.textContent).toBe('3 m² separados-1 m² livres');
    expect(linha?.className).toContain('text-[var(--neg)]');
  });

  it('o texto não aparece em produto sem controle de estoque, mesmo que o dado venha', () => {
    expect(separadoTexto(base({ controla_estoque: false, estoque: null, separado: 4, livre: 0 }))).toBeNull();
  });
});
