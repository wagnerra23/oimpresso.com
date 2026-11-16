// UC-PCAD-25..27 (Create.casos.md) · UC-PEDIT-25..27 (Edit.casos.md): subcategoria, sub-unidades e
// IMEI / nº de série no Novo e no Editar React. Âncora: protótipo do [W] (produto-blade-forms.jsx),
// pedido do [F] em 2026-11-16 e paridade create.blade.php / edit.blade.php (campos escondidos quando a
// empresa não usa subcategoria ou sub-unidades; as sub-unidades vêm do cadastro de unidades).
import { useState } from 'react'
import { render, screen, fireEvent } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'

const enviado: { payload: Record<string, unknown> | null } = { payload: null }

vi.mock('@inertiajs/react', async () => {
  const React = await import('react')
  return {
    Head: () => null,
    Deferred: ({ children }: { children: React.ReactNode }) => children,
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
import { BlocoDados } from '@/Pages/Produto/_components/formulario/BlocoDados'
import { BlocoConfiguracoes } from '@/Pages/Produto/_components/formulario/BlocoConfiguracoes'
import type { CamposProduto, OpcoesProduto } from '@/Pages/Produto/_components/formulario/tipos'

const subcategorias = { 1: { 11: 'Lona', 12: 'Adesivo' }, 2: { 21: 'Parafuso' } }
const subUnidades = {
  4: [
    { id: 4, nome: 'Unidade', multiplicador: 1 },
    { id: 5, nome: 'Caixa', multiplicador: 12 },
  ],
  6: [{ id: 6, nome: 'Metro', multiplicador: 1 }],
}

const opcoesBase: OpcoesProduto = {
  units: { 4: 'Unidade (UN)', 6: 'Metro (M)' },
  categories: { 1: 'Banner', 2: 'Fixação' },
  brands: {},
  taxes: {},
  barcodeTypes: { C128: 'Code 128' },
  warranties: {},
  productTypes: { single: 'Simples' },
}

let estado: CamposProduto
function Dados({ inicial, opcoes }: { inicial: Partial<CamposProduto>; opcoes: OpcoesProduto }) {
  const [data, setData] = useState({ name: '', sku: '', barcode_type: 'C128', ...inicial } as unknown as CamposProduto)
  estado = data
  return (
    <BlocoDados
      modo="novo"
      data={data}
      definir={(k, v) => setData((d) => ({ ...d, [k]: v }))}
      errors={{}}
      opcoes={opcoes}
    />
  )
}

function escolher(id: string, rotulo: string) {
  fireEvent.keyDown(document.getElementById(id) as HTMLElement, { key: 'Enter' })
  fireEvent.click(screen.getByRole('option', { name: rotulo }))
}

describe('UC-PCAD-25 · subcategoria', () => {
  it('sem subcategorias na empresa, o campo não aparece', () => {
    render(<Dados inicial={{ category_id: 1 }} opcoes={opcoesBase} />)
    expect(document.getElementById('sub_category_id')).toBeNull()
  })

  it('lista as subcategorias da categoria escolhida e grava a escolhida', () => {
    render(<Dados inicial={{ category_id: 1, sub_category_id: '' }} opcoes={{ ...opcoesBase, subcategoriasPorCategoria: subcategorias }} />)

    escolher('sub_category_id', 'Adesivo')
    expect(estado.sub_category_id).toBe(12)
    expect(screen.queryByRole('option', { name: 'Parafuso' })).toBeNull()
  })

  it('trocar a categoria limpa a subcategoria', () => {
    render(<Dados inicial={{ category_id: 1, sub_category_id: 11 }} opcoes={{ ...opcoesBase, subcategoriasPorCategoria: subcategorias }} />)

    escolher('category_id', 'Fixação')
    expect(estado.category_id).toBe(2)
    expect(estado.sub_category_id).toBe('')
  })
})

describe('UC-PCAD-26 · sub-unidades', () => {
  it('sem sub-unidades na empresa, o campo não aparece', () => {
    render(<Dados inicial={{ unit_id: 4 }} opcoes={opcoesBase} />)
    expect(screen.queryByText('Sub-unidades relacionadas')).toBeNull()
  })

  it('mostra as unidades da unidade escolhida, com o multiplicador do cadastro, e marca as escolhidas', () => {
    render(<Dados inicial={{ unit_id: 4, sub_unit_ids: [] }} opcoes={{ ...opcoesBase, subUnidadesPorUnidade: subUnidades }} />)

    expect(screen.getByText('(1 = 12 Unidade)')).toBeTruthy()
    fireEvent.click(screen.getByRole('checkbox', { name: /Caixa/ }))
    expect(estado.sub_unit_ids).toEqual([5])
    expect(screen.queryByRole('checkbox', { name: /Metro/ })).toBeNull()
  })

  it('trocar a unidade desmarca as sub-unidades da anterior', () => {
    render(<Dados inicial={{ unit_id: 4, sub_unit_ids: [4, 5] }} opcoes={{ ...opcoesBase, subUnidadesPorUnidade: subUnidades }} />)

    escolher('unit_id', 'Metro (M)')
    expect(estado.unit_id).toBe(6)
    expect(estado.sub_unit_ids).toEqual([])
  })
})

describe('UC-PCAD-27 · IMEI / nº de série', () => {
  it('a caixa liga e desliga o IMEI', () => {
    const definir = vi.fn()
    render(<BlocoConfiguracoes modo="novo" data={{ enable_sr_no: 0 } as unknown as CamposProduto} definir={definir} />)

    fireEvent.click(screen.getByRole('checkbox', { name: 'Habilitar IMEI / nº de série' }))
    expect(definir).toHaveBeenCalledWith('enable_sr_no', 1)
  })
})

const produto = {
  id: 9, name: 'Parafuso', sku: 'PAR-01', type: 'single' as const, brandId: null, unitId: 4,
  subUnitIds: ['4', '5'] as Array<number | string>, categoryId: 1, subCategoryId: 11, tax: null,
  taxType: 'exclusive' as const, origemMercadoria: 0, barcodeType: 'C128', enableStock: true,
  alertQuantity: null, weight: null, productDescription: null, productLocations: [3], image: null,
  warrantyId: null, camposPersonalizados: {} as Record<number, string | null>,
  expiryPeriod: null as number | null, expiryPeriodType: null as string | null, enableSrNo: true,
}

const props = {
  categories: { 1: 'Banner' }, brands: {}, units: { 4: 'Unidade (UN)' }, subUnits: {},
  taxes: { '': 'Nenhum' }, taxAttributes: {}, barcodeTypes: { C128: 'Code 128' }, subCategories: {},
  businessLocations: { 3: 'Loja' }, rackDetails: null, productTypes: { single: 'Simples' }, warranties: {},
  permissions: { opening_stock: true, delete: false },
}

describe('UC-PEDIT-26 · UC-PEDIT-27 · Editar devolve sub-unidades e IMEI gravados', () => {
  it('salvar sem mexer manda as sub-unidades e o IMEI gravados (antes iam em branco e o servidor apagava)', () => {
    const { container } = render(<ProdutoEdit {...props} product={produto} subUnidadesPorUnidade={subUnidades} />)

    expect((screen.getByRole('checkbox', { name: /Caixa/ }) as HTMLButtonElement).getAttribute('data-state')).toBe('checked')
    enviado.payload = null
    fireEvent.submit(container.querySelector('form#produto-edit-form') as HTMLFormElement)
    const envio = enviado.payload as unknown as Record<string, unknown>
    expect(envio.sub_unit_ids).toEqual([4, 5])
    expect(envio.enable_sr_no).toBe(1)
    expect(envio.sub_category_id).toBe(11)
  })
})
