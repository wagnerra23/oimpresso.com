// Cadastro e edição do cliente em página inteira (`Pages/Cliente/Create.tsx` e `Edit.tsx`, corpo em
// `_form/ClienteForm.tsx`): o grupo de clientes "Nenhum" chega do servidor com id vazio — o
// `CustomerGroup::forDropdown` põe `'' => 'Nenhum'` na frente da lista — e o Radix recusa
// `<SelectItem value="">`. O erro derrubava a página inteira: tela em branco em produção para
// qualquer empresa, medido em 2026-11-21 ("A <Select.Item /> must have a value prop that is not an
// empty string"). A gaveta da lista de clientes não usa este formulário e seguia funcionando.
//
// Os testes de servidor (Wave1CreateInertiaTest, ClienteEditInertiaTest) só conferem o nome do
// componente Inertia, por isso o CI ficava verde com a página quebrada. Aqui o formulário é
// desenhado de verdade, com a lista no formato que o ContactController entrega.
//
// Cobre UC-CCRE-05 (Create.casos.md) e UC-CEDI-05 (Edit.casos.md).
//
// Lane: Vitest do `Frontend / Vite build` (.github/workflows/ci.yml).
// Comando: npx vitest run tests/js/cliente-form-grupo-nenhum.test.tsx

import { describe, it, expect, afterEach } from 'vitest'
import { render, screen, cleanup, fireEvent } from '@testing-library/react'
import { ClienteForm } from '@/Pages/Cliente/_form/ClienteForm'
import type { ClienteFormShared, CustomerGroup } from '@/Pages/Cliente/_form/cliente-form-types'

// Formato de `customer_groups` que o ContactController@create e @edit entregam hoje.
const GRUPOS_DO_SERVIDOR: CustomerGroup[] = [
  { id: '', name: 'Nenhum' },
  { id: 3, name: 'Atacado' },
]

function dados(extra: Partial<ClienteFormShared> = {}): ClienteFormShared {
  return {
    type: 'customer',
    contact_type_radio: 'person',
    first_name: '',
    middle_name: '',
    last_name: '',
    supplier_business_name: '',
    tax_number: '',
    mobile: '',
    landline: '',
    email: '',
    address_line_1: '',
    city: '',
    state: '',
    zip_code: '',
    shipping_address: '',
    customer_group_id: '',
    opening_balance: '0',
    credit_limit: '',
    cpf_cnpj: '',
    rg: '',
    inscricao_estadual: '',
    inscricao_municipal: '',
    indicador_ie: '',
    nome_fantasia: '',
    consumidor_final: false,
    contribuinte: true,
    regime: '',
    suframa: '',
    ...extra,
  }
}

function desenhar(customerGroups: CustomerGroup[], extra?: Partial<ClienteFormShared>) {
  return render(
    <ClienteForm
      data={dados(extra)}
      setData={() => {}}
      errors={{}}
      types={{ customer: 'Cliente' }}
      customerGroups={customerGroups}
      isJuridica={false}
      onCnpjLookup={() => {}}
      processing={false}
      submitLabel="Salvar cliente"
      cancelHref="/cliente"
      onSubmit={(e) => e.preventDefault()}
    />,
  )
}

const campoGrupo = () => screen.queryByRole('combobox', { name: 'Grupo de clientes' })

afterEach(cleanup)

describe('UC-CCRE-05 · o cadastro abre com o "Nenhum" que vem do servidor', () => {
  it('UC-CCRE-05 o formulário aparece e o grupo lista "Nenhum" uma vez só', () => {
    desenhar(GRUPOS_DO_SERVIDOR)

    const campo = campoGrupo()
    expect(campo).not.toBeNull()
    expect(campo!.textContent).toContain('Nenhum')

    fireEvent.keyDown(campo!, { key: 'ArrowDown' })
    const opcoes = screen.getAllByRole('option').map((o) => o.textContent)
    expect(opcoes).toEqual(['— Nenhum —', 'Atacado'])
  })

  it('UC-CCRE-05 empresa sem grupo cadastrado: o formulário aparece e o campo de grupo não', () => {
    desenhar([{ id: '', name: 'Nenhum' }])

    expect(screen.getByRole('combobox', { name: 'Tipo de contato' })).not.toBeNull()
    expect(campoGrupo()).toBeNull()
  })
})

describe('UC-CEDI-05 · a edição abre com o grupo do cliente selecionado', () => {
  it('UC-CEDI-05 cliente do grupo Atacado: a edição aparece com Atacado no campo', () => {
    desenhar(GRUPOS_DO_SERVIDOR, { customer_group_id: '3' })

    expect(campoGrupo()!.textContent).toContain('Atacado')
  })
})
