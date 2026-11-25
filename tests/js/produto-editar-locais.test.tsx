// UC-PEDIT-34 — o Editar mostra onde o produto está (item 4 do relatório de pendências, 2026-11-24).
//
// 1) "Banner teste webservice" (WR2) tem 234 no local WR2 Sistemas e nenhum endereço gravado; o bloco
//    "Locais de armazenagem" abria vazio, porque as linhas só vinham do endereço. Agora o local com
//    saldo aparece, sem gravar nada enquanto não ganha endereço nem vira o padrão.
// 2) O campo "Locais do negócio", que o Novo tem, faltava no Editar.
import { render, screen, fireEvent } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'

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
        post: () => {
          enviado.payload = transformar.current(data)
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
import { comLocaisDoEstoque, podeTirar } from '@/Pages/Produto/_components/formulario/armazenagem'

const listas = {
  categories: {},
  brands: {},
  units: { 5: 'Unidade (UN)' },
  taxes: {},
  taxAttributes: {},
  barcodeTypes: { C128: 'Code 128' },
  subCategories: {},
  businessLocations: { 1: 'WR2 Sistemas', 2: 'Depósito' },
  productTypes: { single: 'Simples' },
  warranties: {},
}
const semEndereco = { prateleira: false, fileira: false, posicao: false }
const soPrateleira = { prateleira: true, fileira: false, posicao: false }

const produto = {
  id: 9, name: 'Banner teste webservice', sku: 'BAN-WS', type: 'single', brandId: null, unitId: 5,
  subUnitIds: null, categoryId: null, subCategoryId: null, tax: null, taxType: 'exclusive',
  origemMercadoria: 0, barcodeType: 'C128', enableStock: true, alertQuantity: null,
  weight: null, productDescription: null, productLocations: [1], image: null,
  warrantyId: null, camposPersonalizados: {}, expiryPeriod: null, expiryPeriodType: null,
  localPadraoId: null,
}

function editar(endereco = semEndereco, saldoPorLocal: Record<string, number> = { 1: 234 }, extra: Record<string, unknown> = {}) {
  return render(
    <ProdutoEdit
      {...(listas as never)}
      product={{ ...produto, ...extra } as never}
      rackDetails={[] as never}
      enderecoNoEstoque={endereco}
      saldoPorLocal={saldoPorLocal}
      subUnits={{}}
      permissions={{ opening_stock: false, delete: false }}
    />,
  )
}

function salvar() {
  fireEvent.submit(document.querySelector('form') as HTMLFormElement)
  return enviado.payload as Record<string, unknown>
}

beforeEach(() => {
  enviado.payload = null
})

describe('UC-PEDIT-34 · Locais de armazenagem mostram o local com saldo e sem endereço', () => {
  it('o local com saldo aparece com o "Em estoque", sem poder sair', () => {
    editar()
    expect(screen.getByLabelText('Em estoque, WR2 Sistemas').textContent).toBe('234,00')
    const tirar = screen.getByRole('button', { name: 'Tirar este endereço do produto: WR2 Sistemas' }) as HTMLButtonElement
    expect(tirar.disabled).toBe(true)
  })

  it('salvar sem mexer não grava endereço nem padrão', () => {
    editar()
    const p = salvar()
    expect(p).not.toHaveProperty('product_racks')
    expect(p).not.toHaveProperty('product_racks_update')
    expect(p.local_padrao_id).toBe('')
  })

  it('dar endereço ao local cria a linha em product_racks', () => {
    editar(soPrateleira)
    fireEvent.change(screen.getByLabelText('Prateleira, WR2 Sistemas'), { target: { value: 'B2' } })
    expect(salvar().product_racks).toEqual({ 1: { rack: 'B2', row: '', position: '' } })
  })

  it('local sem saldo e sem endereço não entra; saldo negativo entra', () => {
    const e = comLocaisDoEstoque({ linhas: [], padrao: null }, { 1: 0, 2: -3 })
    expect(e.linhas.map((l) => l.local)).toEqual([2])
    expect(podeTirar(e.linhas[0], { 2: -3 })).toBe(false)
  })

  it('local que já tem endereço gravado não é repetido', () => {
    const e = comLocaisDoEstoque(
      { linhas: [{ local: 1, prateleira: 'A', fileira: '', posicao: '', salvo: true }], padrao: 1 },
      { 1: 234, 2: 4 },
    )
    expect(e.linhas.map((l) => [l.local, l.salvo, !!l.doEstoque])).toEqual([[1, true, false], [2, false, true]])
  })
})

describe('UC-PEDIT-34 · Locais do negócio no Editar', () => {
  it('mostra o campo com os locais gravados marcados', () => {
    editar()
    expect(screen.getByRole('group', { name: 'Locais do negócio' })).toBeTruthy()
    expect(screen.getByRole('checkbox', { name: 'WR2 Sistemas' }).getAttribute('data-state')).toBe('checked')
    expect(screen.getByRole('checkbox', { name: 'Depósito' }).getAttribute('data-state')).toBe('unchecked')
  })

  it('marcar outro local manda os dois em product_locations', () => {
    editar()
    fireEvent.click(screen.getByRole('checkbox', { name: 'Depósito' }))
    expect(salvar().product_locations).toEqual([1, 2])
  })

  it('sem mexer, devolve os locais gravados', () => {
    editar()
    expect(salvar().product_locations).toEqual([1])
  })
})
