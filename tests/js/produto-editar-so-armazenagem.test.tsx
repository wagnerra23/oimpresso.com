// UC-PEDIT-23 (CU-PROD-20, proposta #10765) — Editar de quem só pode mexer nos locais de armazenagem.
//
// Contrato: com `somenteArmazenagem`, a tela mostra o nome, o código e o bloco "Locais de
// armazenagem", sem nenhum campo de preço, imposto ou dados do produto; salvar vai em
// `PATCH /products/{id}/armazenagem` só com os campos dos locais.
import { render, screen, fireEvent } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'

const enviado: { metodo: string | null; url: string | null; payload: Record<string, unknown> | null } = {
  metodo: null,
  url: null,
  payload: null,
}

vi.mock('@inertiajs/react', async () => {
  const React = await import('react')
  return {
    Head: () => null,
    Deferred: ({ children }: { children: React.ReactNode }) => children,
    Link: ({ href, children, ...resto }: { href: string; children: React.ReactNode }) =>
      React.createElement('a', { href, ...resto }, children),
    useForm: (inicial: Record<string, unknown>) => {
      const [data] = React.useState(inicial)
      const transformar = React.useRef((d: Record<string, unknown>) => d)
      const enviar = (metodo: string) => (url: string) => {
        enviado.metodo = metodo
        enviado.url = url
        enviado.payload = transformar.current(data)
      }
      return {
        data,
        setData: () => {},
        transform: (fn: (d: Record<string, unknown>) => Record<string, unknown>) => {
          transformar.current = fn
        },
        post: enviar('post'),
        put: enviar('put'),
        patch: enviar('patch'),
        processing: false,
        errors: {},
      }
    },
  }
})
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: unknown }) => children }))

import ProdutoEdit from '@/Pages/Produto/Edit'

function abrir() {
  return render(
    <ProdutoEdit
      {...({} as never)}
      somenteArmazenagem
      product={{ id: 9, name: 'Lona 440g', sku: 'LON-440', localPadraoId: 2 } as never}
      rackDetails={{ 2: { location_id: 2, rack: 'A', row: '', position: '' } } as never}
      businessLocations={{ 1: 'Matriz', 2: 'Depósito' }}
      enderecoNoEstoque={{ prateleira: true, fileira: false, posicao: false }}
      saldoPorLocal={{ 2: 3 }}
    />,
  )
}

beforeEach(() => {
  enviado.metodo = null
  enviado.url = null
  enviado.payload = null
})

describe('UC-PEDIT-23 · Editar só com os locais de armazenagem', () => {
  it('mostra o nome, o código e o bloco de locais, e nenhum outro campo do produto', () => {
    abrir()
    expect(screen.getByTestId('editar-so-armazenagem')).toBeTruthy()
    expect(screen.getByText('LON-440')).toBeTruthy()
    expect(screen.getByTestId('bloco-armazenagem')).toBeTruthy()
    expect(screen.getByLabelText('Em estoque, Depósito').textContent).toBe('3,00')
    // Nada de preço, imposto ou dados do produto.
    expect(document.getElementById('name')).toBeNull()
    expect(document.getElementById('tax')).toBeNull()
    expect(document.getElementById('politica_preco')).toBeNull()
    expect(screen.queryByText('Impostos e preços')).toBeNull()
    expect(screen.queryByText('Salvar alterações')).toBeNull()
  })

  it('salvar manda PATCH na rota só de locais, com os campos dos locais e nada mais', () => {
    abrir()
    fireEvent.change(screen.getByLabelText('Prateleira, Depósito'), { target: { value: 'C7' } })
    fireEvent.click(screen.getByRole('button', { name: 'Salvar locais' }))
    expect(enviado.metodo).toBe('patch')
    expect(enviado.url).toBe('/products/9/armazenagem')
    expect(enviado.payload).toEqual({
      product_racks_update: { 2: { rack: 'C7', row: '', position: '' } },
      local_padrao_id: 2,
    })
  })
})
