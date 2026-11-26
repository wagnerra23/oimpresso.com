// UC-PCAD-34 (Create.casos.md): SKU digitado repetido. Âncora: CU-PROD-01 item 2 do SDD ("SKU digitado
// → validado duplicado") e o Blade, que confere em `/products/check_product_sku` ao sair do campo. Aqui:
// o aviso ao sair do SKU, o id do produto no Editar, e a recusa do servidor embaixo do SKU.
import { useState } from 'react'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

vi.mock('@inertiajs/react', () => ({ router: { reload: vi.fn() } }))

import { BlocoDados } from '@/Pages/Produto/_components/formulario/BlocoDados'
import type { CamposProduto, ErrosProduto, OpcoesProduto } from '@/Pages/Produto/_components/formulario/tipos'

const opcoes: OpcoesProduto = {
  units: { 4: 'Unidade (UN)' },
  categories: {},
  brands: {},
  taxes: {},
  barcodeTypes: { C128: 'Code 128' },
  warranties: {},
  productTypes: { single: 'Simples' },
}

function Dados({ errors = {}, produtoId }: { errors?: ErrosProduto; produtoId?: number }) {
  const [data, setData] = useState({ name: '', sku: '', barcode_type: 'C128', unit_id: 4 } as unknown as CamposProduto)
  const midia = produtoId ? { produtoId, imagem: null, folheto: null } : undefined
  return (
    <BlocoDados
      modo={produtoId ? 'editar' : 'novo'}
      data={data}
      definir={(k, v) => setData((d) => ({ ...d, [k]: v }))}
      errors={errors}
      opcoes={opcoes}
      midia={midia}
    />
  )
}

let corpos: URLSearchParams[]
function responder(texto: string) {
  vi.stubGlobal(
    'fetch',
    vi.fn(async (_url: string, init: { body: URLSearchParams }) => {
      corpos.push(init.body)
      return { ok: true, text: async () => texto }
    }),
  )
}

beforeEach(() => {
  corpos = []
})
afterEach(() => vi.unstubAllGlobals())

describe('UC-PCAD-34 · SKU repetido', () => {
  it('ao sair do SKU que já existe na empresa, avisa embaixo do campo', async () => {
    responder('false')
    render(<Dados />)
    const sku = screen.getByLabelText('SKU')

    fireEvent.change(sku, { target: { value: 'CAM-001' } })
    fireEvent.blur(sku)

    expect(await screen.findByText('O SKU CAM-001 já existe em outro produto.')).toBeTruthy()
    expect(corpos[0].get('sku')).toBe('CAM-001')
    expect(corpos[0].get('product_id')).toBeNull()

    // Digitar de novo tira o aviso até a próxima conferência.
    fireEvent.change(sku, { target: { value: 'CAM-002' } })
    expect(screen.queryByText(/já existe em outro produto/)).toBeNull()
  })

  it('SKU livre não avisa, e SKU em branco nem pergunta', async () => {
    responder('true')
    render(<Dados />)
    const sku = screen.getByLabelText('SKU')

    fireEvent.blur(sku)
    expect(corpos).toHaveLength(0)

    fireEvent.change(sku, { target: { value: 'NOVO-1' } })
    fireEvent.blur(sku)
    await waitFor(() => expect(corpos).toHaveLength(1))
    expect(screen.queryByText(/já existe em outro produto/)).toBeNull()
  })

  it('no Editar manda o id do produto, para ele não contar como repetido', async () => {
    responder('true')
    render(<Dados produtoId={77} />)
    const sku = screen.getByLabelText('SKU')

    fireEvent.change(sku, { target: { value: 'CAM-001' } })
    fireEvent.blur(sku)

    await waitFor(() => expect(corpos).toHaveLength(1))
    expect(corpos[0].get('product_id')).toBe('77')
  })

  it('a recusa do servidor aparece embaixo do SKU', () => {
    render(<Dados errors={{ sku: 'O SKU CAM-001 já existe em outro produto.' }} />)

    expect(screen.getByText('O SKU CAM-001 já existe em outro produto.')).toBeTruthy()
    expect(screen.getByLabelText('SKU').getAttribute('aria-invalid')).toBe('true')
  })
})
