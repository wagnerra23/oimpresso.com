/**
 * Cancelar a NF-e pergunta se a venda vai junto (R-NFE-044 · decisão [W] 2026-10-26).
 *
 * @covers-us UC-FNFE-51
 *
 * Render do NotaDrawer de verdade: abre o modal, escreve o motivo, escolhe e confirma. O que se
 * prova é o que a tela MANDA ao backend (`escopo`), e que o padrão é "só a nota" — o comportamento
 * de antes. O que o backend faz com o escopo é o `AcoesCancelarEscopoTest` (PHP).
 */
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
  Link: ({ children }: { children: React.ReactNode }) => <a href="#">{children}</a>,
  router: { visit: vi.fn(), get: vi.fn(), post: (...a: unknown[]) => post(...a) },
}));

import NotaDrawer from '@/Pages/Fiscal/_components/NotaDrawer';

const nota = {
  id: 42, num: 7, serie: 1, modelo: 55, key: null, status: 'autorizada', cstat: 100, motivo: null,
  value: 100, emittedAtIso: new Date(Date.now() - 36e5).toISOString(), when: null, transactionId: 9,
  dest: 'CLIENTE', cnpj: null, cpf: null, uf: 'SC', items: 1, cancelavel: true,
};

function abrirModal() {
  render(<NotaDrawer nota={nota} sefazCodes={{}} onClose={() => {}} />);
  fireEvent.click(screen.getByRole('button', { name: /cancelar/i }));
  fireEvent.change(screen.getByPlaceholderText(/cliente desistiu/i), {
    target: { value: 'Cliente desistiu da compra inteira' },
  });
}

describe('UC-FNFE-51 · cancelar pergunta o escopo', () => {
  beforeEach(() => post.mockReset());

  it('o padrão é cancelar só a nota (como antes)', () => {
    abrirModal();
    fireEvent.click(screen.getByRole('button', { name: 'Cancelar só a nota' }));

    expect(post).toHaveBeenCalledTimes(1);
    expect(post.mock.calls[0][0]).toBe('/fiscal/acoes/nfe/42/cancelar');
    expect(post.mock.calls[0][1]).toEqual({ motivo: 'Cliente desistiu da compra inteira', escopo: 'nota' });
  });

  it('escolhendo "a venda junto", manda escopo venda e o botão diz o que vai acontecer', () => {
    abrirModal();
    fireEvent.click(screen.getByRole('radio', { name: /a venda junto/i }));
    fireEvent.click(screen.getByRole('button', { name: 'Cancelar nota e venda' }));

    expect(post.mock.calls[0][1]).toEqual({ motivo: 'Cliente desistiu da compra inteira', escopo: 'venda' });
  });

  it('a opção da venda diz o que acontece e quando não cancela (US-SELL-077)', () => {
    abrirModal();

    expect(screen.getByText(/o estoque volta e o\s+título sem pagamento é cancelado/)).toBeTruthy();
    expect(screen.getByText(/Venda com dinheiro recebido não cancela/)).toBeTruthy();
  });
});
