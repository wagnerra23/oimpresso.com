// Consulta de produto (Produto/Index): filtro "Uso" (UC-PIDX-44 · CV1). O filtro no servidor e a receita da
// Fabricação como fonte do "Material" são provados em tests/Feature/Produto/ProdutoListaUsoTest.php; aqui, o
// que a tela mostra: as opções que a empresa tem e o chip.
//
// Cobre UC-PIDX-44 (resources/js/Pages/Produto/Index.casos.md).
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import FiltrosListaProdutos from '@/Pages/Produto/_components/lista/FiltrosLista';
import { chipsDosFiltros, type FiltrosLista, type OpcoesLista, type Uso } from '@/Pages/Produto/_components/lista/tipos';

const OPCOES = {
  categorias: [], unidades: [], impostos: [], marcas: [], locais: [], modelos_dispositivo: null,
} as unknown as OpcoesLista;

const filtros = (parte: Partial<FiltrosLista> = {}): FiltrosLista => ({
  type: null, category_id: null, brand_id: null, unit_id: null, tax_id: null, location_id: null, active_state: null,
  not_for_selling: false, woocommerce_enabled: false, repair_model_id: null, natureza_fiscal: null, abaixo_do_alerta: false,
  falta_ncm: false, uso: null, q: '', sort: 'produto', dir: 'asc', per_page: 25, page: 1, aba: 'lista', estoque_pagina: 1,
  estoque_por_pagina: 25, ...parte,
});

function montar(usos: Uso[], f: FiltrosLista = filtros()) {
  const aplicar = vi.fn();
  render(<FiltrosListaProdutos filtros={f} opcoes={OPCOES} woocommerce={false} usos={usos} aplicar={aplicar} />);
  return aplicar;
}

afterEach(() => cleanup());

describe('UC-PIDX-44 · filtro "Uso"', () => {
  it('aparece com as opções que a empresa tem', () => {
    montar(['vender', 'material', 'servico']);
    expect(screen.getByRole('combobox', { name: 'Uso' })).toBeTruthy();
  });

  it('com uma opção só (sem Fabricação nem módulo Fiscal), não aparece', () => {
    montar(['vender']);
    // Controle positivo: o painel de filtros abriu.
    expect(screen.getByRole('combobox', { name: 'Situação' })).toBeTruthy();
    expect(screen.queryByRole('combobox', { name: 'Uso' })).toBeNull();
  });

  it('mostra o valor escolhido no gatilho', () => {
    montar(['vender', 'material'], filtros({ uso: 'material' }));
    expect(screen.getByRole('combobox', { name: 'Uso' }).textContent).toContain('Material');
  });

  it('vira um chip "Uso: …" na linha "Filtrando por"', () => {
    expect(chipsDosFiltros(filtros({ uso: 'servico' }), OPCOES)).toContainEqual({ chave: 'uso', rotulo: 'Uso: Serviço' });
    expect(chipsDosFiltros(filtros({ uso: 'vender' }), OPCOES)).toContainEqual({ chave: 'uso', rotulo: 'Uso: Para vender' });
    expect(chipsDosFiltros(filtros(), OPCOES).map((c) => c.chave)).not.toContain('uso');
  });
});
