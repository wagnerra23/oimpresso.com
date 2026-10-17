// Locais do negócio na tela Novo produto (BlocoLocais.tsx + Create.tsx).
//
// Até 2026-10-16 a tela Novo produto React mandava `product_locations: []` e não tinha o campo:
// o produto nascia sem local e não aparecia para venda em local nenhum. A regra é a do cadastro
// Blade (create.blade.php `$default_location`): um local só já vem marcado; com mais de um, quem
// cadastra escolhe.
import { render, screen, fireEvent } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'

const enviado: { payload: Record<string, unknown> | null } = { payload: null }

vi.mock('@inertiajs/react', async () => {
  const React = await import('react')
  return {
    Head: () => null,
    Link: ({ href, children, ...resto }: { href: string; children: React.ReactNode }) =>
      React.createElement('a', { href, ...resto }, children),
    useForm: (inicial: Record<string, unknown>) => {
      const [data, setData] = React.useState(inicial)
      const transformar = React.useRef((d: Record<string, unknown>) => d)
      return {
        data,
        setData: (k: string, v: unknown) => setData((d) => ({ ...d, [k]: v })),
        transform: (fn: (d: Record<string, unknown>) => Record<string, unknown>) => {
          transformar.current = fn
        },
        post: () => {
          enviado.payload = transformar.current(data)
        },
        processing: false,
        errors: {},
      }
    },
  }
})
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: unknown }) => children }))

import ProdutoCreate from '@/Pages/Produto/Create'

const base = {
  categories: { 1: 'Banner' },
  brands: {},
  units: { '': 'Selecionar', 4: 'Unidade (UN)' },
  taxes: { '': 'Nenhum' },
  taxAttributes: {},
  barcodeTypes: { C128: 'Code 128' },
  barcodeDefault: 'C128',
  defaultProfitPercent: 25,
  duplicateProduct: null,
  subCategories: {},
  rackDetails: null,
  sellingPriceGroupCount: 0,
  productTypes: { single: 'Simples' },
  warranties: {},
  commonSettings: {},
  enableExpiry: false,
  enableLot: false,
  enableRacks: false,
}

function enviar(container: HTMLElement) {
  fireEvent.submit(container.querySelector('form#produto-create-form') as HTMLFormElement)
  return enviado.payload
}

describe('UC-PCAD-10 · Novo produto — locais do negócio', () => {
  it('com um local só, ele já vem marcado e vai no envio', () => {
    enviado.payload = null
    const { container } = render(<ProdutoCreate {...base} businessLocations={{ 3: 'Loja Centro' }} />)

    expect(screen.getByLabelText('Loja Centro').getAttribute('data-state')).toBe('checked')
    expect(enviar(container)).toMatchObject({ product_locations: [3] })
  })

  it('com mais de um, nenhum vem marcado; o que a pessoa marcar é o que vai', () => {
    enviado.payload = null
    const { container } = render(
      <ProdutoCreate {...base} businessLocations={{ 3: 'Loja Centro', 5: 'Depósito' }} />,
    )

    expect(screen.getByLabelText('Loja Centro').getAttribute('data-state')).toBe('unchecked')
    expect(screen.getByLabelText('Depósito').getAttribute('data-state')).toBe('unchecked')

    fireEvent.click(screen.getByLabelText('Depósito'))
    expect(enviar(container)).toMatchObject({ product_locations: [5] })
  })
})
