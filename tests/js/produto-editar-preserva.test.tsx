// Editar produto devolve no envio o que a tela não mostra (Edit.tsx + tipos.ts) — UC-PEDIT-10.
//
// O ProductController@update grava '' em todo `product_custom_fieldN` (1 a 20) que não vem no
// request e, com a empresa usando validade, zera a validade que não vem. A tela Editar mandava só os
// campos 1 a 4 e não mandava a validade: salvar por ela apagava os campos 5 a 20 e a validade.
// O servidor não muda — quem passa a mandar tudo é a tela.
import { render, fireEvent } from '@testing-library/react'
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
      return {
        data,
        setData: (k: string, v: unknown) => setData((d) => ({ ...d, [k]: v })),
        put: () => {
          enviado.payload = data
        },
        processing: false,
        errors: {},
      }
    },
  }
})
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: unknown }) => children }))

import ProdutoEdit from '@/Pages/Produto/Edit'

const produto = {
  id: 9,
  name: 'Banner lona',
  sku: 'BAN-01',
  type: 'single' as const,
  brandId: null,
  unitId: 4,
  subUnitIds: null,
  categoryId: 1,
  subCategoryId: null,
  tax: null,
  taxType: 'exclusive' as const,
  origemMercadoria: 0,
  barcodeType: 'C128',
  enableStock: true,
  alertQuantity: null,
  weight: null,
  productDescription: null,
  productLocations: [3],
  image: null,
  warrantyId: null,
  camposPersonalizados: {} as Record<number, string | null>,
  expiryPeriod: null as number | null,
  expiryPeriodType: null as string | null,
}

const props = {
  categories: { 1: 'Banner' },
  brands: {},
  units: { '': 'Selecionar', 4: 'Unidade (UN)' },
  subUnits: {},
  taxes: { '': 'Nenhum' },
  taxAttributes: {},
  barcodeTypes: { C128: 'Code 128' },
  subCategories: {},
  businessLocations: { 3: 'Loja' },
  rackDetails: null,
  productTypes: { single: 'Simples' },
  warranties: {},
  permissions: { opening_stock: true, delete: false },
}

function salvar(container: HTMLElement) {
  enviado.payload = null
  fireEvent.submit(container.querySelector('form#produto-edit-form') as HTMLFormElement)
  return enviado.payload as Record<string, unknown>
}

describe('UC-PEDIT-10 · Editar não apaga o que a tela não mostra', () => {
  it('devolve no envio os 20 campos personalizados gravados, inclusive os que a tela não mostra', () => {
    const { container } = render(
      <ProdutoEdit
        {...props}
        product={{ ...produto, camposPersonalizados: { 1: 'Ref. interna', 5: 'Lona fosca', 20: 'Vinil 3M' } }}
      />,
    )

    const envio = salvar(container)
    expect(envio.product_custom_field1).toBe('Ref. interna')
    expect(envio.product_custom_field5).toBe('Lona fosca')
    expect(envio.product_custom_field20).toBe('Vinil 3M')
    // Sem valor gravado, vai vazio — e vai: a chave ausente é o que o servidor apagaria.
    expect(envio.product_custom_field7).toBe('')
    const chaves = Object.keys(envio).filter((k) => k.startsWith('product_custom_field'))
    expect(chaves).toHaveLength(20)
  })

  it('devolve no envio a validade gravada', () => {
    const { container } = render(
      <ProdutoEdit {...props} product={{ ...produto, expiryPeriod: 12, expiryPeriodType: 'months' }} />,
    )

    const envio = salvar(container)
    expect(envio.expiry_period).toBe('12')
    expect(envio.expiry_period_type).toBe('months')
  })

  it('sem validade gravada, manda os dois vazios (o servidor grava "não aplicável")', () => {
    const { container } = render(<ProdutoEdit {...props} product={produto} />)

    const envio = salvar(container)
    expect(envio.expiry_period).toBe('')
    expect(envio.expiry_period_type).toBe('')
  })
})
