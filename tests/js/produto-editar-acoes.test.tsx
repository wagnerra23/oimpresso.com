// UC-PEDIT-35 — rodapé do Editar com os dois "Salvar e adicionar …" do edit.blade.php (item 6 do
// relatório de pendências, 2026-11-24). O Editar tinha só Cancelar, "Salvar e adicionar outro" e
// "Salvar alterações". Condições do Blade: preços por grupo só com grupo cadastrado; estoque inicial só
// com `product.opening_stock`, desligado quando o produto não controla estoque (serviço).
import { render, screen, fireEvent } from '@testing-library/react'
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


function clicar(rotulo: string) {
  enviado.payload = null
  fireEvent.click(screen.getByRole('button', { name: rotulo }))
  return enviado.payload as Record<string, unknown> | null
}

function editar(extra: Record<string, unknown> = {}, produtoExtra: Record<string, unknown> = {}) {
  return render(<ProdutoEdit {...(props as never)} product={{ ...produto, ...produtoExtra } as never} {...(extra as never)} />)
}

describe('UC-PEDIT-35 · Salvar e adicionar preços por grupo', () => {
  it('sem grupo de preço, o botão não aparece, como no Blade', () => {
    editar({ sellingPriceGroupCount: 0 })
    expect(screen.queryByRole('button', { name: 'Salvar e adicionar preços por grupo' })).toBeNull()
  })

  it('com grupo de preço, envia submit_n_add_selling_prices', () => {
    editar({ sellingPriceGroupCount: 1 })
    expect(clicar('Salvar e adicionar preços por grupo')).toMatchObject({ submit_type: 'submit_n_add_selling_prices' })
  })
})

describe('UC-PEDIT-35 · Salvar e adicionar estoque inicial', () => {
  it('sem product.opening_stock o botão não aparece, como o @can do Blade', () => {
    editar()
    expect(screen.queryByRole('button', { name: 'Salvar e adicionar estoque inicial' })).toBeNull()
  })

  it('com a permissão, envia o update_n_edit_opening_stock do edit.blade.php', () => {
    editar({ permissions: { opening_stock: true, delete: false } })
    expect(clicar('Salvar e adicionar estoque inicial')).toMatchObject({ submit_type: 'update_n_edit_opening_stock' })
  })

  it('produto que não controla estoque (serviço) deixa o botão desligado', () => {
    editar({ permissions: { opening_stock: true, delete: false } }, { enableStock: false })
    const botao = screen.getByRole('button', { name: 'Salvar e adicionar estoque inicial' }) as HTMLButtonElement
    expect(botao.disabled).toBe(true)
  })
})
