// Formulário de produto (Novo e Editar) com as listas que o servidor manda de verdade.
//
// O servidor acrescenta a chave '' às listas de unidades ("Selecionar") e de impostos ("nenhum")
// para os selects do Blade. O Select do Radix não aceita item com valor vazio: em 2026-10-16 a
// tela Novo produto aberta pela lista /products/unificado ficava EM BRANCO em produção, com
// "A <Select.Item /> must have a value prop that is not an empty string" no console.
// Este teste usa as listas com a chave vazia e prova que as duas telas renderizam.
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
import { opcoesDe } from '@/Pages/Produto/_components/formulario/tipos'

// As listas como o ProductController@create/@edit as manda (forDropdown com "show none").
const listasDoServidor = {
  categories: { 1: 'Banner' },
  brands: { 3: 'Marca A' },
  units: { '': 'Selecionar', 4: 'Unidade (UN)', 5: 'Metro (M)' },
  taxes: { 1: 'ICMS 18%', 2: 'ISS 5%', '': 'nenhum' },
  taxAttributes: {},
  barcodeTypes: { C128: 'Code 128', EAN13: 'EAN-13' },
  subCategories: {},
  businessLocations: { 1: 'Matriz' },
  rackDetails: null,
  productTypes: { single: 'Simples', variable: 'Variável', combo: 'Combo' },
  warranties: { 7: '90 dias' },
}

const secoes = ['Identificação', 'Preço & Imposto', 'Estoque', 'Mais opções']

describe('opcoesDe', () => {
  it('tira a chave vazia e mantém as outras na ordem', () => {
    expect(opcoesDe({ '': 'Selecionar', 4: 'Unidade (UN)', 5: 'Metro (M)' })).toEqual([
      ['4', 'Unidade (UN)'],
      ['5', 'Metro (M)'],
    ])
    expect(opcoesDe({ C128: 'Code 128' })).toEqual([['C128', 'Code 128']])
  })
})

describe('Novo produto com as listas do servidor', () => {
  it('renderiza as 4 seções em vez de quebrar', () => {
    render(
      <ProdutoCreate
        {...(listasDoServidor as never)}
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
    for (const s of secoes) expect(screen.getByText(s)).toBeTruthy()
  })

  it('sem imposto escolhido, o campo mostra "Nenhuma"', () => {
    render(
      <ProdutoCreate
        {...(listasDoServidor as never)}
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
    expect(document.querySelector('#tax')?.textContent).toContain('Nenhuma')
  })
})

// UC-PCAD-12 — a validade do Novo produto. O ProductController@store só grava a validade quando
// vêm o número E o período (`expiry_period_type`). A tela mandava o período sempre vazio: a
// validade digitada era descartada. Agora segue o Blade: meses por padrão; com a validade lançada
// na compra (`add_expiry`), o campo fica escondido e vai como 12 meses.
describe('UC-PCAD-12 · Novo produto — validade', () => {
  const novo = (enableExpiry: boolean, expiryType?: string) =>
    render(
      <ProdutoCreate
        {...(listasDoServidor as never)}
        barcodeDefault="C128"
        defaultProfitPercent={25}
        duplicateProduct={null}
        sellingPriceGroupCount={0}
        commonSettings={{}}
        enableExpiry={enableExpiry}
        expiryType={expiryType}
        enableLot={false}
        enableRacks={false}
      />,
    )
  const salvar = (container: HTMLElement) => {
    enviado.payload = null
    fireEvent.submit(container.querySelector('form#produto-create-form') as HTMLFormElement)
    return enviado.payload as Record<string, unknown>
  }

  it('validade pela fabricação: "Expira em" em meses, e o envio leva o número com o período', () => {
    const { container } = novo(true, 'add_manufacturing')

    expect(screen.getByText('Expira em')).toBeTruthy()
    expect(document.querySelector('#expiry_period_type')?.textContent).toContain('meses')
    fireEvent.change(document.querySelector('#expiry_period') as HTMLInputElement, { target: { value: '6' } })

    const envio = salvar(container)
    expect(envio.expiry_period).toBe('6')
    expect(envio.expiry_period_type).toBe('months')
  })

  it('validade lançada na compra: campo escondido e o envio leva 12 meses, como o Blade', () => {
    const { container } = novo(true, 'add_expiry')

    expect(document.querySelector('#expiry_period')).toBeNull()
    const envio = salvar(container)
    expect(envio.expiry_period).toBe('12')
    expect(envio.expiry_period_type).toBe('months')
  })

  it('empresa sem validade: o campo não aparece', () => {
    novo(false)
    expect(document.querySelector('#expiry_period')).toBeNull()
  })
})

describe('Editar produto com as listas do servidor', () => {
  it('renderiza as 4 seções e mostra o imposto do produto', () => {
    render(
      <ProdutoEdit
        {...(listasDoServidor as never)}
        product={{
          id: 9, name: 'Lona 440g', sku: 'LON-440', type: 'single', brandId: 3, unitId: 5,
          subUnitIds: null, categoryId: 1, subCategoryId: null, tax: 1, taxType: 'exclusive',
          origemMercadoria: 0, barcodeType: 'C128', enableStock: true, alertQuantity: null,
          weight: null, productDescription: null, productLocations: [1], image: null,
          warrantyId: null, camposPersonalizados: {}, expiryPeriod: null, expiryPeriodType: null,
        }}
        subUnits={{}}
        permissions={{ opening_stock: false, delete: false }}
      />,
    )
    for (const s of secoes) expect(screen.getByText(s)).toBeTruthy()
    expect(document.querySelector('#tax')?.textContent).toContain('ICMS 18%')
  })
})

// UC-PCAD-13 e UC-PCAD-14 — o NCM no Novo produto (CU-PROD-16 e CU-PROD-18 do SDD). Decisão [M]
// 2026-10-20: o NCM entra no cadastro, mas não é obrigatório, porque produto de serviço não tem
// NCM. Sem NCM a tela só avisa; o envio segue.
describe('UC-PCAD-13 · UC-PCAD-14 · NCM no Novo produto', () => {
  const novo = (duplicateProduct: Record<string, unknown> | null = null) =>
    render(
      <ProdutoCreate
        {...(listasDoServidor as never)}
        barcodeDefault="C128"
        defaultProfitPercent={25}
        duplicateProduct={duplicateProduct as never}
        sellingPriceGroupCount={0}
        commonSettings={{}}
        enableExpiry={false}
        enableLot={false}
        enableRacks={false}
      />,
    )
  const campoNcm = () => document.querySelector('#ncm') as HTMLInputElement
  const aviso = () => screen.queryByTestId('ncm-aviso')
  const salvar = (container: HTMLElement) => {
    enviado.payload = null
    fireEvent.submit(container.querySelector('form#produto-create-form') as HTMLFormElement)
    return enviado.payload as Record<string, unknown> | null
  }
  const produtoOriginal = (ncm: string) => ({
    id: 21, name: 'Lona 440g', brand_id: null, unit_id: 4, category_id: null, sub_category_id: null,
    tax: null, tax_type: 'exclusive', origem_mercadoria: 0, ncm, barcode_type: 'C128', sku: 'LON-440',
    alert_quantity: null, weight: null, product_description: null, type: 'single',
  })

  it('UC-PCAD-14 · sem NCM a tela avisa, e o produto é enviado assim mesmo', () => {
    const { container } = novo()

    expect(campoNcm().value).toBe('')
    expect(aviso()?.textContent).toContain('Sem NCM a nota fiscal de mercadoria não sai')

    const envio = salvar(container)
    expect(envio).not.toBeNull()
    expect(envio?.ncm).toBe('')
  })

  it('UC-PCAD-14 · NCM incompleto avisa que são 8 dígitos', () => {
    novo()
    fireEvent.change(campoNcm(), { target: { value: '4911' } })

    expect(aviso()?.textContent).toBe('O NCM tem 8 dígitos.')
  })

  it('UC-PCAD-13 · NCM digitado com pontos vai só com os 8 dígitos, e o aviso some', () => {
    const { container } = novo()
    fireEvent.change(campoNcm(), { target: { value: '4911.10.90' } })

    expect(campoNcm().value).toBe('49111090')
    expect(aviso()).toBeNull()
    expect(salvar(container)?.ncm).toBe('49111090')
  })

  it('UC-PCAD-13 · duplicar traz o NCM do original, e o "0" do banco aparece vazio', () => {
    const comNcm = novo(produtoOriginal('4911.10.90'))
    expect(campoNcm().value).toBe('49111090')
    comNcm.unmount()

    novo(produtoOriginal('0'))
    expect(campoNcm().value).toBe('')
    expect(aviso()).not.toBeNull()
  })
})

// UC-PEDIT-11 — o NCM na tela Editar. A tela mostra o NCM gravado, só com dígitos, e só o manda
// quando a pessoa mexeu nele: um NCM antigo fora do formato não pode barrar a edição de outro campo.
describe('UC-PEDIT-11 · NCM no Editar produto', () => {
  const editar = (ncm: string | null) =>
    render(
      <ProdutoEdit
        {...(listasDoServidor as never)}
        product={{
          id: 9, name: 'Lona 440g', sku: 'LON-440', type: 'single', brandId: 3, unitId: 5,
          subUnitIds: null, categoryId: 1, subCategoryId: null, tax: 1, taxType: 'exclusive',
          origemMercadoria: 0, ncm, barcodeType: 'C128', enableStock: true, alertQuantity: null,
          weight: null, productDescription: null, productLocations: [1], image: null,
          warrantyId: null, camposPersonalizados: {}, expiryPeriod: null, expiryPeriodType: null,
        }}
        subUnits={{}}
        permissions={{ opening_stock: false, delete: false }}
      />,
    )
  const campoNcm = () => document.querySelector('#ncm') as HTMLInputElement
  const aviso = () => screen.queryByTestId('ncm-aviso')
  const salvar = (container: HTMLElement) => {
    enviado.payload = null
    fireEvent.submit(container.querySelector('form#produto-edit-form') as HTMLFormElement)
    return enviado.payload as Record<string, unknown> | null
  }

  it('mostra o NCM gravado só com dígitos', () => {
    editar('4911.10.90')
    expect(campoNcm().value).toBe('49111090')
    expect(aviso()).toBeNull()
  })

  it('sem mexer no NCM, o envio não leva o campo', () => {
    const { container } = editar('49111090')
    fireEvent.change(document.querySelector('#name') as HTMLInputElement, { target: { value: 'Lona 440g fosca' } })

    const envio = salvar(container)
    expect(envio?.name).toBe('Lona 440g fosca')
    expect(envio && 'ncm' in envio).toBe(false)
  })

  it('NCM antigo fora do formato avisa, mas não vai no envio se a pessoa não mexer nele', () => {
    const { container } = editar('4911')
    expect(aviso()?.textContent).toBe('O NCM tem 8 dígitos.')

    const envio = salvar(container)
    expect(envio).not.toBeNull()
    expect(envio && 'ncm' in envio).toBe(false)
  })

  it('NCM alterado vai no envio', () => {
    const { container } = editar('49111090')
    fireEvent.change(campoNcm(), { target: { value: '3926.90.90' } })

    expect(salvar(container)?.ncm).toBe('39269090')
  })

  it('NCM apagado vai vazio no envio, e a tela avisa', () => {
    const { container } = editar('49111090')
    fireEvent.change(campoNcm(), { target: { value: '' } })

    expect(aviso()?.textContent).toContain('Sem NCM a nota fiscal de mercadoria não sai')
    expect(salvar(container)?.ncm).toBe('')
  })
})

// UC-PCAD-15 e UC-PEDIT-12 — o CEST no cadastro. 7 dígitos (Convênio ICMS 142/2018). Só a mercadoria
// com substituição tributária precisa dele: vazio não avisa; só o CEST incompleto avisa.
describe('UC-PCAD-15 · UC-PEDIT-12 · CEST no cadastro', () => {
  const novo = (duplicateProduct: Record<string, unknown> | null = null) =>
    render(
      <ProdutoCreate
        {...(listasDoServidor as never)}
        barcodeDefault="C128"
        defaultProfitPercent={25}
        duplicateProduct={duplicateProduct as never}
        sellingPriceGroupCount={0}
        commonSettings={{}}
        enableExpiry={false}
        enableLot={false}
        enableRacks={false}
      />,
    )
  const editar = (cest: string | null) =>
    render(
      <ProdutoEdit
        {...(listasDoServidor as never)}
        product={{
          id: 9, name: 'Lona 440g', sku: 'LON-440', type: 'single', brandId: 3, unitId: 5,
          subUnitIds: null, categoryId: 1, subCategoryId: null, tax: 1, taxType: 'exclusive',
          origemMercadoria: 0, ncm: '49111090', cest, barcodeType: 'C128', enableStock: true,
          alertQuantity: null, weight: null, productDescription: null, productLocations: [1],
          image: null, warrantyId: null, camposPersonalizados: {}, expiryPeriod: null,
          expiryPeriodType: null,
        }}
        subUnits={{}}
        permissions={{ opening_stock: false, delete: false }}
      />,
    )
  const campoCest = () => document.querySelector('#cest') as HTMLInputElement
  const aviso = () => screen.queryByTestId('cest-aviso')
  const salvar = (container: HTMLElement, form: string) => {
    enviado.payload = null
    fireEvent.submit(container.querySelector(`form#${form}`) as HTMLFormElement)
    return enviado.payload as Record<string, unknown> | null
  }

  it('UC-PCAD-15 · sem CEST não avisa, e o envio leva o campo vazio', () => {
    const { container } = novo()

    expect(campoCest().value).toBe('')
    expect(aviso()).toBeNull()
    expect(salvar(container, 'produto-create-form')?.cest).toBe('')
  })

  it('UC-PCAD-15 · CEST com pontos vai só com os 7 dígitos', () => {
    const { container } = novo()
    fireEvent.change(campoCest(), { target: { value: '28.038.00' } })

    expect(campoCest().value).toBe('2803800')
    expect(aviso()).toBeNull()
    expect(salvar(container, 'produto-create-form')?.cest).toBe('2803800')
  })

  it('UC-PCAD-15 · CEST incompleto avisa que são 7 dígitos', () => {
    novo()
    fireEvent.change(campoCest(), { target: { value: '28038' } })

    expect(aviso()?.textContent).toBe('O CEST tem 7 dígitos.')
  })

  it('UC-PCAD-15 · duplicar traz o CEST do original', () => {
    novo({
      id: 21, name: 'Lona 440g', brand_id: null, unit_id: 4, category_id: null, sub_category_id: null,
      tax: null, tax_type: 'exclusive', origem_mercadoria: 0, ncm: '49111090', cest: '28.038.00',
      barcode_type: 'C128', sku: 'LON-440', alert_quantity: null, weight: null,
      product_description: null, type: 'single',
    })
    expect(campoCest().value).toBe('2803800')
  })

  it('UC-PEDIT-12 · mostra o CEST gravado e, sem mexer nele, o envio não leva o campo', () => {
    const { container } = editar('28.038.00')
    expect(campoCest().value).toBe('2803800')

    const envio = salvar(container, 'produto-edit-form')
    expect(envio).not.toBeNull()
    expect(envio && 'cest' in envio).toBe(false)
  })

  it('UC-PEDIT-12 · CEST alterado vai no envio', () => {
    const { container } = editar('2803800')
    fireEvent.change(campoCest(), { target: { value: '1700100' } })

    expect(salvar(container, 'produto-edit-form')?.cest).toBe('1700100')
  })

  it('UC-PEDIT-12 · CEST apagado vai vazio no envio', () => {
    const { container } = editar('2803800')
    fireEvent.change(campoCest(), { target: { value: '' } })

    expect(salvar(container, 'produto-edit-form')?.cest).toBe('')
  })
})

// UC-PCAD-16 e UC-PEDIT-13 — peso líquido e dimensões da embalagem (CU-PROD-17). O "Peso" de
// sempre passa a se chamar "Peso bruto (kg)"; os quatro campos novos são opcionais.
describe('UC-PCAD-16 · UC-PEDIT-13 · peso e dimensões no cadastro', () => {
  const novo = () =>
    render(
      <ProdutoCreate
        {...(listasDoServidor as never)}
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
  const editar = () =>
    render(
      <ProdutoEdit
        {...(listasDoServidor as never)}
        product={{
          id: 9, name: 'Lona 440g', sku: 'LON-440', type: 'single', brandId: 3, unitId: 5,
          subUnitIds: null, categoryId: 1, subCategoryId: null, tax: 1, taxType: 'exclusive',
          origemMercadoria: 0, ncm: '49111090', cest: null, barcodeType: 'C128', enableStock: true,
          alertQuantity: null, weight: 1.6, pesoLiquido: '1.250', larguraCm: '30.00',
          alturaCm: '20.50', comprimentoCm: '10.00', productDescription: null, productLocations: [1],
          image: null, warrantyId: null, camposPersonalizados: {}, expiryPeriod: null,
          expiryPeriodType: null,
        }}
        subUnits={{}}
        permissions={{ opening_stock: false, delete: false }}
      />,
    )
  const campo = (id: string) => document.querySelector(`#${id}`) as HTMLInputElement
  const salvar = (container: HTMLElement, form: string) => {
    enviado.payload = null
    fireEvent.submit(container.querySelector(`form#${form}`) as HTMLFormElement)
    return enviado.payload as Record<string, unknown> | null
  }

  it('UC-PCAD-16 · o peso de sempre aparece como peso bruto, e os campos novos existem', () => {
    novo()
    expect(document.querySelector('label[for="weight"]')?.textContent).toBe('Peso bruto (kg)')
    for (const id of ['peso_liquido', 'largura_cm', 'altura_cm', 'comprimento_cm']) {
      expect(campo(id)).not.toBeNull()
    }
  })

  it('UC-PCAD-16 · o envio leva o peso líquido e as dimensões digitados', () => {
    const { container } = novo()
    fireEvent.change(campo('peso_liquido'), { target: { value: '1.25' } })
    fireEvent.change(campo('largura_cm'), { target: { value: '30' } })
    fireEvent.change(campo('altura_cm'), { target: { value: '20.5' } })
    fireEvent.change(campo('comprimento_cm'), { target: { value: '10' } })

    const envio = salvar(container, 'produto-create-form')
    expect(envio?.peso_liquido).toBe('1.25')
    expect(envio?.largura_cm).toBe('30')
    expect(envio?.altura_cm).toBe('20.5')
    expect(envio?.comprimento_cm).toBe('10')
  })

  it('UC-PCAD-16 · sem preencher, o envio leva os quatro vazios', () => {
    const { container } = novo()
    const envio = salvar(container, 'produto-create-form')
    for (const id of ['peso_liquido', 'largura_cm', 'altura_cm', 'comprimento_cm']) {
      expect(envio?.[id]).toBe('')
    }
  })

  it('UC-PEDIT-13 · o Editar mostra os valores gravados e os devolve no envio', () => {
    const { container } = editar()
    expect(campo('peso_liquido').value).toBe('1.250')
    expect(campo('largura_cm').value).toBe('30.00')

    const envio = salvar(container, 'produto-edit-form')
    expect(envio?.peso_liquido).toBe('1.250')
    expect(envio?.altura_cm).toBe('20.50')
  })

  it('UC-PEDIT-13 · campo apagado vai vazio no envio', () => {
    const { container } = editar()
    fireEvent.change(campo('altura_cm'), { target: { value: '' } })

    expect(salvar(container, 'produto-edit-form')?.altura_cm).toBe('')
  })
})
