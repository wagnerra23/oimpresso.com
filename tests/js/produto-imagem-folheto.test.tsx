// UC-PCAD-29 (Create.casos.md) · UC-PEDIT-29 (Edit.casos.md): "Imagem e folheto" no cadastro de produto.
// Âncora: protótipo do [W] (produto-blade-forms.jsx: "Arraste a imagem (1:1, até 5 MB) + folheto em PDF"),
// paridade com o Blade (imagem e folheto no cadastro, limite de 5 MB) e pedido do [F] em 2026-11-16.
// No Novo os arquivos vão com o produto; no Editar gravam na hora pela rota própria, e o resto do
// Editar continua indo como JSON.
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

const enviado: { payload: Record<string, unknown> | null } = { payload: null }

vi.mock('@inertiajs/react', async () => {
  const React = await import('react')
  return {
    Head: () => null,
    Deferred: ({ children }: { children: React.ReactNode }) => children,
    Link: ({ href, children, ...resto }: { href: string; children: React.ReactNode }) =>
      React.createElement('a', { href, ...resto }, children),
    router: { reload: vi.fn() },
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
import { CampoMidia } from '@/Pages/Produto/_components/formulario/CampoMidia'
import type { CamposProduto } from '@/Pages/Produto/_components/formulario/tipos'

function arquivo(nome: string, tipo: string, bytes = 10): File {
  return new File([new Uint8Array(bytes)], nome, { type: tipo })
}

let pedidos: Array<{ url: string; corpo: FormData }>
function responder(resposta: unknown, ok = true) {
  vi.stubGlobal(
    'fetch',
    vi.fn(async (url: string, init: { body: FormData }) => {
      pedidos.push({ url, corpo: init.body })
      return { ok, json: async () => resposta }
    }),
  )
}
beforeEach(() => {
  pedidos = []
})
afterEach(() => vi.unstubAllGlobals())

describe('UC-PCAD-29 · Novo: imagem e folheto vão com o produto', () => {
  it('escolher a imagem e o folheto guarda os arquivos no formulário, sem gravar nada antes de salvar', () => {
    responder({})
    const definir = vi.fn()
    render(<CampoMidia modo="novo" data={{} as CamposProduto} definir={definir} />)

    const img = arquivo('banner.png', 'image/png')
    fireEvent.change(document.getElementById('produto-imagem') as HTMLInputElement, { target: { files: [img] } })
    const pdf = arquivo('folheto.pdf', 'application/pdf')
    fireEvent.change(document.getElementById('produto-folheto') as HTMLInputElement, { target: { files: [pdf] } })

    expect(definir).toHaveBeenCalledWith('image', img)
    expect(definir).toHaveBeenCalledWith('product_brochure', pdf)
    expect(pedidos).toHaveLength(0)
  })

  it('arquivo acima de 5 MB é recusado na tela', () => {
    const definir = vi.fn()
    render(<CampoMidia modo="novo" data={{} as CamposProduto} definir={definir} />)

    fireEvent.change(document.getElementById('produto-imagem') as HTMLInputElement, {
      target: { files: [arquivo('grande.png', 'image/png', 5_000_001)] },
    })
    expect(screen.getByRole('alert').textContent).toMatch(/5 MB/)
    expect(definir).not.toHaveBeenCalled()
  })
})

describe('UC-PEDIT-29 · Editar: imagem e folheto gravam na hora', () => {
  it('a imagem vai para a rota própria e a tela mostra a nova', async () => {
    responder({ success: true, imagem: '/uploads/img/1_nova.png', folheto: null })
    render(<CampoMidia modo="editar" data={{} as CamposProduto} definir={vi.fn()} produtoId={9} imagemGravada={null} folhetoGravado={null} />)

    const img = arquivo('nova.png', 'image/png')
    fireEvent.change(document.getElementById('produto-imagem') as HTMLInputElement, { target: { files: [img] } })

    expect(await screen.findByText('Imagem gravada.')).toBeTruthy()
    expect(pedidos).toHaveLength(1)
    expect(pedidos[0].url).toBe('/products/9/midia')
    // O FormData guarda uma cópia do arquivo: confere nome e tamanho.
    const enviadoNoPedido = pedidos[0].corpo.get('image') as File
    expect([enviadoNoPedido.name, enviadoNoPedido.size]).toEqual([img.name, img.size])
    expect((screen.getByAltText('Imagem do produto') as HTMLImageElement).src).toContain('/uploads/img/1_nova.png')
  })

  it('a recusa do servidor aparece na tela', async () => {
    responder({ message: 'Erro', errors: { image: ['A imagem precisa ser um arquivo de imagem (JPG, PNG, WEBP).'] } }, false)
    render(<CampoMidia modo="editar" data={{} as CamposProduto} definir={vi.fn()} produtoId={9} />)

    fireEvent.change(document.getElementById('produto-imagem') as HTMLInputElement, { target: { files: [arquivo('x.png', 'image/png')] } })
    expect((await screen.findByRole('alert')).textContent).toMatch(/arquivo de imagem/)
  })

  it('o folheto gravado aparece com o link, e trocar a imagem não muda o envio do Editar', async () => {
    responder({ success: true, imagem: '/uploads/img/1_nova.png', folheto: { nome: 'folheto.pdf', url: '/uploads/media/1_folheto.pdf' } })
    const produto = {
      id: 9, name: 'Banner', sku: 'BAN-01', type: 'single' as const, brandId: null, unitId: 4, subUnitIds: null,
      categoryId: 1, subCategoryId: null, tax: null, taxType: 'exclusive' as const, origemMercadoria: 0,
      barcodeType: 'C128', enableStock: true, alertQuantity: null, weight: null, productDescription: null,
      productLocations: [3], image: null, folheto: { nome: 'folheto.pdf', url: '/uploads/media/1_folheto.pdf' },
      warrantyId: null, camposPersonalizados: {} as Record<number, string | null>,
      expiryPeriod: null as number | null, expiryPeriodType: null as string | null,
    }
    const props = {
      categories: { 1: 'Banner' }, brands: {}, units: { 4: 'Unidade (UN)' }, subUnits: {}, taxes: { '': 'Nenhum' },
      taxAttributes: {}, barcodeTypes: { C128: 'Code 128' }, subCategories: {}, businessLocations: { 3: 'Loja' },
      rackDetails: null, productTypes: { single: 'Simples' }, warranties: {}, permissions: { opening_stock: true, delete: false },
    }
    const { container } = render(<ProdutoEdit {...props} product={produto} />)

    expect(screen.getByRole('link', { name: 'Folheto: folheto.pdf' })).toBeTruthy()
    fireEvent.change(document.getElementById('produto-imagem') as HTMLInputElement, { target: { files: [arquivo('nova.png', 'image/png')] } })
    await waitFor(() => expect(screen.getByText('Imagem gravada.')).toBeTruthy())

    enviado.payload = null
    fireEvent.submit(container.querySelector('form#produto-edit-form') as HTMLFormElement)
    const envio = enviado.payload as unknown as Record<string, unknown>
    expect('image' in envio).toBe(false)
    expect('product_brochure' in envio).toBe(false)
  })
})
