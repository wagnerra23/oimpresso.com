// Formulário de produto (Novo e Editar) com as listas que o servidor manda de verdade.
//
// O servidor acrescenta a chave '' às listas de unidades ("Selecionar") e de impostos ("nenhum")
// para os selects do Blade. O Select do Radix não aceita item com valor vazio: em 2026-10-16 a
// tela Novo produto aberta pela lista /products/unificado ficava EM BRANCO em produção, com
// "A <Select.Item /> must have a value prop that is not an empty string" no console.
// Este teste usa as listas com a chave vazia e prova que as duas telas renderizam.
import { render, screen } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'

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
        post: vi.fn(),
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
