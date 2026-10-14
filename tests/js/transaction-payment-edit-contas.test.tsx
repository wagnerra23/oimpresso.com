/**
 * A tela de edição de pagamento abre com a lista de contas que o servidor manda de verdade.
 *
 * @covers-us UC-TPEDT-02
 *
 * FONTE DO CONTRATO: `Edit.charter.md` §Props (`accounts` = dropdown de contas, opcional) e o
 * UC-TPEDT-02 de `Edit.casos.md` (a tela abre com o pagamento e as listas). O Pest prova o lado
 * servidor; ele não renderiza React, por isso não viu a tela quebrar.
 *
 * O QUE QUEBRAVA (medido em produção, biz=1, 2026-10-14): `Account::forDropdown` devolve as contas
 * MAIS a entrada `"" => "nenhum"`. O `Edit.tsx` mapeava tudo para `<SelectItem value={id}>`, e o
 * Radix lança em `value=""` — a página inteira ficava em branco. É a lápide §5 2026-06-29
 * (opção data-driven sem filtrar valor vazio). A forma dos dados abaixo é a medida em produção,
 * com os nomes trocados por fictícios.
 */
import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  router: { visit: vi.fn() },
  useForm: (inicial: Record<string, unknown>) => ({
    data: inicial,
    setData: vi.fn(),
    transform: vi.fn(),
    put: vi.fn(),
    processing: false,
    errors: {},
  }),
}));
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

import Edit from '@/Pages/TransactionPayment/Edit';

const props = {
  payment_line: {
    id: 1,
    amount: '150.0000',
    method: 'cash',
    paid_on: '2026-04-24 18:41:00',
    note: null,
    account_id: null,
    transaction_id: 10,
  },
  transaction: { id: 10, business_id: 98, final_total: '150.0000', payment_status: 'paid', contact: { name: 'Cliente Teste' } },
  payment_types: { cash: 'Dinheiro', card: 'Cartão' },
  // Forma real: chaves numéricas + a chave vazia que o forDropdown acrescenta.
  accounts: { '8': 'Conta Teste A (Saldo: 0,00)', '9': 'Conta Teste B (Saldo: 0,00)', '': 'nenhum' },
};

describe('TransactionPayment/Edit — lista de contas', () => {
  it('UC-TPEDT-02 · abre com a lista de contas do servidor, inclusive a entrada vazia "nenhum"', () => {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    render(<Edit {...(props as any)} />);

    expect(screen.getByText('Conta bancária')).toBeTruthy();

    // Abre o select de contas como o operador (no navegador os itens montam junto com a tela;
    // no jsdom só ao abrir). No navegador o <SelectItem value=""> LANÇA e a tela some; no jsdom
    // o Radix não lança — então o que este caso prova é a ausência da opção vazia (assert abaixo).
    // Mordida provada em 2026-10-14: sem o filtro, este caso cai nesse assert.
    const gatilho = document.getElementById('account_id') as HTMLElement;
    fireEvent.keyDown(gatilho, { key: 'ArrowDown' });

    expect(screen.getByRole('option', { name: /Conta Teste A/ })).toBeTruthy();
    expect(screen.getByRole('option', { name: /Nenhuma/ })).toBeTruthy();
    // A chave vazia do forDropdown não vira opção: o "nenhuma" é o sentinela `none`.
    expect(screen.queryByRole('option', { name: /^nenhum$/ })).toBeNull();
  });

  it('UC-TPEDT-02 · controle: sem a entrada vazia a tela abre igual (o filtro não esconde conta real)', () => {
    const semVazio = { ...props, accounts: { '8': 'Conta Teste A (Saldo: 0,00)' } };
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    render(<Edit {...(semVazio as any)} />);

    expect(screen.getByText('Conta bancária')).toBeTruthy();
  });
});
