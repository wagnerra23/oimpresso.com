// Tipo do produto na tela Novo produto (BlocoImpostosPrecos.tsx + Create.tsx + tipos.ts).
//
// A tela React oferece "Composto", mas não tem o editor da composição: sem `composition_variation_id`
// o composto nasce sem itens e com preço zero, e não entra em venda, compra, OS nem fabricação. A tela
// manda o composto para o cadastro clássico. O editor espera a D2 (SDD §10.6, decisão do [F] em
// 2026-11-17). O variável tem a grade desde então (UC-PCAD-32, produto-grade.test.tsx).
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

describe('UC-PCAD-11 · Novo produto — composto vai para o cadastro clássico', () => {
  it.each(['combo'] as const)('%s: avisa, leva ao clássico e não salva', (tipo) => {
    const { container } = render(
      <ProdutoCreate {...base} businessLocations={{ 3: 'Loja' }} duplicateProduct={duplicado(tipo)} />,
    )

    const aviso = screen.getByRole('alert')
    expect(aviso.textContent).toContain('composto')
    expect(screen.getByText('Cadastrar no formulário clássico').getAttribute('href')).toBe('/products/create?classico=1')
    // Todos os botões que salvam (o Salvar e os "Salvar e …" do rodapé) ficam desligados.
    const salvar = Array.from(screen.getByTestId('acoes-cadastro').querySelectorAll('button[type="submit"]'))
    expect(salvar.length).toBeGreaterThan(0)
    for (const b of salvar) expect((b as HTMLButtonElement).disabled).toBe(true)
    expect(enviar(container)).toBeNull()
  })

  it('variável: tem a grade (UC-PCAD-32), sem aviso nem link para o clássico', () => {
    render(<ProdutoCreate {...base} businessLocations={{ 3: 'Loja' }} duplicateProduct={duplicado('variable')} modelosVariacao={[]} />)

    expect(screen.queryByText('Cadastrar no formulário clássico')).toBeNull()
    expect(screen.getByRole('group', { name: 'Grade da variação' })).toBeTruthy()
  })

  it('simples: sem aviso e salva', () => {
    const { container } = render(
      <ProdutoCreate {...base} businessLocations={{ 3: 'Loja' }} duplicateProduct={duplicado('single')} />,
    )

    expect(screen.queryByText('Cadastrar no formulário clássico')).toBeNull()
    expect(enviar(container)).toMatchObject({ type: 'single' })
  })
})
