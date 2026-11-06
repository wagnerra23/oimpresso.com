// "O preço de venda já tem o imposto?" nas telas Novo e Editar produto, na forma do protótipo
// (prototipo-ui/cowork/Wagner/produto-blade-forms.jsx). Troca o select "Imposto inclui no preço?" por
// dois botões de opção com um exemplo calculado pela alíquota. Grava o mesmo `tax_type` de antes.
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
  units: { 4: 'Unidade (UN)' },
  taxes: { 7: 'ICMS 18%' },
  taxAttributes: { 7: { 'data-rate': 18 } },
  barcodeTypes: { C128: 'Code 128' },
  barcodeDefault: 'C128',
  defaultProfitPercent: 25,
  duplicateProduct: null,
  subCategories: {},
  businessLocations: { 3: 'Loja' },
  rackDetails: null,
  sellingPriceGroupCount: 0,
  productTypes: { single: 'Simples' },
  warranties: {},
  commonSettings: {},
  enableExpiry: false,
  enableLot: false,
  enableRacks: false,
}

// Produto duplicado com o ICMS escolhido: a tela abre com o imposto já preenchido.
const comIcms = { ...base, duplicateProduct: { name: 'Lona', tax: 7, tax_type: 'exclusive', type: 'single' } }

function salvar() {
  enviado.payload = null
  fireEvent.change(document.querySelector('#name') as HTMLInputElement, { target: { value: 'Lona 440g' } })
  fireEvent.click(screen.getByRole('button', { name: 'Salvar' }))
  return enviado.payload as Record<string, unknown> | null
}

describe('O preço de venda já tem o imposto? — na forma do protótipo', () => {
  it('sem imposto escolhido não há pergunta, só o aviso', () => {
    render(<ProdutoCreate {...base} />)
    expect(screen.getByText('Sem imposto escolhido, não há o que perguntar aqui.')).toBeTruthy()
    expect(screen.queryByRole('radio')).toBeNull()
    expect(screen.queryByText('Imposto inclui no preço?')).toBeNull()
  })

  it('com ICMS escolhido pergunta pelo ICMS e o exemplo soma 18% ao preço', () => {
    render(<ProdutoCreate {...(comIcms as never)} />)
    expect(screen.getByText('O preço de venda já tem o ICMS?')).toBeTruthy()
    expect((screen.getByLabelText('Não, somar ao preço') as HTMLButtonElement).getAttribute('data-state')).toBe('checked')
    expect(screen.getByText(/o cliente paga R\$\s118,00\./)).toBeTruthy()
  })

  it('"Sim, já está no preço" grava tax_type=inclusive e o exemplo mostra o ICMS dentro', () => {
    render(<ProdutoCreate {...(comIcms as never)} />)
    fireEvent.click(screen.getByLabelText('Sim, já está no preço'))
    expect(screen.getByText(/com R\$\s15,25 de ICMS dentro\./)).toBeTruthy()
    expect(salvar()).toMatchObject({ tax_type: 'inclusive' })
  })

  it('"Não, somar ao preço" grava tax_type=exclusive, o mesmo valor de antes', () => {
    render(<ProdutoCreate {...(comIcms as never)} />)
    fireEvent.click(screen.getByLabelText('Sim, já está no preço'))
    fireEvent.click(screen.getByLabelText('Não, somar ao preço'))
    expect(salvar()).toMatchObject({ tax_type: 'exclusive' })
  })
})
