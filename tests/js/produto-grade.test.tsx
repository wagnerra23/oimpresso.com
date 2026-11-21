// UC-PCAD-32 (Create.casos.md): grade do produto variável no Novo produto. Âncora: decisão do [F] em
// 2026-11-17 (SDD §10.6: modelo da empresa, uma variação, primeiro no Novo), protótipo do [W]
// (produto-blade-forms.jsx: formato do sub-SKU, tabela de valores, Enter abre a próxima linha) e
// paridade variable_product_form_part.blade.php / ProductUtil::createVariableProductVariations e
// generateSubSku. A conta de cada valor é a do preço simples (precos.ts, medida contra o Blade).
import { render, screen, fireEvent } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'
import { aoEscolherModelo, aplicarATodos, arquivosDaGrade, camposDaGrade, erroDaGrade, gradeInicial, gradeSemArquivos, previaSubSku, valorDoModelo } from '@/Pages/Produto/_components/formulario/grade'
import type { ModeloVariacao } from '@/Pages/Produto/_components/formulario/grade'
import { camposDePreco, mudarCompra, mudarMargem, precoInicial } from '@/Pages/Produto/_components/formulario/precos'

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
  it('a opção "SKU + valor da variação" diz o que grava: o SKU junto do valor, como CAMAzul', () => {
    // Decisão do [W] em 2026-11-21 (#11263): o texto antigo, "SKU + número da variação", não batia com o
    // que o servidor grava (ProductUtil::generateSubSku junta o SKU com o valor, sem número).
    render(<ProdutoCreate {...props} />)

    escolher('variacao_modelo', 'Cor')
    fireEvent.change(screen.getByLabelText('Valor 1'), { target: { value: 'Azul' } })
    expect(screen.queryByText('SKU + número da variação')).toBeNull()

    fireEvent.click(screen.getByLabelText('SKU + valor da variação (ex.: CAMAzul)'))
    expect((screen.getByLabelText('Sub-SKU 1') as HTMLInputElement).placeholder).toBe('CAMAzul')
  })

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
    // A prévia do gerado fica como dica no Sub-SKU, que pode ser digitado (UC-PCAD-33).
    expect((screen.getByLabelText('Sub-SKU 2') as HTMLInputElement).placeholder).toBe('CAM-2')

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

  // Checagem em produção (2026-11-18, empresa 1, 1280px): o título só existia como aria-label, o
  // cursor ficava na linha de cima depois do Enter e o Select do modelo avisava "uncontrolled to
  // controlled". O protótipo (produto-blade-forms.jsx) mostra "Variações" com "Adicionar valor" ao
  // lado e promete a grade inteira sem o mouse.
  it('mostra o título "Variações" com "Adicionar valor" ao lado', () => {
    render(<ProdutoCreate {...props} />)

    const titulo = screen.getByRole('heading', { name: 'Variações' })
    expect(titulo.parentElement?.textContent).toContain('Adicionar valor')
  })

  it('Enter leva o cursor ao Valor da linha seguinte, abrindo a linha quando é a última', () => {
    render(<ProdutoCreate {...props} />)
    escolher('variacao_modelo', 'Cor')

    fireEvent.keyDown(screen.getByLabelText('% de lucro 1'), { key: 'Enter' })
    expect(document.activeElement).toBe(screen.getByLabelText('Valor 2'))

    fireEvent.keyDown(screen.getByLabelText('Valor 1'), { key: 'Enter' })
    expect(screen.queryByLabelText('Valor 3')).toBeNull()
    expect(document.activeElement).toBe(screen.getByLabelText('Valor 2'))
  })

  it('escolher o modelo não troca o Select de não controlado para controlado', () => {
    const erros = vi.spyOn(console, 'warn').mockImplementation(() => {})
    render(<ProdutoCreate {...props} />)
    escolher('variacao_modelo', 'Cor')

    const avisos = erros.mock.calls.map((c) => String(c[0])).filter((m) => /uncontrolled to controlled/i.test(m))
    erros.mockRestore()
    expect(avisos).toEqual([])
  })

  it('empresa sem modelo de variação: avisa e leva ao cadastro de variações', () => {
    render(<ProdutoCreate {...props} modelosVariacao={[]} />)

    expect(screen.getByText('Cadastrar em Cadastros → Variações').getAttribute('href')).toBe('/variation-templates')
  })
})

// UC-PCAD-33 (Create.casos.md): paridade da grade com o Blade, pedida pelo [F] em 2026-11-19 antes de
// ligar a chave useV2ProdutoCadastro para todas as empresas. Âncora: product.js (`.variation_template`
// chama `getVariationTemplate`, que monta uma linha por valor; `.apply-all`), product_variation_row
// (`sub_sku` digitável, `variation_images_<i>_<k>[]`) e ProductUtil::createVariableProductVariations
// (`sub_sku` vazio → generateSubSku; fotos lidas por `variation_images_<i>_<k>`).
const TAMANHO: ModeloVariacao = { id: 5, nome: 'Tamanho', valores: [{ id: 11, nome: 'P' }, { id: 12, nome: 'M' }, { id: 13, nome: 'G' }] }

describe('UC-PCAD-33 · grade.ts — paridade com o Blade', () => {
  it('escolher o modelo traz os valores dele, com o preço da primeira linha', () => {
    const grade = { ...gradeInicial(25), valores: [{ nome: '', preco: mudarCompra(precoInicial(25), 10) }] }
    const g = aoEscolherModelo(grade, TAMANHO, 25)
    expect(g.modeloId).toBe(5)
    expect(g.valores.map((v) => v.nome)).toEqual(['P', 'M', 'G'])
    expect(g.valores.map((v) => v.preco)).toEqual([0, 1, 2].map(() => mudarCompra(precoInicial(25), 10)))
  })

  it('linhas já preenchidas ficam e o valor do modelo não entra duas vezes', () => {
    const grade = { ...gradeInicial(25), valores: [{ nome: ' m ', preco: precoInicial(25) }, { nome: 'GG', preco: precoInicial(25) }] }
    expect(aoEscolherModelo(grade, TAMANHO, 25).valores.map((v) => v.nome)).toEqual([' m ', 'GG', 'P', 'G'])
  })

  it('modelo sem valores mantém a grade como está', () => {
    const grade = gradeInicial(25)
    expect(aoEscolherModelo(grade, { id: 9, nome: 'Vazio', valores: [] }, 25)).toEqual({ ...grade, modeloId: 9 })
  })

  // Dupla prova (REGRA MESTRE de valor): aplicar a todos dá o mesmo preço que digitar o valor em cada
  // linha (mudarCompra/mudarMargem, a conta medida contra o Blade), e os números batem com a conta à mão.
  it('aplicar a compra da linha 2 a todas refaz a venda de cada linha pelo lucro dela', () => {
    const grade = {
      ...gradeInicial(25),
      valores: [
        { nome: 'P', preco: mudarCompra(precoInicial(25), 10) },
        { nome: 'M', preco: mudarCompra(precoInicial(40), 84.75) },
        { nome: 'G', preco: mudarCompra(precoInicial(0), 5) },
      ],
    }
    const g = aplicarATodos(grade, 1, 'compra')
    expect(g.valores.map((v) => v.preco)).toEqual([
      mudarCompra(grade.valores[0].preco, 84.75),
      mudarCompra(grade.valores[1].preco, 84.75),
      mudarCompra(grade.valores[2].preco, 84.75),
    ])
    // À mão: 84,75 + 25% = 105,9375 → 105,94; + 40% = 118,65; + 0% = 84,75.
    // O estado guarda a conta exata; a tela e o envio arredondam como o Blade.
    expect(camposDaGrade(g, TAMANHO, 0).product_variation[0].variations.map((v) => v.default_sell_price)).toEqual(['105,94', '118,65', '84,75'])
  })

  it('aplicar o lucro da linha 1 a todas refaz a venda de cada compra', () => {
    const grade = {
      ...gradeInicial(25),
      valores: [
        { nome: 'P', preco: mudarMargem(mudarCompra(precoInicial(25), 10), 50) },
        { nome: 'M', preco: mudarCompra(precoInicial(25), 20) },
      ],
    }
    const g = aplicarATodos(grade, 0, 'margem')
    expect(g.valores.map((v) => [v.preco.margem, v.preco.venda])).toEqual([[50, 15], [50, 30]])
  })

  it('o sub-SKU digitado vai no envio; em branco, vai vazio e o servidor gera', () => {
    const grade = {
      modeloId: 5,
      formato: 'with_out_variation' as const,
      valores: [
        { nome: 'P', preco: precoInicial(25), subSku: ' 7891234567895 ' },
        { nome: 'M', preco: precoInicial(25) },
      ],
    }
    expect(camposDaGrade(grade, TAMANHO, 0).product_variation[0].variations.map((v) => v.sub_sku)).toEqual(['7891234567895', ''])
  })

  it('a foto vai no nome que o servidor lê, pela posição do valor no envio', () => {
    const foto = new File(['x'], 'p.png', { type: 'image/png' })
    const grade = {
      modeloId: 5,
      formato: 'with_out_variation' as const,
      valores: [
        { nome: '', preco: precoInicial(25), imagens: [new File(['y'], 'perdida.png', { type: 'image/png' })] },
        { nome: 'P', preco: precoInicial(25) },
        { nome: 'M', preco: precoInicial(25), imagens: [foto] },
      ],
    }
    // A linha sem nome não vai, então "M" é o valor 1 do envio, não o 2.
    expect(arquivosDaGrade(grade)).toEqual({ variation_images_0_1: [foto] })
    expect(camposDaGrade(grade, TAMANHO, 0)).toHaveProperty('variation_images_0_1', [foto])
    expect(gradeSemArquivos(grade).valores.every((v) => !('imagens' in v))).toBe(true)
  })
})

describe('UC-PCAD-33 · Novo produto — a grade na tela', () => {
  it('escolher o modelo monta as linhas; tirar uma linha tira o valor do envio', () => {
    const { container } = render(<ProdutoCreate {...props} modelosVariacao={[COR, TAMANHO]} />)

    escolher('variacao_modelo', 'Tamanho')
    expect([1, 2, 3].map((n) => (screen.getByLabelText(`Valor ${n}`) as HTMLInputElement).value)).toEqual(['P', 'M', 'G'])

    fireEvent.click(screen.getByLabelText('Remover valor 2'))
    fireEvent.change(screen.getByLabelText('Sub-SKU 1'), { target: { value: '7891234567895' } })
    const envio = enviar(container) as Record<string, unknown>
    const variacoes = (envio.product_variation as { variations: { value: string; variation_value_id: number; sub_sku: string }[] }[])[0].variations
    expect(variacoes.map((v) => [v.value, v.variation_value_id, v.sub_sku])).toEqual([['P', 11, '7891234567895'], ['G', 13, '']])
  })

  it('o botão de aplicar a compra copia o valor para todas as linhas', () => {
    render(<ProdutoCreate {...props} modelosVariacao={[TAMANHO]} />)
    escolher('variacao_modelo', 'Tamanho')

    digitar('Compra sem imposto 2', '40')
    fireEvent.click(screen.getByLabelText('Aplicar a compra da linha 2 a todas'))

    expect([1, 2, 3].map((n) => (screen.getByLabelText(`Compra sem imposto ${n}`) as HTMLInputElement).value)).toEqual(['40,00', '40,00', '40,00'])
    expect((screen.getByLabelText('Venda sem imposto 3') as HTMLInputElement).value).toBe('50,00')
  })

  it('foto acima de 5 MB é recusada com aviso; a de tamanho certo fica no valor', () => {
    const { container } = render(<ProdutoCreate {...props} modelosVariacao={[TAMANHO]} />)
    escolher('variacao_modelo', 'Tamanho')

    const grande = new File(['x'], 'grande.png', { type: 'image/png' })
    Object.defineProperty(grande, 'size', { value: 6_000_000 })
    const boa = new File(['x'], 'boa.png', { type: 'image/png' })
    fireEvent.change(screen.getByLabelText('Fotos do valor 1'), { target: { files: [grande, boa] } })

    expect(screen.getByText('Cada foto pode ter até 5 MB.')).toBeTruthy()
    expect(screen.getByText('1 foto')).toBeTruthy()
    const envio = enviar(container) as Record<string, unknown>
    expect(envio.variation_images_0_0).toEqual([boa])
  })
})
