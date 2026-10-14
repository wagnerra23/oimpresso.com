/**
 * TransactionPayment/Edit — a tela abre com a lista de contas real.
 *
 * @covers-us UC-TPEDT-08
 *
 * O `accountsDropdown` (o mesmo do modal Blade) devolve `"" => "nenhum"` como 1ª entrada.
 * O Radix Select recusa item com value vazio e derruba a tela inteira — medido em produção
 * em 2026-10-14. Este teste usa essa forma de dado, não uma lista limpa.
 */
import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'

vi.mock('@inertiajs/react', async () => {
  const React = await import('react')
  return {
    Head: () => null,
    router: { visit: vi.fn(), get: vi.fn() },
    useForm: (inicial: Record<string, unknown>) => {
      const [data, setDataState] = React.useState(inicial)
      return {
        data,
        setData: (k: string, v: unknown) => setDataState((d) => ({ ...d, [k]: v })),
        errors: {},
        processing: false,
        put: vi.fn(),
        transform: vi.fn(),
      }
    },
  }
})
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }))

import Edit from '@/Pages/TransactionPayment/Edit'

// O Radix Select só monta os itens com o menu aberto; o jsdom não tem estas APIs de ponteiro.
Element.prototype.hasPointerCapture ??= () => false
Element.prototype.releasePointerCapture ??= () => {}
Element.prototype.scrollIntoView ??= () => {}

const props = {
  payment_line: {
    id: 16482,
    amount: '150.0000',
    method: 'cash',
    paid_on: '2026-05-27 11:52:24',
    payment_ref_no: 'PGV2026/0071',
    note: null,
    card_holder_name: null,
    card_number: null,
    card_transaction_number: null,
    cheque_number: null,
    bank_account_number: null,
    transaction_no: null,
    account_id: null,
  },
  transaction: {
    id: 69292,
    ref_no: '',
    type: 'sell',
    final_total: '150.0000',
    payment_status: 'paid',
    contact: { id: 12, name: 'Cliente Teste', supplier_business_name: null, type: 'customer' },
  },
  payment_types: { cash: 'Dinheiro', card: 'Cartão' },
  // forma real do accountsDropdown($business_id, true, false, true)
  accounts: { '': 'nenhum', 8: 'CAIXA (Saldo: 0,00)', 9: 'BANCO TESTE (Saldo: 0,00)' } as Record<string, string>,
}

describe('TransactionPayment/Edit', () => {
  // UC-TPEDT-08 — com a entrada "" vinda do backend, a tela ainda monta o formulário
  it('abre o formulário quando a lista de contas traz a entrada vazia do Blade', () => {
    render(<Edit {...(props as never)} />)

    expect(screen.getByText('Conta bancária')).not.toBeNull()

    // abrir o select é o que monta os SelectItem — é aí que o item de value "" derrubava a tela
    const trigger = document.getElementById('account_id') as HTMLElement
    fireEvent.keyDown(trigger, { key: 'Enter' })

    expect(screen.getByRole('option', { name: 'CAIXA (Saldo: 0,00)' })).not.toBeNull()
    // a entrada "" => "nenhum" não vira opção; "— Nenhuma —" aparece uma vez só
    expect(screen.queryByRole('option', { name: 'nenhum' })).toBeNull()
    expect(screen.getAllByRole('option', { name: '— Nenhuma —' })).toHaveLength(1)
  })
})
