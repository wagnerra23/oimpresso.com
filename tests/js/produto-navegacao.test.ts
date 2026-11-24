// Navegação da lista de produtos para as telas React (UC-PIDX-36 · resources/js/Pages/Produto/Index.casos.md).
//
// A lista só navega por Inertia com a chave `useV2ProdutoCadastro` ligada e para as telas React
// prontas (Novo/duplicar, Editar, Histórico de estoque, Preços por tabela). Editar entrou em
// 2026-11-24 (decisão [W]).
// Comando: npx vitest run tests/js/produto-navegacao.test.ts

import { describe, it, expect } from 'vitest';
import { abreTelaReact, modoDeNavegar } from '@/Pages/Produto/_components/lista/navegacao';

describe('UC-PIDX-36 navegação da lista para as telas React', () => {
  it('com a chave ligada, Novo, duplicar, Editar, Histórico e Preços abrem por Inertia', () => {
    for (const url of ['/products/create', '/products/create?d=7', '/products/7/edit', '/products/stock-history/7', '/products/add-selling-prices/7']) {
      expect(modoDeNavegar(url, true)).toBe('inertia');
    }
  });

  it('com a chave desligada, tudo segue em página inteira (Blade)', () => {
    for (const url of ['/products/create', '/products/7/edit', '/products/stock-history/7', '/products/add-selling-prices/7']) {
      expect(modoDeNavegar(url, false)).toBe('pagina');
    }
  });

  it('etiquetas, exportação e URLs vizinhas de Editar seguem em página inteira mesmo com a chave', () => {
    for (const url of ['/products/7/edit/x', '/products/abc/edit', '/labels/show?product_id=7', '/products/download-excel', '/products?exportar=csv']) {
      expect(abreTelaReact(url)).toBe(false);
      expect(modoDeNavegar(url, true)).toBe('pagina');
    }
  });
});
