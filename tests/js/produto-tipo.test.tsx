// Tipo do produto na tela Novo produto (BlocoIdentificacao.tsx + Create.tsx + tipos.ts).
//
// A tela React oferece "Variável" e "Composto", mas não tem o editor da grade nem da composição.
// Sem `product_variation`, o ProductController@store cria o variável SEM variação nenhuma; sem
// `composition_variation_id`, o composto nasce sem itens e com preço zero. Nos dois casos o produto
// não entra em venda, compra, OS nem fabricação. Até o editor existir, a tela só salva o simples e
// manda os outros para o cadastro clássico.
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

const duplicado = (type: 'single' | 'variable' | 'combo') => ({
  id: 9,
  name: 'Camiseta (copy)',
  brand_id: null,
  unit_id: 4,
  category_id: 1,
  sub_category_id: null,
  tax: null,
  tax_type: 'exclusive' as const,
  barcode_type: 'C128',
  sku: '',
  alert_quantity: null,
  weight: null,
  product_description: null,
  type,
})

function enviar(container: HTMLElement) {
  enviado.payload = null
  fireEvent.submit(container.querySelector('form#produto-create-form') as HTMLFormElement)
  return enviado.payload
}

describe('UC-PCAD-11 · Novo produto — variável e composto vão para o cadastro clássico', () => {
  it.each(['variable', 'combo'] as const)('%s: avisa, leva ao clássico e não salva', (tipo) => {
    const { container } = render(
      <ProdutoCreate {...base} businessLocations={{ 3: 'Loja' }} duplicateProduct={duplicado(tipo)} />,
    )

    const aviso = screen.getByRole('alert')
    expect(aviso.textContent).toContain(tipo === 'variable' ? 'variável' : 'composto')
    expect(screen.getByText('Cadastrar no formulário clássico').getAttribute('href')).toBe('/products/create?classico=1')
    expect((screen.getByText('Salvar produto').closest('button') as HTMLButtonElement).disabled).toBe(true)
    expect(enviar(container)).toBeNull()
  })

  it('simples: sem aviso e salva', () => {
    const { container } = render(
      <ProdutoCreate {...base} businessLocations={{ 3: 'Loja' }} duplicateProduct={duplicado('single')} />,
    )

    expect(screen.queryByText('Cadastrar no formulário clássico')).toBeNull()
    expect(enviar(container)).toMatchObject({ type: 'single' })
  })
})
