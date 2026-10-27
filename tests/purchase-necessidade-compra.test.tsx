// NecessidadeDeCompra — US-COM-030 · UC-REQC-07 (resources/js/Pages/PurchaseRequisition/Index.casos.md).
// A seção só mostra o que o servidor calculou: quanto comprar, a conta e a data de pedir com "Atrasado".

import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

import NecessidadeDeCompra, { type LinhaNecessidade } from '@/Pages/PurchaseRequisition/_components/NecessidadeDeCompra';

afterEach(cleanup);

const lona: LinhaNecessidade = {
  chave: '1:9', insumo: 'Lona 440g', local: 'Matriz', fornecedor: 'Fornecedor A',
  saldo: 3, comprometido: 8, minimo: 5, a_caminho: 3, necessidade: 7,
  prazo_dias: 4, pedir_ate: '08/11/2026', atrasado: true,
};

describe('NecessidadeDeCompra', () => {
  it('mostra quanto comprar, a conta e a data de pedir com o aviso de atraso', () => {
    const { container } = render(<NecessidadeDeCompra linhas={[lona]} />);
    expect(screen.getByText('Lona 440g')).toBeTruthy();
    expect(container.textContent).toContain('8 + 5 − 3 − 3');
    expect(container.textContent).toContain('08/11/2026');
    expect(container.textContent).toContain('4 d');
    expect(screen.getByText('Atrasado')).toBeTruthy();
    expect(container.textContent).toContain('1 já passou da data de pedir');
  });

  it('sem nada a comprar, a seção não aparece', () => {
    const { container } = render(<NecessidadeDeCompra linhas={[]} />);
    expect(container.querySelector('[data-contract="necessidade-compra"]')).toBeNull();
  });
});
