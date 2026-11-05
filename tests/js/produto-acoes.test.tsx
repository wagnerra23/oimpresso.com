// Rodapé de ações da tela Novo produto (AcoesDoCadastro.tsx + Create.tsx), na forma do protótipo
// (produto-blade-forms.jsx): Cancelar, "Salvar e adicionar preços por grupo", "Salvar e adicionar
// outro" e Salvar. Cada "Salvar e …" manda o `submit_type` do botão equivalente do Blade
// (create.blade.php), que o ProductController@store já trata; o Salvar não manda nenhum e volta à lista.
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
  taxes: {},
  taxAttributes: {},
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

function clicar(rotulo: string) {
  enviado.payload = null
  // O nome é obrigatório (required): sem ele o próprio navegador barra o envio pelo botão.
  fireEvent.change(document.querySelector('#name') as HTMLInputElement, { target: { value: 'Banner 1x1' } })
  fireEvent.click(screen.getByRole('button', { name: rotulo }))
  return enviado.payload as Record<string, unknown> | null
}

describe('UC-PCAD-18 · Novo produto — rodapé de ações na forma do protótipo', () => {
  it('Salvar envia sem submit_type: o servidor volta para a lista', () => {
    render(<ProdutoCreate {...base} />)
    const payload = clicar('Salvar')
    expect(payload).not.toBeNull()
    expect(payload).not.toHaveProperty('submit_type')
  })

  it('"Salvar e adicionar outro" envia submit_type=save_n_add_another, como o Blade', () => {
    render(<ProdutoCreate {...base} />)
    expect(clicar('Salvar e adicionar outro')).toMatchObject({ submit_type: 'save_n_add_another' })
  })

  it('sem grupo de preço, o botão de preços por grupo não aparece, como no Blade', () => {
    render(<ProdutoCreate {...base} />)
    expect(screen.queryByRole('button', { name: 'Salvar e adicionar preços por grupo' })).toBeNull()
  })

  it('com grupo de preço, "Salvar e adicionar preços por grupo" envia submit_n_add_selling_prices', () => {
    render(<ProdutoCreate {...base} sellingPriceGroupCount={2} />)
    expect(clicar('Salvar e adicionar preços por grupo')).toMatchObject({
      submit_type: 'submit_n_add_selling_prices',
    })
  })

  it('o preço do produto simples continua indo no envio pelo botão do rodapé', () => {
    render(<ProdutoCreate {...base} />)
    expect(clicar('Salvar e adicionar outro')).toHaveProperty('single_dpp')
  })
})
