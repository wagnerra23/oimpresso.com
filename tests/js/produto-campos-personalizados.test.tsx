// UC-PCAD-24 (Create.casos.md) · UC-PEDIT-24 (Edit.casos.md): os campos personalizados aparecem com o
// nome que a empresa deu em Configurações → Rótulos; campo sem nome não aparece, mas o valor dele
// continua no envio. Âncora: protótipo do [W] (produto-blade-forms.jsx, nota D7 de 2026-10-05) e
// pedido do [F] em 2026-11-16.
import { render, screen, fireEvent, within } from '@testing-library/react'
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
import { BlocoConfiguracoes } from '@/Pages/Produto/_components/formulario/BlocoConfiguracoes'
import { camposPersonalizadosGravados } from '@/Pages/Produto/_components/formulario/tipos'
import type { CampoDaEmpresa, CamposProduto } from '@/Pages/Produto/_components/formulario/tipos'

const campos: CampoDaEmpresa[] = [
  { numero: 3, rotulo: 'Cor da lona', tipo: 'text', opcoes: [] },
  { numero: 7, rotulo: 'Validade do lote', tipo: 'date', opcoes: [] },
  { numero: 12, rotulo: 'Acabamento', tipo: 'dropdown', opcoes: ['Fosco', 'Brilho'] },
]

function dados(extra: Partial<CamposProduto> = {}): CamposProduto {
  return { ...camposPersonalizadosGravados(null), ...extra } as unknown as CamposProduto
}

describe('UC-PCAD-24 · campos personalizados com o nome da empresa', () => {
  it('mostra só os campos com nome, com o nome que a empresa deu', () => {
    render(<BlocoConfiguracoes modo="novo" data={dados()} definir={vi.fn()} campos={campos} />)

    expect(screen.getByLabelText('Cor da lona')).toBeTruthy()
    expect(screen.getByLabelText('Validade do lote')).toBeTruthy()
    expect(screen.getByLabelText('Acabamento')).toBeTruthy()
    expect(screen.queryByText(/Campo personalizado/)).toBeNull()
    expect(document.getElementById('product_custom_field1')).toBeNull()
  })

  it('sem nenhum campo nomeado, a linha não aparece', () => {
    render(<BlocoConfiguracoes modo="novo" data={dados()} definir={vi.fn()} campos={[]} />)

    expect(screen.queryByText(/Campo personalizado/)).toBeNull()
    expect(document.querySelector('[id^="product_custom_field"]')).toBeNull()
  })

  it('o texto digitado vai para o campo do número certo', () => {
    const definir = vi.fn()
    render(<BlocoConfiguracoes modo="novo" data={dados()} definir={definir} campos={campos} />)

    fireEvent.change(screen.getByLabelText('Cor da lona'), { target: { value: 'Branca' } })
    expect(definir).toHaveBeenCalledWith('product_custom_field3', 'Branca')
  })

  it('campo do tipo data é um campo de data', () => {
    render(<BlocoConfiguracoes modo="novo" data={dados()} definir={vi.fn()} campos={campos} />)

    expect((screen.getByLabelText('Validade do lote') as HTMLInputElement).type).toBe('date')
    expect((screen.getByLabelText('Cor da lona') as HTMLInputElement).type).toBe('text')
  })

  it('campo do tipo lista mostra o valor gravado, mesmo fora das opções', () => {
    render(
      <BlocoConfiguracoes
        modo="editar"
        data={dados({ product_custom_field12: 'Acetinado' } as Partial<CamposProduto>)}
        definir={vi.fn()}
        campos={campos}
      />,
    )

    expect(within(screen.getByLabelText('Acabamento')).getByText('Acetinado')).toBeTruthy()
  })
})

const produto = {
  id: 9, name: 'Banner lona', sku: 'BAN-01', type: 'single' as const, brandId: null, unitId: 4,
  subUnitIds: null, categoryId: 1, subCategoryId: null, tax: null, taxType: 'exclusive' as const,
  origemMercadoria: 0, barcodeType: 'C128', enableStock: true, alertQuantity: null, weight: null,
  productDescription: null, productLocations: [3], image: null, warrantyId: null,
  camposPersonalizados: { 3: 'Branca', 5: 'Lona fosca' } as Record<number, string | null>,
  expiryPeriod: null as number | null, expiryPeriodType: null as string | null,
}

const props = {
  categories: { 1: 'Banner' }, brands: {}, units: { '': 'Selecionar', 4: 'Unidade (UN)' }, subUnits: {},
  taxes: { '': 'Nenhum' }, taxAttributes: {}, barcodeTypes: { C128: 'Code 128' }, subCategories: {},
  businessLocations: { 3: 'Loja' }, rackDetails: null, productTypes: { single: 'Simples' }, warranties: {},
  permissions: { opening_stock: true, delete: false },
}

describe('UC-PEDIT-24 · Editar com os campos da empresa', () => {
  it('mostra o valor gravado no campo com nome e devolve no envio também o campo sem nome', () => {
    const { container } = render(<ProdutoEdit {...props} product={produto} camposDaEmpresa={campos} />)

    expect((screen.getByLabelText('Cor da lona') as HTMLInputElement).value).toBe('Branca')
    expect(document.getElementById('product_custom_field5')).toBeNull()

    fireEvent.change(screen.getByLabelText('Cor da lona'), { target: { value: 'Azul' } })
    enviado.payload = null
    fireEvent.submit(container.querySelector('form#produto-edit-form') as HTMLFormElement)
    const envio = enviado.payload as unknown as Record<string, unknown>
    expect(envio.product_custom_field3).toBe('Azul')
    expect(envio.product_custom_field5).toBe('Lona fosca')
  })
})
