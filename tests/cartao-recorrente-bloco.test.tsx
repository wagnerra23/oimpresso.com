// CartaoRecorrenteBloco — bloco do drawer da assinatura (US-RB-011 · UC-RBCC-05).
//
// O bloco só aparece em produção com RB_SALVAR_CARTAO ligada (desligada por padrão), então nem o CI
// visual nem um screenshot de produção o mostram: este teste é a prova de render dos dois estados.

import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

import CartaoRecorrenteBloco from '@/Pages/RecurringBilling/_components/CartaoRecorrenteBloco';

afterEach(() => cleanup());

describe('CartaoRecorrenteBloco', () => {
  it('com cartão salvo: mostra bandeira e final, nada mais do cartão', () => {
    const { container } = render(<CartaoRecorrenteBloco cartao={{ ultimos_4: '8829', bandeira: 'MASTERCARD' }} />);
    screen.getByText('MASTERCARD final 8829. Cada fatura é cobrada nele no vencimento.');
    expect(container.querySelector('[data-contract="cartao-recorrente"]')).not.toBeNull();
  });

  it('sem bandeira: usa "Cartão"', () => {
    render(<CartaoRecorrenteBloco cartao={{ ultimos_4: '4242', bandeira: null }} />);
    screen.getByText('Cartão final 4242. Cada fatura é cobrada nele no vencimento.');
  });

  it('sem cartão salvo: explica quando ele passa a existir', () => {
    render(<CartaoRecorrenteBloco cartao={{ ultimos_4: null, bandeira: null }} />);
    screen.getByText('Nenhum cartão salvo. O cartão fica salvo quando o cliente paga a primeira fatura no cartão.');
    expect(screen.queryByText(/final/)).toBeNull();
  });
});
