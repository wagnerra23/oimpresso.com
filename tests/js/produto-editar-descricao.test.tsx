// Descrição gravada pela tela antiga com HTML (editor rico) no Editar React — UC-PEDIT-33.
//
// O produto "Banner teste webservice" (WR2) abria com `<p>teste obeservacao</p>` na caixa. A caixa
// passa a mostrar o texto; salvar sem mexer devolve a gravada igual; salvar mexido manda o texto novo
// em parágrafos HTML escapados, para a tela antiga seguir mostrando as linhas.
import { render, fireEvent } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'
import { descricaoParaEnvio, textoDaDescricao } from '@/Pages/Produto/_components/formulario/descricao'

const enviado: { payload: Record<string, unknown> | null } = { payload: null }

vi.mock('@inertiajs/react', async () => {
  const React = await import('react')
  return {
    Head: () => null,
    // O Editar embrulha o card Produção (prop deferida) em <Deferred>: aqui a prop já vem pronta.
    Deferred: ({ children }: { children: React.ReactNode }) => children,
    Link: ({ href, children, ...resto }: { href: string; children: React.ReactNode }) =>
      React.createElement('a', { href, ...resto }, children),
    useForm: (inicial: Record<string, unknown>) => {
      const [data, setData] = React.useState(inicial)
      // O Edit.tsx passa o envio por `transform` (UC-PEDIT-11: o NCM só vai quando a pessoa mexeu).
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

const produto = {
  id: 9,
  name: 'Banner lona',
  sku: 'BAN-01',
  type: 'single' as const,
  brandId: null,
  unitId: 4,
  subUnitIds: null,
  categoryId: 1,
  subCategoryId: null,
  tax: null,
  taxType: 'exclusive' as const,
  origemMercadoria: 0,
  barcodeType: 'C128',
  enableStock: true,
  alertQuantity: null,
  weight: null,
  productDescription: null,
  productLocations: [3],
  image: null,
  warrantyId: null,
  camposPersonalizados: {} as Record<number, string | null>,
  expiryPeriod: null as number | null,
  expiryPeriodType: null as string | null,
}

const props = {
  categories: { 1: 'Banner' },
  brands: {},
  units: { '': 'Selecionar', 4: 'Unidade (UN)' },
  subUnits: {},
  taxes: { '': 'Nenhum' },
  taxAttributes: {},
  barcodeTypes: { C128: 'Code 128' },
  subCategories: {},
  businessLocations: { 3: 'Loja' },
  rackDetails: null,
  productTypes: { single: 'Simples' },
  warranties: {},
  permissions: { opening_stock: true, delete: false },
}

function salvar(container: HTMLElement) {
  enviado.payload = null
  fireEvent.submit(container.querySelector('form#produto-edit-form') as HTMLFormElement)
  return enviado.payload as Record<string, unknown>
}


function abrir(descricao: string | null) {
  return render(<ProdutoEdit {...props} product={{ ...produto, productDescription: descricao }} />)
}

function caixa(container: HTMLElement) {
  return container.querySelector('textarea#product_description') as HTMLTextAreaElement
}

describe('UC-PEDIT-33 · Descrição gravada com HTML pela tela antiga', () => {
  it('a caixa mostra o texto, sem as tags', () => {
    const { container } = abrir('<p>teste obeservacao</p>')
    expect(caixa(container).value).toBe('teste obeservacao')
  })

  it('parágrafos e quebras viram linhas, e as entidades viram caracteres', () => {
    expect(textoDaDescricao(`<p>Lona 440g &amp; ilh&oacute;s</p>
<p>Linha 1<br />Linha 2</p>`)).toBe(
      `Lona 440g & ilhós
Linha 1
Linha 2`,
    )
  })

  it('salvar sem mexer devolve a descrição gravada, com a formatação', () => {
    const gravada = '<p><strong>Banner</strong> em lona</p><ul><li>ilhós</li></ul>'
    const { container } = abrir(gravada)
    expect(salvar(container).product_description).toBe(gravada)
  })

  it('salvar mexido manda o texto novo em parágrafos, com "<" escapado', () => {
    const { container } = abrir('<p>teste obeservacao</p>')
    fireEvent.change(caixa(container), { target: { value: `teste observação
medida < 2m

segundo` } })
    expect(salvar(container).product_description).toBe('<p>teste observação<br>medida &lt; 2m</p><p>segundo</p>')
  })

  it('descrição gravada sem HTML continua texto simples, na caixa e no envio', () => {
    const { container } = abrir('texto simples')
    expect(caixa(container).value).toBe('texto simples')
    fireEvent.change(caixa(container), { target: { value: 'texto novo' } })
    expect(salvar(container).product_description).toBe('texto novo')
  })

  it('sem descrição gravada, a caixa abre vazia e o envio vai vazio', () => {
    const { container } = abrir(null)
    expect(caixa(container).value).toBe('')
    expect(salvar(container).product_description).toBe('')
    expect(descricaoParaEnvio(null, '', '')).toBe('')
  })
})
