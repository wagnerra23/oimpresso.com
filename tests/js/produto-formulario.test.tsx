// Formulário de produto (Novo e Editar) com as listas que o servidor manda de verdade.
//
// O servidor acrescenta a chave '' às listas de unidades ("Selecionar") e de impostos ("nenhum")
// para os selects do Blade. O Select do Radix não aceita item com valor vazio: em 2026-10-16 a
// tela Novo produto aberta pela lista /products/unificado ficava EM BRANCO em produção, com
// "A <Select.Item /> must have a value prop that is not an empty string" no console.
// Este teste usa as listas com a chave vazia e prova que as duas telas renderizam.
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
        put: vi.fn(),
        processing: false,
        errors: {},
      }
    },
  }
})
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: unknown }) => children }))

import ProdutoCreate from '@/Pages/Produto/Create'
import ProdutoEdit from '@/Pages/Produto/Edit'
import { opcoesDe } from '@/Pages/Produto/_components/formulario/tipos'

// As listas como o ProductController@create/@edit as manda (forDropdown com "show none").
const listasDoServidor = {
  categories: { 1: 'Banner' },
  brands: { 3: 'Marca A' },
  units: { '': 'Selecionar', 4: 'Unidade (UN)', 5: 'Metro (M)' },
  taxes: { 1: 'ICMS 18%', 2: 'ISS 5%', '': 'nenhum' },
  taxAttributes: {},
  barcodeTypes: { C128: 'Code 128', EAN13: 'EAN-13' },
  subCategories: {},
  businessLocations: { 1: 'Matriz' },
  rackDetails: null,
  productTypes: { single: 'Simples', variable: 'Variável', combo: 'Combo' },
  warranties: { 7: '90 dias' },
}

const secoes = ['Identificação', 'Preço & Imposto', 'Estoque', 'Mais opções']

describe('opcoesDe', () => {
  it('tira a chave vazia e mantém as outras na ordem', () => {
    expect(opcoesDe({ '': 'Selecionar', 4: 'Unidade (UN)', 5: 'Metro (M)' })).toEqual([
      ['4', 'Unidade (UN)'],
      ['5', 'Metro (M)'],
    ])
    expect(opcoesDe({ C128: 'Code 128' })).toEqual([['C128', 'Code 128']])
  })
})

describe('Novo produto com as listas do servidor', () => {
  it('renderiza as 4 seções em vez de quebrar', () => {
    render(
      <ProdutoCreate
        {...(listasDoServidor as never)}
        barcodeDefault="C128"
        defaultProfitPercent={25}
        duplicateProduct={null}
        sellingPriceGroupCount={0}
        commonSettings={{}}
        enableExpiry={false}
        enableLot={false}
        enableRacks={false}
      />,
    )
    for (const s of secoes) expect(screen.getByText(s)).toBeTruthy()
  })

  it('sem imposto escolhido, o campo mostra "Nenhuma"', () => {
    render(
      <ProdutoCreate
        {...(listasDoServidor as never)}
        barcodeDefault="C128"
        defaultProfitPercent={25}
        duplicateProduct={null}
        sellingPriceGroupCount={0}
        commonSettings={{}}
        enableExpiry={false}
        enableLot={false}
        enableRacks={false}
      />,
    )
    expect(document.querySelector('#tax')?.textContent).toContain('Nenhuma')
  })
})

// UC-PCAD-12 — a validade do Novo produto. O ProductController@store só grava a validade quando
// vêm o número E o período (`expiry_period_type`). A tela mandava o período sempre vazio: a
// validade digitada era descartada. Agora segue o Blade: meses por padrão; com a validade lançada
// na compra (`add_expiry`), o campo fica escondido e vai como 12 meses.
describe('UC-PCAD-12 · Novo produto — validade', () => {
  const novo = (enableExpiry: boolean, expiryType?: string) =>
    render(
      <ProdutoCreate
        {...(listasDoServidor as never)}
        barcodeDefault="C128"
        defaultProfitPercent={25}
        duplicateProduct={null}
        sellingPriceGroupCount={0}
        commonSettings={{}}
        enableExpiry={enableExpiry}
        expiryType={expiryType}
        enableLot={false}
        enableRacks={false}
      />,
    )
  const salvar = (container: HTMLElement) => {
    enviado.payload = null
    fireEvent.submit(container.querySelector('form#produto-create-form') as HTMLFormElement)
    return enviado.payload as Record<string, unknown>
  }

  it('validade pela fabricação: "Expira em" em meses, e o envio leva o número com o período', () => {
    const { container } = novo(true, 'add_manufacturing')

    expect(screen.getByText('Expira em')).toBeTruthy()
    expect(document.querySelector('#expiry_period_type')?.textContent).toContain('meses')
    fireEvent.change(document.querySelector('#expiry_period') as HTMLInputElement, { target: { value: '6' } })

    const envio = salvar(container)
    expect(envio.expiry_period).toBe('6')
    expect(envio.expiry_period_type).toBe('months')
  })

  it('validade lançada na compra: campo escondido e o envio leva 12 meses, como o Blade', () => {
    const { container } = novo(true, 'add_expiry')

    expect(document.querySelector('#expiry_period')).toBeNull()
    const envio = salvar(container)
    expect(envio.expiry_period).toBe('12')
    expect(envio.expiry_period_type).toBe('months')
  })

  it('empresa sem validade: o campo não aparece', () => {
    novo(false)
    expect(document.querySelector('#expiry_period')).toBeNull()
  })
})

describe('Editar produto com as listas do servidor', () => {
  it('renderiza as 4 seções e mostra o imposto do produto', () => {
    render(
      <ProdutoEdit
        {...(listasDoServidor as never)}
        product={{
          id: 9, name: 'Lona 440g', sku: 'LON-440', type: 'single', brandId: 3, unitId: 5,
          subUnitIds: null, categoryId: 1, subCategoryId: null, tax: 1, taxType: 'exclusive',
          origemMercadoria: 0, barcodeType: 'C128', enableStock: true, alertQuantity: null,
          weight: null, productDescription: null, productLocations: [1], image: null,
          warrantyId: null, productCustomField1: null, productCustomField2: null,
          productCustomField3: null, productCustomField4: null,
        }}
        subUnits={{}}
        permissions={{ opening_stock: false, delete: false }}
      />,
    )
    for (const s of secoes) expect(screen.getByText(s)).toBeTruthy()
    expect(document.querySelector('#tax')?.textContent).toContain('ICMS 18%')
  })
})
