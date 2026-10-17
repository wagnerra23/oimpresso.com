// Preço do produto simples na tela Novo produto (precos.ts + CamposPreco.tsx + Create.tsx).
//
// Até 2026-10-16 o card "Preço & Imposto" do Novo produto React não tinha campo de preço, e o
// produto nascia sem custo nem venda (Create.casos.md §Pendência de CONTRATO). A conta tem de ser a
// do cadastro Blade (public/js/product.js), para o produto nascer com o mesmo preço pelos dois
// caminhos. Os números abaixo são os que o Blade grava, medidos rodando as funções dele.
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
import {
  aliquotaDoImposto,
  camposDePreco,
  margemDe,
  mudarCompra,
  mudarMargem,
  mudarVenda,
  precoInicial,
  somarPercentual,
} from '@/Pages/Produto/_components/formulario/precos'

describe('UC-PCAD-09 · precos.ts — a conta do Blade', () => {
  it('compra 100, lucro 25%, imposto 18%: os cinco campos', () => {
    const p = mudarCompra(precoInicial(25), 100)
    expect(camposDePreco(p, 18)).toEqual({
      single_dpp: '100',
      single_dpp_inc_tax: '118',
      profit_percent: '25',
      single_dsp: '125',
      single_dsp_inc_tax: '147,5',
    })
  })

  it('meio centavo arredonda para cima, como o Blade (84,75 + 18% = 100,01)', () => {
    // Em `number`, 84,75 + 18% dá 100,00499… e cairia para 100,00.
    expect(camposDePreco(mudarCompra(precoInicial(0), 84.75), 18).single_dpp_inc_tax).toBe('100,01')
    // 84,75 + 10% = 93,225 → 93,23 (em `number` sairia 93,22).
    expect(camposDePreco(mudarCompra(precoInicial(10), 84.75), 0).single_dsp).toBe('93,23')
  })

  it('a conta é exata como a do Blade, não em `number` (123,45 + 10% = 135,79)', () => {
    // 135,795 exato vira o double 135,79499… no Decimal.toNumber() do Blade, que grava 135,79.
    // Em `number`, 123,45 + 12,345 dá 135,79500000000002 e sairia 135,80.
    expect(camposDePreco(mudarCompra(precoInicial(10), 123.45), 0).single_dsp).toBe('135,79')
  })

  it('mudar a venda refaz o lucro; mudar o lucro refaz a venda', () => {
    const comVenda = mudarVenda(mudarCompra(precoInicial(25), 100), 150)
    expect(comVenda.margem).toBe(50)
    expect(mudarMargem(comVenda, 40).venda).toBe(140)
  })

  it('sem compra não há lucro (o Blade grava 0)', () => {
    expect(margemDe(0, 150)).toBe(0)
    expect(mudarVenda(precoInicial(25), 150).margem).toBe(0)
  })

  it('nunca manda número com ponto: o servidor leria como milhar', () => {
    const campos = camposDePreco(mudarCompra(precoInicial(12.5), 1234.56), 17)
    for (const valor of Object.values(campos)) expect(valor).not.toContain('.')
    expect(campos).toMatchObject({ single_dpp: '1234,56', single_dsp: '1388,88' })
  })

  it('alíquota vem de taxAttributes; sem imposto é 0', () => {
    const atributos = { 7: { 'data-rate': 18 }, 9: { 'data-rate': '12.5' } }
    expect(aliquotaDoImposto(7, atributos)).toBe(18)
    expect(aliquotaDoImposto('9', atributos)).toBe(12.5)
    expect(aliquotaDoImposto('', atributos)).toBe(0)
    expect(aliquotaDoImposto(3, atributos)).toBe(0)
    expect(somarPercentual(200, 12.5)).toBe(225)
  })
})

const props = {
  categories: { 1: 'Banner' },
  brands: {},
  units: { '': 'Selecionar', 4: 'Unidade (UN)' },
  taxes: { '': 'Nenhum', 7: 'ICMS 18%' },
  taxAttributes: { 7: { 'data-rate': 18 } },
  barcodeTypes: { C128: 'Code 128' },
  barcodeDefault: 'C128',
  defaultProfitPercent: 25,
  businessLocations: { 1: 'Loja' },
  duplicateProduct: null,
  subCategories: {},
  rackDetails: null,
  sellingPriceGroupCount: 0,
  productTypes: { single: 'Simples', variable: 'Variável', combo: 'Composto' },
  warranties: {},
  commonSettings: {},
  enableExpiry: false,
  enableLot: false,
  enableRacks: false,
}

describe('UC-PCAD-09 · Novo produto — o preço sai da tela', () => {
  it('digitar a compra preenche a venda pelo lucro padrão e o envio leva os cinco campos', () => {
    enviado.payload = null
    const { container } = render(<ProdutoCreate {...props} />)

    const compra = screen.getByLabelText('Compra sem imposto')
    fireEvent.focus(compra)
    fireEvent.change(compra, { target: { value: '100' } })
    fireEvent.blur(compra)

    expect((screen.getByLabelText('Venda sem imposto') as HTMLInputElement).value).toBe('125,00')
    expect((screen.getByLabelText('% de lucro') as HTMLInputElement).value).toBe('25,00')

    fireEvent.submit(container.querySelector('form#produto-create-form') as HTMLFormElement)
    expect(enviado.payload).toMatchObject({
      type: 'single',
      single_dpp: '100',
      single_dpp_inc_tax: '100',
      profit_percent: '25',
      single_dsp: '125',
      single_dsp_inc_tax: '125',
    })
  })
})
