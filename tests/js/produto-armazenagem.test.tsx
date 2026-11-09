// UC-PCAD-22 · UC-PEDIT-19 (CU-PROD-20) — bloco "Locais de armazenagem" do cadastro de produto.
//
// Contrato (SDD CU-PROD-20, decisões [M] 2026-10-22 + #10544): uma linha por local de estoque com o
// padrão e o endereço (só as partes que a empresa usa); o cadastro não grava quantidade; linha já
// gravada não sai pela tela; o envio usa os campos do Blade (`product_racks` cria, `product_racks_update`
// muda) e `local_padrao_id`. Só aparece com o estoque controlado.
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
import { armazenagemInicial, camposDaArmazenagem, semLinha } from '@/Pages/Produto/_components/formulario/armazenagem'

const listas = {
  categories: {},
  brands: {},
  units: { 5: 'Metro (M)' },
  taxes: {},
  taxAttributes: {},
  barcodeTypes: { C128: 'Code 128' },
  subCategories: {},
  businessLocations: { 1: 'Matriz', 2: 'Depósito' },
  productTypes: { single: 'Simples' },
  warranties: {},
}
const soPrateleira = { prateleira: true, fileira: false, posicao: false }

function novo(extra: Record<string, unknown> = {}) {
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
      enableRacks
      enderecoNoEstoque={soPrateleira}
      {...(extra as never)}
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

describe('armazenagem — estado e envio', () => {
  it('Novo sem nada gravado começa no local inicial, que é o padrão', () => {
    expect(armazenagemInicial([], { salvo: false, localInicial: 1 })).toEqual({
      linhas: [{ local: 1, prateleira: '', fileira: '', posicao: '', salvo: false }],
      padrao: 1,
    })
  })

  it('Editar lê o endereço gravado e mantém o padrão gravado, mesmo nulo', () => {
    const gravado = { 2: { location_id: 2, rack: 'A', row: null, position: '05' } }
    expect(armazenagemInicial(gravado, { salvo: true, padrao: null })).toEqual({
      linhas: [{ local: 2, prateleira: 'A', fileira: '', posicao: '05', salvo: true }],
      padrao: null,
    })
  })

  it('o envio separa linha nova (product_racks) de linha gravada (product_racks_update)', () => {
    const e = {
      linhas: [
        { local: 2, prateleira: 'A', fileira: '', posicao: '', salvo: true },
        { local: 1, prateleira: '', fileira: '', posicao: '9', salvo: false },
      ],
      padrao: 1,
    }
    expect(camposDaArmazenagem(e)).toEqual({
      product_racks: { 1: { rack: '', row: '', position: '9' } },
      product_racks_update: { 2: { rack: 'A', row: '', position: '' } },
      local_padrao_id: 1,
    })
    expect(camposDaArmazenagem({ linhas: [], padrao: null })).toEqual({ local_padrao_id: '' })
  })

  it('tirar a linha padrão passa o padrão para a primeira que sobra; linha gravada não sai', () => {
    const e = {
      linhas: [
        { local: 1, prateleira: '', fileira: '', posicao: '', salvo: false },
        { local: 2, prateleira: '', fileira: '', posicao: '', salvo: true },
      ],
      padrao: 1,
    }
    expect(semLinha(e, 0)).toEqual({ linhas: [e.linhas[1]], padrao: 2 })
    expect(semLinha(e, 1)).toBe(e)
  })
})

describe('UC-PCAD-22 · Novo produto', () => {
  it('mostra o bloco com só as partes do endereço que a empresa usa e envia o padrão', () => {
    novo()
    const bloco = screen.getByTestId('bloco-armazenagem')
    expect(bloco.textContent).toContain('Locais de armazenagem')
    expect(bloco.textContent).toContain('Prateleira')
    expect(bloco.textContent).not.toContain('Fileira')
    expect(bloco.textContent).not.toContain('Qtd')

    fireEvent.change(screen.getByLabelText('Prateleira, Matriz'), { target: { value: 'B3' } })
    const p = salvar()
    expect(p.product_racks).toEqual({ 1: { rack: 'B3', row: '', position: '' } })
    expect(p.local_padrao_id).toBe(1)
  })

  it('"+ Adicionar local de estoque" acrescenta o próximo local livre; sem local livre, o botão desliga', () => {
    novo()
    const botao = screen.getByRole('button', { name: '+ Adicionar local de estoque' })
    fireEvent.click(botao)
    expect(screen.getByLabelText('Prateleira, Depósito')).toBeTruthy()
    expect((botao as HTMLButtonElement).disabled).toBe(true)

    fireEvent.click(screen.getByRole('radio', { name: 'Local padrão: Depósito' }))
    const p = salvar()
    expect(Object.keys(p.product_racks as object)).toEqual(['1', '2'])
    expect(p.local_padrao_id).toBe(2)
  })

  it('sem controle de estoque o bloco some e o envio não leva locais nem padrão', () => {
    novo()
    fireEvent.click(document.getElementById('enable_stock') as HTMLElement)
    expect(screen.queryByTestId('bloco-armazenagem')).toBeNull()
    const p = salvar()
    expect(p).not.toHaveProperty('product_racks')
    expect(p).not.toHaveProperty('local_padrao_id')
  })
})

describe('UC-PEDIT-19 · Editar produto', () => {
  const produto = {
    id: 9, name: 'Lona 440g', sku: 'LON-440', type: 'single', brandId: null, unitId: 5,
    subUnitIds: null, categoryId: null, subCategoryId: null, tax: null, taxType: 'exclusive',
    origemMercadoria: 0, barcodeType: 'C128', enableStock: true, alertQuantity: null,
    weight: null, productDescription: null, productLocations: [1], image: null,
    warrantyId: null, camposPersonalizados: {}, expiryPeriod: null, expiryPeriodType: null,
    localPadraoId: 2,
  }

  function editar(extra: Record<string, unknown> = {}) {
    return render(
      <ProdutoEdit
        {...(listas as never)}
        product={{ ...produto, ...extra } as never}
        rackDetails={{ 2: { location_id: 2, rack: 'A', row: '', position: '' } } as never}
        enderecoNoEstoque={soPrateleira}
        subUnits={{}}
        permissions={{ opening_stock: false, delete: false }}
      />,
    )
  }

  it('lê o local gravado e o padrão; a linha gravada não tem botão de tirar e vai em product_racks_update', () => {
    editar()
    expect(screen.queryByRole('button', { name: /Remover local/ })).toBeNull()
    expect((screen.getByLabelText('Prateleira, Depósito') as HTMLInputElement).value).toBe('A')
    const p = salvar()
    expect(p.product_racks_update).toEqual({ 2: { rack: 'A', row: '', position: '' } })
    expect(p).not.toHaveProperty('product_racks')
    expect(p.local_padrao_id).toBe(2)
  })

  it('produto sem estoque controlado: sem bloco e sem os campos no envio (o padrão gravado fica)', () => {
    editar({ enableStock: false })
    expect(screen.queryByTestId('bloco-armazenagem')).toBeNull()
    expect(salvar()).not.toHaveProperty('local_padrao_id')
  })
})
