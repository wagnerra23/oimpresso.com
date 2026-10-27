// CartaoRecorrenteBloco — bloco do drawer da assinatura (US-RB-011/013 · UC-RBCC-11).
//
// Em produção nenhuma assinatura tem cartão salvo enquanto RB_SALVAR_CARTAO estiver desligada, então o
// screenshot só mostra o estado "nenhum cartão salvo": este teste é a prova de render com cartão e
// tentativas. Sem jest-dom (não está no setup — ver tests/js/setup.ts).

import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

import CartaoRecorrenteBloco from '@/Pages/RecurringBilling/_components/CartaoRecorrenteBloco';

afterEach(() => cleanup());

describe('CartaoRecorrenteBloco', () => {
  it('UC-RBCC-11 · sem cartão salvo: explica como o cartão é salvo, sem tentativas', () => {
    render(<CartaoRecorrenteBloco cartao={{ ultimos_4: null, bandeira: null, tentativas: [] }} />);
    screen.getByText('Nenhum cartão salvo. O cartão fica salvo quando o cliente paga a primeira fatura no cartão.');
    expect(screen.queryByText(/tentativa/)).toBeNull();
  });

  it('UC-RBCC-11 · com cartão: bandeira e final, e cada tentativa com status e motivo', () => {
    render(
      <CartaoRecorrenteBloco
        cartao={{
          ultimos_4: '8829',
          bandeira: 'MASTERCARD',
          tentativas: [
            { n: 1, status: 'soft_decline', em: '2031-04-01T10:00:00', motivo: 'Saldo insuficiente.' },
            { n: 2, status: 'hard_decline', em: '2031-04-02T10:00:00', motivo: 'Cartão vencido.' },
          ],
        }}
      />,
    );
    screen.getByText('MASTERCARD final 8829. Cada fatura é cobrada nele no vencimento.');
    expect(screen.getByText('1ª tentativa').parentElement?.textContent).toContain('01/04/2031 · Recusa leve — tenta de novo');
    expect(screen.getByText('2ª tentativa').parentElement?.textContent).toContain('Recusa definitiva — cliente avisado');
    screen.getByText('Cartão vencido.');
  });
});
