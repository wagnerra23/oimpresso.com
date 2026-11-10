// UC-PCAD-23 · UC-PEDIT-20 (CU-PROD-21) — campo "Política de preço" do cadastro de produto.
//
// Contrato (SDD CU-PROD-21, decisão [M] 2026-10-22 + resposta do [W] na US-PROD-030): três opções com
// os nomes de Fluxos e da Fabricação; produto novo começa com Preço fixo; "Margem alvo (%)" só aparece
// com a política Margem alvo. O Novo sempre envia a política; o Editar só quando ela muda.
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

import ProdutoCreate from '@/Pages/Produto/Create'
import ProdutoEdit from '@/Pages/Produto/Edit'
import { camposDaPolitica, politicaInicial } from '@/Pages/Produto/_components/formulario/politicaPreco'

const listas = {
  categories: {},
  brands: {},
  units: { 5: 'Metro (M)' },
  taxes: {},
  taxAttributes: {},
  barcodeTypes: { C128: 'Code 128' },
  subCategories: {},
  businessLocations: { 1: 'Matriz' },
  productTypes: { single: 'Simples' },
  warranties: {},
}

function salvar() {
  fireEvent.submit(document.querySelector('form') as HTMLFormElement)
  return enviado.payload as Record<string, unknown>
}

function escolher(rotulo: string) {
  fireEvent.keyDown(document.querySelector('#politica_preco') as HTMLElement, { key: 'Enter' })
  fireEvent.click(screen.getByRole('option', { name: rotulo }))
}

beforeEach(() => {
  enviado.payload = null
})

describe('política de preço — estado e envio', () => {
  it('produto novo começa com Preço fixo; política desconhecida também vira Preço fixo', () => {
    expect(politicaInicial()).toEqual({ politica: 'fixo', margemAlvo: '' })
    expect(politicaInicial({ politica: 'outra', margemAlvoPct: 30 })).toEqual({ politica: 'fixo', margemAlvo: '' })
  })

  it('a margem gravada aparece com vírgula e sai com ponto', () => {
    const e = politicaInicial({ politica: 'margem', margemAlvoPct: 45.5 })
    expect(e).toEqual({ politica: 'margem', margemAlvo: '45,5' })
    expect(camposDaPolitica(e)).toEqual({ politica_preco: 'margem', margem_alvo_pct: '45.5' })
  })

  it('fora de Margem alvo a margem sai vazia; no Editar, sem mudança, nada sai', () => {
    expect(camposDaPolitica({ politica: 'acompanha', margemAlvo: '30' })).toEqual({
      politica_preco: 'acompanha',
      margem_alvo_pct: '',
    })
    const gravada = politicaInicial({ politica: 'margem', margemAlvoPct: 45 })
    expect(camposDaPolitica({ ...gravada }, gravada)).toEqual({})
    expect(camposDaPolitica({ ...gravada, margemAlvo: '45.0' }, gravada)).toEqual({
      politica_preco: 'margem',
      margem_alvo_pct: '45.0',
    })
  })
})

describe('UC-PCAD-23 · Novo produto', () => {
  function novo() {
    return render(
      <ProdutoCreate
        {...(listas as never)}
        rackDetails={null}
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
  }

  it('mostra Preço fixo sem o campo de margem e envia a política', () => {
    novo()
    expect(screen.getByTestId('campo-politica-preco').textContent).toContain('Preço fixo — só a margem muda')
    expect(document.querySelector('#margem_alvo_pct')).toBeNull()
    const p = salvar()
    expect(p.politica_preco).toBe('fixo')
    expect(p.margem_alvo_pct).toBe('')
  })

  it('com Margem alvo aparece "Margem alvo (%)" e o envio leva a margem com ponto', () => {
    novo()
    escolher('Margem alvo — recalcula pelo custo')
    fireEvent.change(document.querySelector('#margem_alvo_pct') as HTMLElement, { target: { value: '38,5' } })
    const p = salvar()
    expect(p.politica_preco).toBe('margem')
    expect(p.margem_alvo_pct).toBe('38.5')
  })
})

describe('UC-PEDIT-20 · Editar produto', () => {
  const produto = {
    id: 9, name: 'Lona 440g', sku: 'LON-440', type: 'single', brandId: null, unitId: 5,
    subUnitIds: null, categoryId: null, subCategoryId: null, tax: null, taxType: 'exclusive',
    origemMercadoria: 0, barcodeType: 'C128', enableStock: false, alertQuantity: null,
    weight: null, productDescription: null, productLocations: [1], image: null,
    warrantyId: null, camposPersonalizados: {}, expiryPeriod: null, expiryPeriodType: null,
    politicaPreco: 'margem', margemAlvoPct: 45,
  }

  function editar(extra: Record<string, unknown> = {}) {
    return render(
      <ProdutoEdit
        {...(listas as never)}
        product={{ ...produto, ...extra } as never}
        rackDetails={null}
        subUnits={{}}
        permissions={{ opening_stock: false, delete: false }}
      />,
    )
  }

  it('lê a política e a margem gravadas; salvar sem mexer não envia nenhuma das duas', () => {
    editar()
    expect((document.querySelector('#margem_alvo_pct') as HTMLInputElement).value).toBe('45')
    const p = salvar()
    expect(p).not.toHaveProperty('politica_preco')
    expect(p).not.toHaveProperty('margem_alvo_pct')
  })

  it('trocar para Acompanha o custo esconde a margem e envia a política com a margem vazia', () => {
    editar()
    escolher('Acompanha o custo — mantém o markup')
    expect(document.querySelector('#margem_alvo_pct')).toBeNull()
    const p = salvar()
    expect(p.politica_preco).toBe('acompanha')
    expect(p.margem_alvo_pct).toBe('')
  })

  it('produto antigo sem política gravada abre em Preço fixo', () => {
    editar({ politicaPreco: undefined, margemAlvoPct: undefined })
    expect(screen.getByTestId('campo-politica-preco').textContent).toContain('Preço fixo — só a margem muda')
    expect(salvar()).not.toHaveProperty('politica_preco')
  })
})
