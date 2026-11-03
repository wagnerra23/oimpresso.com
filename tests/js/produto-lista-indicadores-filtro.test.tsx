// Consulta de produto (Produto/Index): os indicadores "Abaixo do alerta" e "Inativos" filtram a lista ao clicar
// (UC-PIDX-35). O filtro no servidor e o número do indicador são provados em
// tests/Feature/Produto/ProdutoListaAbaixoDoAlertaTest.php; aqui, o que a tela faz com o clique.
//
// Cobre UC-PIDX-35 (resources/js/Pages/Produto/Index.casos.md).
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import IndicadoresLista from '@/Pages/Produto/_components/lista/IndicadoresLista';
import { chipsDosFiltros, type FiltrosLista, type KpisLista } from '@/Pages/Produto/_components/lista/tipos';

const KPIS: KpisLista = {
  total: 40, variaveis: 3, composicoes: 1, abaixo_do_alerta: 3, inativos: 2, valor_custo: null, valor_calculado_em: null,
} as KpisLista;

const filtros = (parte: Partial<FiltrosLista> = {}): FiltrosLista => ({
  type: null, category_id: null, brand_id: null, unit_id: null, tax_id: null, location_id: null, active_state: null,
  not_for_selling: false, woocommerce_enabled: false, repair_model_id: null, natureza_fiscal: null, abaixo_do_alerta: false,
  q: '', sort: 'produto', dir: 'asc', per_page: 25, page: 1, aba: 'lista', estoque_pagina: 1, estoque_por_pagina: 25,
  ...parte,
});

function montar(f: FiltrosLista) {
  const aplicar = vi.fn();
  render(<IndicadoresLista kpis={KPIS} filtros={f} aplicar={aplicar} />);
  return aplicar;
}

const botao = (rotulo: RegExp) => screen.getByRole('button', { name: rotulo });

afterEach(() => cleanup());

describe('UC-PIDX-35 · indicadores que filtram', () => {
  it('clicar em "Abaixo do alerta" liga o filtro; o 2º clique desliga', () => {
    const aplicar = montar(filtros());
    const alerta = botao(/abaixo do alerta/i);
    expect(alerta.getAttribute('aria-pressed')).toBe('false');
    alerta.click();
    expect(aplicar).toHaveBeenCalledWith({ abaixo_do_alerta: true });
    cleanup();

    const desligar = montar(filtros({ abaixo_do_alerta: true }));
    expect(botao(/abaixo do alerta/i).getAttribute('aria-pressed')).toBe('true');
    botao(/abaixo do alerta/i).click();
    expect(desligar).toHaveBeenCalledWith({ abaixo_do_alerta: false });
  });

  it('clicar em "Inativos" escreve a situação "Inativo"; o 2º clique tira', () => {
    const aplicar = montar(filtros());
    botao(/inativos/i).click();
    expect(aplicar).toHaveBeenCalledWith({ active_state: 'inactive' });
    cleanup();

    const tirar = montar(filtros({ active_state: 'inactive' }));
    expect(botao(/inativos/i).getAttribute('aria-pressed')).toBe('true');
    botao(/inativos/i).click();
    expect(tirar).toHaveBeenCalledWith({ active_state: null });
  });

  it('"Produtos cadastrados" e "Valor em estoque" continuam de leitura, sem botão', () => {
    montar(filtros());
    // Controle positivo: os dois que filtram são botões.
    expect(screen.getAllByRole('button')).toHaveLength(2);
    expect(screen.queryByRole('button', { name: /produtos cadastrados/i })).toBeNull();
    expect(screen.queryByRole('button', { name: /valor em estoque/i })).toBeNull();
    expect(screen.getByText('Produtos cadastrados')).toBeTruthy();
  });

  it('com o filtro ligado aparece o chip "Abaixo do alerta" em "Filtrando por"', () => {
    const opcoes = { categorias: [], marcas: [], unidades: [], impostos: [], locais: [], modelos_dispositivo: null };
    expect(chipsDosFiltros(filtros(), opcoes).map((c) => c.chave)).not.toContain('abaixo_do_alerta');
    expect(chipsDosFiltros(filtros({ abaixo_do_alerta: true }), opcoes)).toContainEqual({ chave: 'abaixo_do_alerta', rotulo: 'Abaixo do alerta' });
  });
});
