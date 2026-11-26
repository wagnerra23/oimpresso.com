// UC-PEDIT-36 — "Última alteração" no cabeçalho do Editar (AR-PROD-004, paridade com o "Alterar
// produto" do Delphi). Só leitura: a tela mostra a data e a hora que o edit() manda em
// `product.ultimaAlteracao` (updated_at, dd/mm/aaaa hh:mm) e não tem campo para mudá-la.
import { render, screen } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'

const enviado: { payload: Record<string, unknown> | null } = { payload: null }

vi.mock('@inertiajs/react', async () => {
  const React = await import('react')
  return {
    Head: () => null,
    // O Editar embrulha o card Produção (prop deferida) em <Deferred>: aqui a prop já vem pronta.
    Deferred: ({ children }: { children: React.ReactNode }) => children,
    Link: ({ href, children, ...resto }: { href: string; children: React.ReactNode }) =>
      React.createElement('a', { href, ...resto }, children),
    useForm: (inicial: Record<string, unknown>) => {
      const [data, setData] = React.useState(inicial)
      // O Edit.tsx passa o envio por `transform` (UC-PEDIT-11: o NCM só vai quando a pessoa mexeu).
      const transformar = React.useRef((d: Record<string, unknown>) => d)
      return {
        data,
        setData: (k: string, v: unknown) => setData((d) => ({ ...d, [k]: v })),
        transform: (fn: (d: Record<string, unknown>) => Record<string, unknown>) => {
          transformar.current = fn
        },
        put: () => {
          enviado.payload = transformar.current(data)
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
  permissions: { opening_stock: false, delete: false },
}


function editar(produtoExtra: Record<string, unknown> = {}) {
  return render(<ProdutoEdit {...(props as never)} product={{ ...produto, ...produtoExtra } as never} />)
}

describe('UC-PEDIT-36 · Última alteração', () => {
  it('mostra a data e a hora da última alteração ao lado do SKU', () => {
    editar({ ultimaAlteracao: '20/11/2026 14:35' })
    const linha = screen.getByTestId('produto-ultima-alteracao')
    expect(linha.textContent).toContain('Última alteração 20/11/2026 14:35')
    expect(linha.parentElement?.textContent).toContain('BAN-01')
  })

  it('é só leitura: não há campo de formulário com o valor', () => {
    editar({ ultimaAlteracao: '20/11/2026 14:35' })
    expect(screen.queryByDisplayValue('20/11/2026 14:35')).toBeNull()
  })

  it('sem data gravada, a linha não aparece', () => {
    editar({ ultimaAlteracao: null })
    expect(screen.queryByTestId('produto-ultima-alteracao')).toBeNull()
  })
})
