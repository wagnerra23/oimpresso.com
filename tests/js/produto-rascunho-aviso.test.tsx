// UC-PCAD-30 (Create.casos.md) · UC-PEDIT-30 (Edit.casos.md): rascunho automático do Novo produto e aviso
// ao sair com alterações não salvas. Âncora: protótipo do [W] (produto-blade-forms.jsx: "o cadastro é
// longo — recarregar a página não pode perder o trabalho", `beforeunload`, "Rascunho salvo
// automaticamente") e pedido do [F] em 2026-11-16. O rascunho fica numa chave por empresa e usuário
// que o servidor manda: uma empresa nunca recupera o rascunho de outra.
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

const enviado: { payload: Record<string, unknown> | null; opcoes: { onSuccess?: () => void; onError?: () => void } | null } = {
  payload: null,
  opcoes: null,
}

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
      const enviar = (_url: string, opcoes?: { onSuccess?: () => void; onError?: () => void }) => {
        enviado.payload = transformar.current(data)
        enviado.opcoes = opcoes ?? null
      }
      return {
        data,
        setData: (k: string, v: unknown) => setData((d) => ({ ...d, [k]: v })),
        transform: (fn: (d: Record<string, unknown>) => Record<string, unknown>) => {
          transformar.current = fn
        },
        post: enviar,
        put: enviar,
        processing: false,
        errors: {},
      }
    },
  }
})
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: unknown }) => children }))

import ProdutoCreate from '@/Pages/Produto/Create'
import ProdutoEdit from '@/Pages/Produto/Edit'
import { PREFIXO_RASCUNHO } from '@/Pages/Produto/_components/formulario/rascunho'

const base = {
  categories: { 1: 'Banner' }, brands: {}, units: { 4: 'Unidade (UN)' }, taxes: {}, taxAttributes: {},
  barcodeTypes: { C128: 'Code 128' }, barcodeDefault: 'C128', defaultProfitPercent: 25, duplicateProduct: null,
  subCategories: {}, businessLocations: { 3: 'Loja' }, rackDetails: null, sellingPriceGroupCount: 0,
  productTypes: { single: 'Simples' }, warranties: {}, commonSettings: {}, enableExpiry: false, enableLot: false,
  enableRacks: false, rascunho: '98.7',
}
const CHAVE = PREFIXO_RASCUNHO + '98.7'

function navegarPorLink(metodo = 'get', only: string[] = []) {
  const evento = new CustomEvent('inertia:before', { cancelable: true, detail: { visit: { method: metodo, only } } })
  document.dispatchEvent(evento)
  return evento.defaultPrevented
}

beforeEach(() => {
  window.localStorage.clear()
  enviado.payload = null
  enviado.opcoes = null
})
afterEach(() => vi.restoreAllMocks())

describe('UC-PCAD-30 · rascunho automático do Novo produto', () => {
  it('o que a pessoa digita fica guardado no navegador, na chave da empresa e do usuário', async () => {
    render(<ProdutoCreate {...base} />)
    fireEvent.change(document.querySelector('#name') as HTMLInputElement, { target: { value: 'Lona 440g' } })

    await waitFor(() => expect(window.localStorage.getItem(CHAVE)).not.toBeNull(), { timeout: 2000 })
    expect(JSON.parse(window.localStorage.getItem(CHAVE) as string).form.name).toBe('Lona 440g')
    expect(screen.getByText(/Rascunho salvo automaticamente às/)).toBeTruthy()
  })

  it('reabrir o Novo recupera o rascunho e avisa; "Descartar rascunho" limpa a tela e o navegador', () => {
    window.localStorage.setItem(CHAVE, JSON.stringify({ em: '2026-11-16T12:00:00Z', form: { name: 'Banner 2x1', sku: 'BAN-21' } }))
    render(<ProdutoCreate {...base} />)

    expect((document.querySelector('#name') as HTMLInputElement).value).toBe('Banner 2x1')
    expect(screen.getByRole('status').textContent).toMatch(/Rascunho recuperado: você parou em “Banner 2x1”/)

    fireEvent.click(screen.getByRole('button', { name: 'Descartar rascunho' }))
    expect((document.querySelector('#name') as HTMLInputElement).value).toBe('')
    expect(window.localStorage.getItem(CHAVE)).toBeNull()
  })

  it('rascunho de outra empresa ou usuário não aparece', () => {
    window.localStorage.setItem(PREFIXO_RASCUNHO + '99.7', JSON.stringify({ form: { name: 'De outra empresa' } }))
    render(<ProdutoCreate {...base} />)

    expect((document.querySelector('#name') as HTMLInputElement).value).toBe('')
    expect(screen.queryByText(/Rascunho recuperado/)).toBeNull()
  })

  it('duplicar produto não recupera rascunho por cima do original', () => {
    window.localStorage.setItem(CHAVE, JSON.stringify({ form: { name: 'Rascunho velho' } }))
    const dup = { id: 5, name: 'Original', brand_id: null, unit_id: 4, category_id: null, sub_category_id: null, tax: null, tax_type: 'exclusive' as const, barcode_type: 'C128', sku: '', alert_quantity: null, weight: null, product_description: null, type: 'single' as const }
    render(<ProdutoCreate {...base} duplicateProduct={dup} />)

    expect((document.querySelector('#name') as HTMLInputElement).value).toBe('Original')
  })

  // Teste no Chrome em 2026-11-16 (WR2, biz=1): depois do F5 o nome e o SKU voltavam, mas Unidade,
  // sub-unidade, Marca, Categoria, Garantia e Origem da mercadoria voltavam vazios, e o rascunho era
  // regravado com 0 nesses campos. O Select do Radix, dentro de <form>, tem um <select> nativo
  // escondido; quando o rascunho muda o valor logo depois de abrir a tela, as opções desse <select>
  // ainda não existem, ele fica vazio e o Radix chama onValueChange('') — que virava Number('') = 0.
  it('o rascunho volta com os campos de lista e não os apaga ao regravar', async () => {
    const props = {
      ...base,
      units: { 4: 'Unidade (UN)' },
      subUnidadesPorUnidade: { 4: [{ id: 4, nome: 'Unidade', multiplicador: 1 }, { id: 9, nome: 'cx', multiplicador: 12 }] },
      brands: { 2: 'Imprimax' },
      categories: { 1: 'Banner' },
      warranties: { 5: 'Um ano' },
    }
    const gravado = { name: 'Lona 440g', unit_id: 4, sub_unit_ids: [9], brand_id: 2, category_id: 1, warranty_id: 5, origem_mercadoria: 3 }
    window.localStorage.setItem(CHAVE, JSON.stringify({ em: '2026-11-16T12:00:00Z', form: gravado }))
    render(<ProdutoCreate {...props} />)

    // A tela regrava o rascunho logo depois de recuperar: é essa regravação que perdia os campos.
    await waitFor(() => expect(JSON.parse(window.localStorage.getItem(CHAVE) as string).em).not.toBe('2026-11-16T12:00:00Z'), { timeout: 2000 })
    const regravado = JSON.parse(window.localStorage.getItem(CHAVE) as string).form
    expect({
      unit_id: regravado.unit_id, sub_unit_ids: regravado.sub_unit_ids, brand_id: regravado.brand_id,
      category_id: regravado.category_id, warranty_id: regravado.warranty_id, origem_mercadoria: regravado.origem_mercadoria,
    }).toEqual({ unit_id: 4, sub_unit_ids: [9], brand_id: 2, category_id: 1, warranty_id: 5, origem_mercadoria: 3 })
    expect(document.getElementById('unit_id')?.textContent).toContain('Unidade (UN)')
    expect(document.getElementById('brand_id')?.textContent).toContain('Imprimax')
  })

  it('salvo com sucesso, o rascunho some', () => {
    window.localStorage.setItem(CHAVE, JSON.stringify({ form: { name: 'Banner 2x1' } }))
    render(<ProdutoCreate {...base} />)
    fireEvent.click(screen.getByRole('button', { name: 'Salvar' }))

    expect(enviado.payload).not.toBeNull()
    act(() => enviado.opcoes?.onSuccess?.())
    expect(window.localStorage.getItem(CHAVE)).toBeNull()
  })
})

describe('UC-PCAD-30 · aviso ao sair do Novo produto', () => {
  it('sem nada preenchido, sair não pergunta', () => {
    const confirmar = vi.spyOn(window, 'confirm')
    render(<ProdutoCreate {...base} />)

    expect(navegarPorLink()).toBe(false)
    expect(confirmar).not.toHaveBeenCalled()
  })

  it('com alteração, sair por link pergunta, e "Cancelar" fica na tela', () => {
    const confirmar = vi.spyOn(window, 'confirm').mockReturnValue(false)
    render(<ProdutoCreate {...base} />)
    fireEvent.change(document.querySelector('#name') as HTMLInputElement, { target: { value: 'Lona' } })

    expect(navegarPorLink()).toBe(true)
    expect(confirmar).toHaveBeenCalledWith('Há alterações não salvas neste produto. Sair mesmo assim?')
  })

  it('com alteração, fechar ou recarregar a aba avisa', () => {
    render(<ProdutoCreate {...base} />)
    fireEvent.change(document.querySelector('#name') as HTMLInputElement, { target: { value: 'Lona' } })

    const evento = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(evento)
    expect(evento.defaultPrevented).toBe(true)
  })

  it('o próprio salvar e a recarga parcial de uma lista não perguntam', () => {
    const confirmar = vi.spyOn(window, 'confirm').mockReturnValue(false)
    render(<ProdutoCreate {...base} />)
    fireEvent.change(document.querySelector('#name') as HTMLInputElement, { target: { value: 'Lona' } })

    expect(navegarPorLink('post')).toBe(false)
    expect(navegarPorLink('get', ['units'])).toBe(false)
    expect(confirmar).not.toHaveBeenCalled()
  })
})

const produto = {
  id: 9, name: 'Banner', sku: 'BAN-01', type: 'single' as const, brandId: null, unitId: 4, subUnitIds: null,
  categoryId: 1, subCategoryId: null, tax: null, taxType: 'exclusive' as const, origemMercadoria: 0,
  barcodeType: 'C128', enableStock: true, alertQuantity: null, weight: null, productDescription: null,
  productLocations: [3], image: null, warrantyId: null, camposPersonalizados: {} as Record<number, string | null>,
  expiryPeriod: null as number | null, expiryPeriodType: null as string | null,
}
const propsEdit = {
  categories: { 1: 'Banner' }, brands: {}, units: { 4: 'Unidade (UN)' }, subUnits: {}, taxes: { '': 'Nenhum' },
  taxAttributes: {}, barcodeTypes: { C128: 'Code 128' }, subCategories: {}, businessLocations: { 3: 'Loja' },
  rackDetails: null, productTypes: { single: 'Simples' }, warranties: {}, permissions: { opening_stock: true, delete: false },
}

describe('UC-PEDIT-30 · aviso ao sair do Editar', () => {
  it('sem mexer, sair não pergunta; depois de mexer, pergunta', () => {
    const confirmar = vi.spyOn(window, 'confirm').mockReturnValue(false)
    render(<ProdutoEdit {...propsEdit} product={produto} />)

    expect(navegarPorLink()).toBe(false)
    fireEvent.change(document.querySelector('#name') as HTMLInputElement, { target: { value: 'Banner novo' } })
    expect(navegarPorLink()).toBe(true)
    expect(confirmar).toHaveBeenCalledTimes(1)
  })

  it('depois de salvar, a navegação de volta não pergunta; se o servidor recusar, volta a perguntar', () => {
    const confirmar = vi.spyOn(window, 'confirm').mockReturnValue(false)
    const { container } = render(<ProdutoEdit {...propsEdit} product={produto} />)
    fireEvent.change(document.querySelector('#name') as HTMLInputElement, { target: { value: 'Banner novo' } })
    fireEvent.submit(container.querySelector('form#produto-edit-form') as HTMLFormElement)

    expect(navegarPorLink()).toBe(false)
    expect(confirmar).not.toHaveBeenCalled()
    act(() => enviado.opcoes?.onError?.())
    expect(navegarPorLink()).toBe(true)
  })
})
