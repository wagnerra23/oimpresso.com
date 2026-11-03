// Navegação da lista de produtos para as telas React (UC-PIDX-36 · resources/js/Pages/Produto/Index.casos.md).
//
// A lista só navega por Inertia com a chave `useV2ProdutoCadastro` ligada e para as telas React
// prontas (Novo/duplicar, Histórico de estoque, Preços por tabela). Editar segue em página inteira
// (Blade) porque a edição React ainda não tem o preço do produto.
// Comando: npx vitest run tests/js/produto-navegacao.test.ts

import { describe, it, expect } from 'vitest';
import { abreTelaReact, modoDeNavegar } from '@/Pages/Produto/_components/lista/navegacao';

describe('UC-PIDX-36 navegação da lista para as telas React', () => {
  it('com a chave ligada, Novo, duplicar, Histórico e Preços abrem por Inertia', () => {
    for (const url of ['/products/create', '/products/create?d=7', '/products/stock-history/7', '/products/add-selling-prices/7']) {
      expect(modoDeNavegar(url, true)).toBe('inertia');
    }
  });

  it('com a chave desligada, tudo segue em página inteira (Blade)', () => {
    for (const url of ['/products/create', '/products/stock-history/7', '/products/add-selling-prices/7']) {
      expect(modoDeNavegar(url, false)).toBe('pagina');
    }
  });

  it('Editar, etiquetas e exportação seguem em página inteira mesmo com a chave', () => {
    for (const url of ['/products/7/edit', '/labels/show?product_id=7', '/products/download-excel', '/products?exportar=csv']) {
      expect(abreTelaReact(url)).toBe(false);
      expect(modoDeNavegar(url, true)).toBe('pagina');
    }
  });
});
