/**
 * UC-RLT-07 (CV3): a tela de Lotes filtrada por um produto, como chega do painel da consulta de produto.
 * A etiqueta "Produto: …" mostra o filtro e o tira; os outros filtros seguem com o produto.
 */
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';
import { router } from '@inertiajs/react';
import LotesIndex from '@/Pages/Relatorios/Lotes/Index';

vi.mock('@inertiajs/react', () => ({ router: { get: vi.fn(), visit: vi.fn() } }));
afterEach(() => { cleanup(); vi.clearAllMocks(); });

const base = {
  linhas: [], rodape: [], podeRastrear: false,
  paginacao: { atual: 1, ultima: 1, total: 0 },
  locais: [], categorias: [], marcas: [], unidades: [],
};

describe('UC-RLT-07 · Lotes filtrados por produto', () => {
  it('sem filtro de produto não há etiqueta', () => {
    render(<LotesIndex {...base} filtros={{ location_id: '', category_id: '', brand_id: '', unit_id: '' }} produto={null} />);
    expect(screen.queryByRole('button', { name: /Remover o filtro do produto/ })).toBeNull();
  });

  it('com filtro: a etiqueta mostra o produto e, ao clicar, tira só o filtro de produto', () => {
    render(<LotesIndex {...base} filtros={{ location_id: '3', category_id: '', brand_id: '', unit_id: '', product_id: '42' }} produto="Lona 440g" />);
    const etiqueta = screen.getByRole('button', { name: 'Remover o filtro do produto Lona 440g' });
    expect(etiqueta.textContent).toContain('Produto: Lona 440g');
    fireEvent.click(etiqueta);
    expect(router.get).toHaveBeenCalledWith(
      '/reports/lot-report',
      expect.objectContaining({ tela: 'nova', location_id: '3', product_id: '' }),
      expect.objectContaining({ only: expect.arrayContaining(['linhas', 'filtros', 'produto']) }),
    );
  });
});
