// Pergunta do imposto no Novo produto, na forma do protótipo (produto-blade-forms.jsx): duas
// opções com o exemplo de quanto o cliente paga; some quando não há imposto; o título do bloco
// resume o imposto escolhido. Só forma: o envio segue levando tax_type (UC-PCAD-03).
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
  taxes: { 7: 'ICMS 18%' },
  taxAttributes: { 7: { 'data-rate': 18 } },
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

const comImposto = (tax: number | null) => ({ ...duplicado('single'), tax })

describe('Novo produto — pergunta do imposto', () => {
  it('sem imposto: não pergunta nada e explica por quê', () => {
    render(<ProdutoCreate {...base} businessLocations={{ 3: 'Loja' }} duplicateProduct={comImposto(null)} />)

    expect(screen.getByText('Sem imposto escolhido, não há o que perguntar aqui.')).toBeTruthy()
    expect(screen.queryByRole('radio', { name: 'Não, somar ao preço' })).toBeNull()
    expect(screen.queryByText(/imposto ICMS 18%/)).toBeNull()
  })

  it('UC-PCAD-03 · com imposto: duas opções, "somar ao preço" marcada, exemplo e resumo no título', () => {
    const { container } = render(<ProdutoCreate {...base} businessLocations={{ 3: 'Loja' }} duplicateProduct={comImposto(7)} />)

    const grupo = screen.getByRole('radiogroup', { name: 'O preço de venda já tem o ICMS?' })
    expect(grupo).toBeTruthy()
    expect(screen.getByRole('radio', { name: 'Não, somar ao preço' }).getAttribute('aria-checked')).toBe('true')
    expect(screen.getByTestId('exemplo-imposto').textContent).toBe('Exemplo: venda de R$ 100,00 → o cliente paga R$ 118,00.')
    expect(screen.getByText('imposto ICMS 18%')).toBeTruthy()

    fireEvent.click(screen.getByRole('radio', { name: 'Sim, já está no preço' }))
    expect(screen.getByTestId('exemplo-imposto').textContent).toBe(
      'Exemplo: venda de R$ 100,00 → o cliente paga R$ 100,00, com R$ 15,25 de ICMS dentro.',
    )
    expect(enviar(container)?.tax_type).toBe('inclusive')
  })
})
