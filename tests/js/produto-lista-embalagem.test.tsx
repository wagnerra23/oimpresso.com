// Consulta de produto (Produto/Index): o saldo também em embalagens inteiras, como rolos (UC-PIDX-39 · CV2).
// O servidor só manda a embalagem (nome e fator); a conta é da tela, e é ela que se prova aqui, por
// dois caminhos: (1) o número de rolos e o resto contra a conta feita à mão; (2) rolos × fator + resto
// volta ao saldo da linha. O lado do servidor está em tests/Feature/Produto/ProdutoListaEmbalagemTest.php.
//
// Cobre UC-PIDX-39 (resources/js/Pages/Produto/Index.casos.md).
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, cleanup } from '@testing-library/react';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import { colunasDaLista } from '@/Pages/Produto/_components/lista/colunas';
import {
  visibilidadePadrao, colunasOpcionais, saldoEmEmbalagens, embalagemTexto, type LinhaProduto, type PermissoesLista,
} from '@/Pages/Produto/_components/lista/tipos';

vi.mock('@inertiajs/react', () => ({ router: { get: vi.fn(), visit: vi.fn() } }));

const base = (parte: Partial<LinhaProduto>): LinhaProduto => ({
  id: 1, nome: 'Lona 440g', sku: 'LONA', tipo: 'single', categoria: null, subcategoria: null, unidade: 'Metro quadrado', unidade_curta: 'm²',
  marca: null, imposto: null, imagem: null, controla_estoque: true, estoque: 170, alerta: null, abaixo_do_alerta: false,
  inativo: false, nao_para_venda: false, serie: false, locais: ['Matriz'], variacoes: 1, campos: {}, anexo: null,
  embalagem: { nome: 'Rolo', fator: 80 },
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

describe('UC-PIDX-39 · saldo em embalagens inteiras', () => {
  it('as duas contas fecham: rolos × fator + resto = saldo', () => {
    // [saldo, fator, rolos à mão, resto à mão]
    const casos: Array<[number, number, number, number]> = [
      [160, 80, 2, 0],
      [170, 80, 2, 10],
      [80, 80, 1, 0],
      [250.5, 50, 5, 0.5],
      [4.2, 1.4, 3, 0], // ponto flutuante: 4.2 / 1.4 é 2.9999… em JS; não pode dar 2 rolos + 1,4
      [1234.5678, 100, 12, 34.5678],
    ];
    for (const [estoque, fator, rolos, resto] of casos) {
      const e = saldoEmEmbalagens(base({ estoque, embalagem: { nome: 'Rolo', fator } }));
      // Caminho 1: contra a conta feita à mão.
      expect(e).toEqual({ inteiras: rolos, resto });
      // Caminho 2: de volta ao saldo da linha.
      expect(Math.round((e!.inteiras * fator + e!.resto) * 10000)).toBe(Math.round(estoque * 10000));
    }
  });

  it('a célula mostra "2 × Rolo + 10 m²" abaixo do saldo, com o fator no title', () => {
    const c = montar([base({})]);
    const linha = c.querySelector('[data-contract="produto-lista-embalagem"]');
    expect(linha?.textContent).toBe('2 × Rolo + 10 m²');
    expect(linha?.getAttribute('title')).toBe('1 Rolo = 80 m²');
    expect(embalagemTexto(base({ estoque: 160 }))).toBe('2 × Rolo');
  });

  it('sem embalagem, abaixo de uma embalagem, saldo negativo ou sem controle de estoque: nada', () => {
    expect(embalagemTexto(base({ embalagem: undefined }))).toBeNull();
    expect(embalagemTexto(base({ estoque: 50 }))).toBeNull();
    expect(embalagemTexto(base({ estoque: -100 }))).toBeNull();
    expect(embalagemTexto(base({ controla_estoque: false, estoque: null }))).toBeNull();
    const c = montar([base({ embalagem: undefined })]);
    expect(c.querySelector('[data-contract="produto-lista-embalagem"]')).toBeNull();
    // Controle positivo: o saldo continua na célula.
    expect(c.textContent).toContain('170 m²');
  });
});
