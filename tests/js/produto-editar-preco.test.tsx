// Preço do produto simples na tela Editar (Edit.tsx + precos.ts) — UC-PEDIT-16 · Edit.casos.md.
//
// A tela mostra o preço gravado e só o manda se a pessoa mexeu no preço ou no imposto: o update()
// preserva o que não vem, e devolver o gravado arredondado reescreveria custo/venda de 4 casas.
// Mexeu: vão os cinco campos do cadastro (a conta do Blade, precos.ts) e a variação.
// Comando: npx vitest run tests/js/produto-editar-preco.test.tsx
import { render, fireEvent, screen } from '@testing-library/react'
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
import { camposDoPrecoEditado } from '@/Pages/Produto/_components/formulario/precos'

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

const gravado = { variacaoId: 55, compra: 10.1234, margem: 25, venda: 12.6543 }

describe('UC-PEDIT-16 · Editar o preço do produto simples', () => {
  it('mostra o preço gravado e, sem mexer, não manda campo de preço', () => {
    const { container } = render(<ProdutoEdit {...props} product={{ ...produto, precoSimples: gravado }} />)

    expect((screen.getByLabelText('Venda sem imposto') as HTMLInputElement).value).toBe('12,65')
    const envio = salvar(container)
    for (const k of ['single_variation_id', 'single_dpp', 'single_dpp_inc_tax', 'profit_percent', 'single_dsp', 'single_dsp_inc_tax']) {
      expect(envio).not.toHaveProperty(k)
    }
  })

  it('mexeu na venda: manda a variação e os cinco campos, com a margem refeita', () => {
    const { container } = render(<ProdutoEdit {...props} product={{ ...produto, precoSimples: { ...gravado, compra: 10 } }} />)

    const venda = screen.getByLabelText('Venda sem imposto')
    fireEvent.focus(venda)
    fireEvent.change(venda, { target: { value: '15' } })
    fireEvent.blur(venda)

    expect(salvar(container)).toMatchObject({
      single_variation_id: 55,
      single_dpp: '10',
      single_dpp_inc_tax: '10',
      profit_percent: '50',
      single_dsp: '15',
      single_dsp_inc_tax: '15',
    })
  })

  it('produto variável ou composto não tem preço nesta tela e não manda nada', () => {
    const { container } = render(<ProdutoEdit {...props} product={{ ...produto, type: 'variable', precoSimples: null }} />)

    expect(screen.queryByLabelText('Venda sem imposto')).toBeNull()
    expect(salvar(container)).not.toHaveProperty('single_dsp')
  })

  it('trocar só o imposto manda o preço de novo, com os "com imposto" refeitos', () => {
    const preco = { compra: 100, margem: 25, venda: 125 }
    expect(camposDoPrecoEditado({ ...preco, variacaoId: 55 }, preco, 18, false)).toEqual({})
    expect(camposDoPrecoEditado({ ...preco, variacaoId: 55 }, preco, 18, true)).toMatchObject({
      single_variation_id: 55,
      single_dpp_inc_tax: '118',
      single_dsp_inc_tax: '147,5',
    })
  })
})
