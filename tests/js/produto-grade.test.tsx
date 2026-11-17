// UC-PCAD-32 (Create.casos.md): grade do produto variável no Novo produto. Âncora: decisão do [F] em
// 2026-11-17 (SDD §10.6: modelo da empresa, uma variação, primeiro no Novo), protótipo do [W]
// (produto-blade-forms.jsx: formato do sub-SKU, tabela de valores, Enter abre a próxima linha) e
// paridade variable_product_form_part.blade.php / ProductUtil::createVariableProductVariations e
// generateSubSku. A conta de cada valor é a do preço simples (precos.ts, medida contra o Blade).
import { render, screen, fireEvent } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'
import { camposDaGrade, erroDaGrade, gradeInicial, previaSubSku, valorDoModelo } from '@/Pages/Produto/_components/formulario/grade'
import type { ModeloVariacao } from '@/Pages/Produto/_components/formulario/grade'
import { camposDePreco, mudarCompra, precoInicial } from '@/Pages/Produto/_components/formulario/precos'

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

const COR: ModeloVariacao = { id: 3, nome: 'Cor', valores: [{ id: 7, nome: 'Azul' }, { id: 8, nome: 'Verde' }] }

describe('UC-PCAD-32 · grade.ts — a conta e o envio', () => {
  it('cada valor manda os cinco preços pela conta do simples, com 18% de imposto', () => {
    const grade = {
      modeloId: 3,
      formato: 'with_out_variation' as const,
      valores: [
        { nome: 'Azul', preco: mudarCompra(precoInicial(25), 10) },
        // O caso que separa a conta exata da de ponto flutuante: 84,75 + 18% = 100,01 no Blade.
        { nome: 'Amarelo', preco: mudarCompra(precoInicial(0), 84.75) },
        { nome: '  ', preco: precoInicial(25) },
      ],
    }
    expect(camposDaGrade(grade, COR, 18)).toEqual({
      sku_type: 'with_out_variation',
      product_variation: [
        {
          variation_template_id: 3,
          variations: [
            { value: 'Azul', variation_value_id: 7, sub_sku: '', default_purchase_price: '10', dpp_inc_tax: '11,8', profit_percent: '25', default_sell_price: '12,5', sell_price_inc_tax: '14,75' },
            { value: 'Amarelo', variation_value_id: '', sub_sku: '', default_purchase_price: '84,75', dpp_inc_tax: '100,01', profit_percent: '0', default_sell_price: '84,75', sell_price_inc_tax: '100,01' },
          ],
        },
      ],
    })
  })

  // Dupla prova (REGRA MESTRE de valor): a grade dá o mesmo preço que o produto simples, cuja conta foi
  // medida contra o Blade (precos.ts: 2.725 valores, 0 divergências). Trocar a conta exata por
  // `compra + compra · alíquota / 100` em ponto flutuante derruba o caso 15,50 com 17% (medido em 2026-11-17).
  it.each([[4.25, 18], [84.75, 18], [27.75, 18], [9.5, 7], [15.5, 17], [1234.56, 12], [0, 18]])(
    'compra %s com %s%%: os cinco preços iguais aos do produto simples',
    (compra, aliquota) => {
      const preco = mudarCompra(precoInicial(25), compra)
      const linha = camposDaGrade({ modeloId: 3, formato: 'with_out_variation', valores: [{ nome: 'Azul', preco }] }, COR, aliquota)
        .product_variation[0].variations[0]
      const simples = camposDePreco(preco, aliquota)
      expect([linha.default_purchase_price, linha.dpp_inc_tax, linha.profit_percent, linha.default_sell_price, linha.sell_price_inc_tax])
        .toEqual([simples.single_dpp, simples.single_dpp_inc_tax, simples.profit_percent, simples.single_dsp, simples.single_dsp_inc_tax])
    },
  )

  it('casa o valor digitado com o do modelo sem diferença de maiúsculas', () => {
    expect(valorDoModelo(' azul ', COR)).toBe(7)
    expect(valorDoModelo('Roxo', COR)).toBeNull()
  })

  it('a prévia do sub-SKU segue o generateSubSku', () => {
    expect(previaSubSku('CAM', 'C128', 'with_out_variation', 'Azul', 1)).toBe('CAM-1')
    expect(previaSubSku('CAM', 'EAN13', 'with_out_variation', 'Azul', 2)).toBe('CAM2')
    expect(previaSubSku('CAM', 'C128', 'with_variation', 'Azul claro!', 1)).toBe('CAMAzulclaro')
    expect(previaSubSku('', 'C128', 'with_out_variation', 'Azul', 1)).toBe('')
  })

  it('sem modelo ou sem valor a grade não vai', () => {
    expect(erroDaGrade(gradeInicial(25))).toBe('Escolha o modelo da variação.')
    expect(erroDaGrade({ ...gradeInicial(25), modeloId: 3 })).toBe('Adicione ao menos um valor da variação.')
  })
})

const props = {
  categories: { 1: 'Banner' },
  brands: {},
  units: { '': 'Selecionar', 4: 'Unidade (UN)' },
  taxes: { '': 'Nenhum', 9: 'ICMS 18%' },
  taxAttributes: { 9: { 'data-rate': 18 } },
  barcodeTypes: { C128: 'Code 128' },
  barcodeDefault: 'C128',
  defaultProfitPercent: 25,
  businessLocations: { 1: 'Loja' },
  subCategories: {},
  rackDetails: null,
  sellingPriceGroupCount: 0,
  productTypes: { single: 'Simples', variable: 'Variável', combo: 'Composto' },
  warranties: {},
  commonSettings: {},
  enableExpiry: false,
  enableLot: false,
  enableRacks: false,
  modelosVariacao: [COR],
  duplicateProduct: {
    id: 9, name: 'Camiseta', brand_id: null, unit_id: 4, category_id: 1, sub_category_id: null, tax: 9,
    tax_type: 'exclusive' as const, barcode_type: 'C128', sku: 'CAM', alert_quantity: null, weight: null,
    product_description: null, type: 'variable' as const,
  },
}

function escolher(id: string, rotulo: string) {
  fireEvent.keyDown(document.getElementById(id) as HTMLElement, { key: 'Enter' })
  fireEvent.click(screen.getByRole('option', { name: rotulo }))
}

function digitar(rotulo: string, valor: string) {
  const campo = screen.getByLabelText(rotulo)
  fireEvent.focus(campo)
  fireEvent.change(campo, { target: { value: valor } })
  fireEvent.blur(campo)
}

function enviar(container: HTMLElement) {
  enviado.payload = null
  fireEvent.submit(container.querySelector('form#produto-create-form') as HTMLFormElement)
  return enviado.payload
}

describe('UC-PCAD-32 · Novo produto — a grade na tela', () => {
  it('escolhe o modelo, preenche os valores com Enter e envia a grade com os preços', () => {
    const { container } = render(<ProdutoCreate {...props} />)

    escolher('variacao_modelo', 'Cor')
    fireEvent.change(screen.getByLabelText('Valor 1'), { target: { value: 'azul' } })
    digitar('Compra sem imposto 1', '10')
    fireEvent.keyDown(screen.getByLabelText('Compra sem imposto 1'), { key: 'Enter' })
    fireEvent.change(screen.getByLabelText('Valor 2'), { target: { value: 'Amarelo' } })
    digitar('Compra sem imposto 2', '20')

    // Venda pelo lucro padrão (25%) e os "com imposto" pela alíquota do imposto escolhido (18%).
    expect((screen.getByLabelText('Venda sem imposto 2') as HTMLInputElement).value).toBe('25,00')
    expect(screen.getByRole('group', { name: 'Grade da variação' }).textContent).toContain('CAM-2')

    const envio = enviar(container) as Record<string, unknown>
    expect(envio).toMatchObject({ type: 'variable', sku_type: 'with_out_variation' })
    expect(envio.product_variation).toEqual([
      {
        variation_template_id: 3,
        variations: [
          { value: 'azul', variation_value_id: 7, sub_sku: '', default_purchase_price: '10', dpp_inc_tax: '11,8', profit_percent: '25', default_sell_price: '12,5', sell_price_inc_tax: '14,75' },
          { value: 'Amarelo', variation_value_id: '', sub_sku: '', default_purchase_price: '20', dpp_inc_tax: '23,6', profit_percent: '25', default_sell_price: '25', sell_price_inc_tax: '29,5' },
        ],
      },
    ])
    expect(envio).not.toHaveProperty('single_dpp')
  })

  it('sem modelo avisa e não envia', () => {
    const { container } = render(<ProdutoCreate {...props} />)

    expect(enviar(container)).toBeNull()
    expect(screen.getByText('Escolha o modelo da variação.')).toBeTruthy()
  })

  it('empresa sem modelo de variação: avisa e leva ao cadastro de variações', () => {
    render(<ProdutoCreate {...props} modelosVariacao={[]} />)

    expect(screen.getByText('Cadastrar em Cadastros → Variações').getAttribute('href')).toBe('/variation-templates')
  })
})
