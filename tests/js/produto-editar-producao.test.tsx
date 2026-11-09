// UC-PEDIT-18 · CU-PROD-22 (decisão [W], opção A) — card "Produção" do Editar produto.
//
// Só leitura: o roteiro que o produto segue, com o número de etapas e o link para Produção →
// Roteiros. Sem roteiro o produto não vira OS (UC-PCROT-02), e o card diz isso; o texto do
// protótipo ("usa as etapas do modelo da empresa") só vale com a ADR 0436 item 3. Quem decide se
// o card aparece é o servidor (prop `producao` null: sem o módulo Produção, composição, serviço).
import { render, screen } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'

vi.mock('@inertiajs/react', async () => {
  const React = await import('react')
  return {
    Head: () => null,
    Link: ({ href, children, ...resto }: { href: string; children: React.ReactNode }) =>
      React.createElement('a', { href, ...resto }, children),
    useForm: (inicial: Record<string, unknown>) => {
      const [data, setData] = React.useState(inicial)
      return {
        data,
        setData: (k: string, v: unknown) => setData((d) => ({ ...d, [k]: v })),
        transform: () => {},
        put: () => {},
        processing: false,
        errors: {},
      }
    },
  }
})
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: unknown }) => children }))

import ProdutoEdit from '@/Pages/Produto/Edit'
import { BlocoProducao } from '@/Pages/Produto/_components/formulario/BlocoProducao'

const listas = {
  categories: { 1: 'Banner' },
  brands: { 3: 'Marca A' },
  units: { 5: 'Metro (M)' },
  taxes: { 1: 'ICMS 18%' },
  taxAttributes: {},
  barcodeTypes: { C128: 'Code 128' },
  subCategories: {},
  businessLocations: { 1: 'Matriz' },
  rackDetails: null,
  productTypes: { single: 'Simples', variable: 'Variável', combo: 'Combo' },
  warranties: {},
}

const produto = {
  id: 9, name: 'Lona 440g', sku: 'LON-440', type: 'single', brandId: 3, unitId: 5,
  subUnitIds: null, categoryId: 1, subCategoryId: null, tax: 1, taxType: 'exclusive',
  origemMercadoria: 0, barcodeType: 'C128', enableStock: true, alertQuantity: null,
  weight: null, productDescription: null, productLocations: [1], image: null,
  warrantyId: null, camposPersonalizados: {}, expiryPeriod: null, expiryPeriodType: null,
}

function editar(producao: unknown) {
  return render(
    <ProdutoEdit
      {...(listas as never)}
      product={produto as never}
      subUnits={{}}
      permissions={{ opening_stock: false, delete: false }}
      producao={producao as never}
    />,
  )
}

describe('UC-PEDIT-18 · card Produção no Editar', () => {
  it('com roteiro: nome, número de etapas e "Ver o roteiro"', () => {
    editar({ roteiro: { nome: 'Impressão digital + acabamento', etapas: 4, ativo: true }, linkRoteiros: '/producao/roteiros' })
    const card = screen.getByTestId('bloco-producao')
    expect(card.textContent).toContain('Impressão digital + acabamento · 4 etapas')
    expect(card.textContent).toContain('A OS deste produto copia essas etapas')
    const link = screen.getByRole('link', { name: 'Ver o roteiro' })
    expect(link.getAttribute('href')).toBe('/producao/roteiros')
  })

  it('sem roteiro: diz que o produto não vira OS, não o texto do protótipo', () => {
    editar({ roteiro: null, linkRoteiros: '/producao/roteiros' })
    const card = screen.getByTestId('bloco-producao')
    expect(card.textContent).toContain('Sem roteiro: este produto não vira OS.')
    expect(card.textContent).toContain('Para gerar OS, ligue um roteiro em Produção → Roteiros.')
    expect(card.textContent).not.toContain('modelo da empresa')
    expect(screen.getByRole('link', { name: 'Abrir Roteiros' }).getAttribute('href')).toBe('/producao/roteiros')
  })

  it('sem `producao.access`: o card aparece sem o link', () => {
    editar({ roteiro: null, linkRoteiros: null })
    expect(screen.getByTestId('bloco-producao')).toBeTruthy()
    expect(screen.queryByRole('link', { name: 'Abrir Roteiros' })).toBeNull()
  })

  it('prop null (sem o módulo, composição, serviço) ou ainda não carregada: sem card', () => {
    const { unmount } = editar(null)
    expect(screen.queryByTestId('bloco-producao')).toBeNull()
    unmount()
    editar(undefined)
    expect(screen.queryByTestId('bloco-producao')).toBeNull()
  })

  it('roteiro inativo e roteiro de 1 etapa', () => {
    render(<BlocoProducao producao={{ roteiro: { nome: 'Corte', etapas: 1, ativo: false }, linkRoteiros: null }} />)
    const card = screen.getByTestId('bloco-producao')
    expect(card.textContent).toContain('Corte · 1 etapa')
    expect(card.textContent).toContain('roteiro inativo')
  })
})
