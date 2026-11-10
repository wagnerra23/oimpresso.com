/**
 * Vestuario/Etiquetas — o operador acha a peça pelo nome ou SKU, sem digitar id.
 *
 * @covers-us UC-VET-24
 *
 * O caso é de tela: o Pest prova o que o servidor devolve, só o render prova que a linha do lote
 * nasce com o sub_sku certo e que o preço continua sendo o que o operador digita.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'

vi.mock('@inertiajs/react', () => ({ Head: () => null }))
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))
vi.mock('@/Components/PageHeader', () => ({ PageHeader: () => null }))

import EtiquetasIndex from '@/Pages/Vestuario/Etiquetas/Index'

const config = { width_dots: 400, height_dots: 240, dpi: 203, margin_dots: 10, qr_enabled: false }
const props = { config: { ...config, max_etiquetas: 500 } } as never
const achado = {
  product_id: 42, variation_id: 7, name: 'Camiseta Básica', type: 'variable', variation: 'M',
  sub_sku: 'CAMI-042-M-AZU', selling_price: 99.9,
}

const fetchMock = vi.fn()

beforeEach(() => {
  fetchMock.mockReset()
  fetchMock.mockImplementation(async (url: string) => {
    if (String(url).startsWith('/products/list')) {
      return new Response(JSON.stringify([achado]), { status: 200 })
    }
    return new Response('^XA^XZ', { status: 200 })
  })
  vi.stubGlobal('fetch', fetchMock)
  URL.createObjectURL = vi.fn(() => 'blob:x')
  URL.revokeObjectURL = vi.fn()
})

afterEach(() => vi.unstubAllGlobals())

async function buscarEEscolher() {
  render(<EtiquetasIndex {...props} />)
  fireEvent.change(screen.getByLabelText('Buscar produto'), { target: { value: 'cami' } })
  fireEvent.click(await screen.findByText(/CAMI-042-M-AZU/))
}

describe('UC-VET-24 · busca por nome ou SKU', () => {
  it('não pede mais o id do produto', () => {
    render(<EtiquetasIndex {...props} />)

    expect(screen.queryByText('Produto ID')).toBeNull()
    expect(screen.getByLabelText('Buscar produto')).toBeTruthy()
  })

  it('escolher a variação preenche a linha com nome, tamanho e sub_sku, e não o preço', async () => {
    await buscarEEscolher()

    expect((screen.getByLabelText('Nome') as HTMLInputElement).value).toBe('Camiseta Básica')
    expect((screen.getByLabelText('Tam') as HTMLInputElement).value).toBe('M')
    expect((screen.getByLabelText('SKU') as HTMLInputElement).value).toBe('CAMI-042-M-AZU')
    expect((screen.getByLabelText('Preço') as HTMLInputElement).value).toBe('0')
    expect(String(fetchMock.mock.calls[0][0])).toContain('term=cami')
  })

  it('a etiqueta pedida leva os ids e o sub_sku da variação escolhida', async () => {
    await buscarEEscolher()
    fireEvent.click(screen.getByRole('button', { name: /Baixar ZPL/ }))

    await waitFor(() => expect(fetchMock.mock.calls.some(([u]) => String(u).includes('/lote/zpl'))).toBe(true))
    const [, init] = fetchMock.mock.calls.find(([u]) => String(u).includes('/lote/zpl'))!
    expect(JSON.parse(init.body).items[0]).toMatchObject({
      product_id: 42, variation_id: 7, sku: 'CAMI-042-M-AZU',
    })
  })

  it('a mesma variação não entra duas vezes', async () => {
    await buscarEEscolher()
    fireEvent.change(screen.getByLabelText('Buscar produto'), { target: { value: 'cami' } })
    fireEvent.click(await screen.findByText(/CAMI-042-M-AZU/))

    expect(screen.getAllByLabelText('SKU')).toHaveLength(1)
  })
})
