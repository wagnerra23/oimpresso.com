// Grade do produto variável na tela Editar (Edit.tsx + BlocoGradeEditar + gradeEditar.ts) —
// UC-PEDIT-32 · Edit.casos.md.
//
// A tela mostra uma linha por variação gravada e só manda a linha que a pessoa mexeu: o servidor
// preserva a que não vem, e devolver o gravado arredondado reescreveria custo/venda de 4 casas.
// Comando: npx vitest run tests/js/produto-editar-grade.test.tsx
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
import { camposDaGradeEditada } from '@/Pages/Produto/_components/formulario/gradeEditar'

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

const grade = {
  modelo: 'Diâmetro',
  linhas: [
    { variacaoId: 71, valor: '6 mm', subSku: 'BRO-1', compra: 10, margem: 25, venda: 12.5 },
    { variacaoId: 72, valor: '8 mm', subSku: 'BRO-2', compra: 20.1234, margem: 50, venda: 30.1851 },
  ],
}

describe('UC-PEDIT-32 · Editar a grade do produto variável', () => {
  it('mostra uma linha por variação e, sem mexer, não manda a grade', () => {
    const { container } = render(<ProdutoEdit {...props} product={{ ...produto, type: 'variable', gradeVariavel: grade }} />)

    expect(screen.getByText('6 mm')).toBeTruthy()
    expect(screen.getByText('8 mm')).toBeTruthy()
    expect((screen.getByLabelText('Venda sem imposto 2') as HTMLInputElement).value).toBe('30,19')
    expect(salvar(container)).not.toHaveProperty('variacoes_editadas')
  })

  it('mexeu na venda da linha 1: manda só ela, com a margem refeita, e a linha 2 fica de fora', () => {
    const { container } = render(<ProdutoEdit {...props} product={{ ...produto, type: 'variable', gradeVariavel: grade }} />)

    const venda = screen.getByLabelText('Venda sem imposto 1')
    fireEvent.focus(venda)
    fireEvent.change(venda, { target: { value: '15' } })
    fireEvent.blur(venda)

    const envio = salvar(container)
    expect(envio.variacoes_editadas).toEqual({
      71: { dpp: '10', dpp_inc_tax: '10', profit_percent: '50', dsp: '15', dsp_inc_tax: '15' },
    })
  })

  it('mexeu só no sub-SKU: manda o sub-SKU sem preço', () => {
    const { container } = render(<ProdutoEdit {...props} product={{ ...produto, type: 'variable', gradeVariavel: grade }} />)

    fireEvent.change(screen.getByLabelText('Sub-SKU 2'), { target: { value: 'FORN-8' } })

    expect(salvar(container).variacoes_editadas).toEqual({ 72: { sub_sku: 'FORN-8' } })
  })

  it('produto simples não tem grade', () => {
    render(<ProdutoEdit {...props} product={{ ...produto, gradeVariavel: null }} />)
    expect(screen.queryByLabelText('Valores da variação')).toBeNull()
  })

  it('trocar só o imposto manda todas as linhas, com os "com imposto" refeitos, e nunca milhar com ponto', () => {
    const atual = grade.linhas.map((l) => ({ variacaoId: l.variacaoId, subSku: l.subSku, preco: { compra: l.compra, margem: l.margem, venda: l.venda } }))
    expect(camposDaGradeEditada(grade, atual, 18, false)).toEqual({})
    expect(camposDaGradeEditada(grade, atual, 18, true)).toEqual({
      variacoes_editadas: {
        71: { dpp: '10', dpp_inc_tax: '11,8', profit_percent: '25', dsp: '12,5', dsp_inc_tax: '14,75' },
        72: { dpp: '20,12', dpp_inc_tax: '23,75', profit_percent: '50', dsp: '30,19', dsp_inc_tax: '35,62' },
      },
    })
    const caro = [{ ...atual[0], preco: { compra: 1000, margem: 23.456, venda: 1234.56 } }]
    expect(camposDaGradeEditada(grade, caro, 0, false).variacoes_editadas?.[71]).toMatchObject({ dsp: '1234,56', profit_percent: '23,46' })
  })
})
