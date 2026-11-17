// Consulta de produto (Produto/Index): filtro "Falta NCM" (UC-PIDX-42). O filtro no servidor, a regra igual à
// do card de Saúde fiscal e o link do card são provados em tests/Feature/Produto/ProdutoListaFaltaNcmTest.php
// e Modules/NfeBrasil/Tests/Feature/SaudeFiscalTest.php; aqui, o que a tela mostra e manda.
//
// Cobre UC-PIDX-42 (resources/js/Pages/Produto/Index.casos.md).
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import FiltrosListaProdutos from '@/Pages/Produto/_components/lista/FiltrosLista';
import { chipsDosFiltros, type FiltrosLista, type OpcoesLista } from '@/Pages/Produto/_components/lista/tipos';

const OPCOES = {
  categorias: [], unidades: [], impostos: [], marcas: [], locais: [], modelos_dispositivo: null,
} as unknown as OpcoesLista;

const filtros = (parte: Partial<FiltrosLista> = {}): FiltrosLista => ({
  type: null, category_id: null, brand_id: null, unit_id: null, tax_id: null, location_id: null, active_state: null,
  not_for_selling: false, woocommerce_enabled: false, repair_model_id: null, natureza_fiscal: null, abaixo_do_alerta: false,
  falta_ncm: false, q: '', sort: 'produto', dir: 'asc', per_page: 25, page: 1, aba: 'lista', estoque_pagina: 1, estoque_por_pagina: 25,
  ...parte,
});

function montar(f: FiltrosLista, emiteNota: boolean) {
  const aplicar = vi.fn();
  render(<FiltrosListaProdutos filtros={f} opcoes={OPCOES} woocommerce={false} emiteNota={emiteNota} aplicar={aplicar} />);
  return aplicar;
}

afterEach(() => cleanup());

describe('UC-PIDX-42 · filtro "Falta NCM"', () => {
  it('aparece para a empresa que emite nota e liga o filtro ao marcar', () => {
    const aplicar = montar(filtros(), true);
    const caixa = screen.getByRole('checkbox', { name: /falta ncm/i });
    expect(caixa.getAttribute('aria-checked')).toBe('false');
    caixa.click();
    expect(aplicar).toHaveBeenCalledWith({ falta_ncm: true });
  });

  it('marcado, desmarcar desliga o filtro', () => {
    const aplicar = montar(filtros({ falta_ncm: true }), true);
    const caixa = screen.getByRole('checkbox', { name: /falta ncm/i });
    expect(caixa.getAttribute('aria-checked')).toBe('true');
    caixa.click();
    expect(aplicar).toHaveBeenCalledWith({ falta_ncm: false });
  });

  it('não aparece para a empresa que não emite nota', () => {
    montar(filtros(), false);
    // Controle positivo: o painel de filtros abriu.
    expect(screen.getByRole('checkbox', { name: /não para venda/i })).toBeTruthy();
    expect(screen.queryByRole('checkbox', { name: /falta ncm/i })).toBeNull();
  });

  it('vira um chip "Falta NCM" na linha "Filtrando por"', () => {
    expect(chipsDosFiltros(filtros({ falta_ncm: true }), OPCOES)).toContainEqual({ chave: 'falta_ncm', rotulo: 'Falta NCM' });
    expect(chipsDosFiltros(filtros(), OPCOES).map((c) => c.chave)).not.toContain('falta_ncm');
  });
});
