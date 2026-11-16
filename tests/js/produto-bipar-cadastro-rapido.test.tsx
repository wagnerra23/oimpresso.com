// UC-PCAD-28 (Create.casos.md) · UC-PEDIT-28 (Edit.casos.md): "Bipar" no SKU e "+" de unidade e de marca
// no cadastro de produto. Âncora: protótipo do [W] (produto-blade-forms.jsx: botão Bipar ao lado do SKU,
// "+" que abre o cadastro rápido em modal), Blade (create.blade.php: "+" desabilitado sem unit.create /
// brand.create) e pedido do [F] em 2026-11-16.
import { useState } from 'react'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

const recarregar = vi.fn()
vi.mock('@inertiajs/react', () => ({ router: { reload: (o: unknown) => recarregar(o) } }))

import { BlocoDados } from '@/Pages/Produto/_components/formulario/BlocoDados'
import type { CamposProduto, OpcoesProduto } from '@/Pages/Produto/_components/formulario/tipos'

const opcoesBase: OpcoesProduto = {
  units: { 4: 'Unidade (UN)' },
  categories: {},
  brands: { 2: 'Genérica' },
  taxes: {},
  barcodeTypes: { C128: 'Code 128' },
  warranties: {},
  productTypes: { single: 'Simples' },
}

let estado: CamposProduto
function Dados({ opcoes }: { opcoes: OpcoesProduto }) {
  const [data, setData] = useState({ name: '', sku: '', barcode_type: 'C128', unit_id: 4, brand_id: 2 } as unknown as CamposProduto)
  estado = data
  // Dentro de <form>, como na tela: só aí o Select do Radix cria o <select> nativo escondido, que é o
  // que devolvia '' quando a unidade/marca nova ainda não estava na lista.
  return (
    <form>
      <BlocoDados modo="novo" data={data} definir={(k, v) => setData((d) => ({ ...d, [k]: v }))} errors={{}} opcoes={opcoes} />
    </form>
  )
}

let chamadas: Array<{ url: string; corpo: Record<string, unknown> }>
function responder(resposta: unknown, ok = true) {
  vi.stubGlobal(
    'fetch',
    vi.fn(async (url: string, init: { body: string }) => {
      chamadas.push({ url, corpo: JSON.parse(init.body) })
      return { ok, json: async () => resposta }
    }),
  )
}

beforeEach(() => {
  chamadas = []
  recarregar.mockClear()
})
afterEach(() => vi.unstubAllGlobals())

describe('UC-PCAD-28 · Bipar', () => {
  it('põe o cursor no SKU e avisa que o leitor está pronto', () => {
    render(<Dados opcoes={opcoesBase} />)

    fireEvent.click(screen.getByRole('button', { name: 'Bipar' }))
    expect(document.activeElement?.id).toBe('sku')
    expect(screen.getByRole('status').textContent).toMatch(/Leitor pronto/)
  })
})

describe('UC-PCAD-28 · "+" de unidade e de marca', () => {
  it('sem a permissão de cadastrar, o "+" fica desabilitado', () => {
    render(<Dados opcoes={opcoesBase} />)

    expect((screen.getByRole('button', { name: 'Cadastrar unidade' }) as HTMLButtonElement).disabled).toBe(true)
    expect((screen.getByRole('button', { name: 'Cadastrar marca' }) as HTMLButtonElement).disabled).toBe(true)
  })

  it('cadastra a unidade, recarrega só a lista de unidades e já escolhe a nova no produto', async () => {
    responder({ success: true, data: { id: 31 } })
    render(<Dados opcoes={{ ...opcoesBase, podeCriarUnidade: true, podeCriarMarca: true }} />)

    fireEvent.click(screen.getByRole('button', { name: 'Cadastrar unidade' }))
    fireEvent.change(screen.getByLabelText('Nome *'), { target: { value: 'Caixa' } })
    fireEvent.change(screen.getByLabelText('Abreviação *'), { target: { value: 'CX' } })
    fireEvent.click(screen.getByRole('checkbox', { name: /casas decimais/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Gravar' }))

    await waitFor(() => expect(estado.unit_id).toBe(31))
    // A unidade nova ainda não está na lista (a recarga é assíncrona): a escolha tem de ficar, não só
    // passar pelo campo. Teste no Chrome 2026-11-16: o Select devolvia '' e a unidade virava 0.
    await new Promise((r) => setTimeout(r, 50))
    expect(estado.unit_id).toBe(31)
    expect(chamadas).toEqual([{ url: '/units', corpo: { actual_name: 'Caixa', short_name: 'CX', allow_decimal: 1 } }])
    expect(recarregar).toHaveBeenCalledWith({ only: ['units'] })
  })

  it('cadastra a marca e já escolhe a nova no produto', async () => {
    responder({ success: true, data: { id: 7 } })
    render(<Dados opcoes={{ ...opcoesBase, podeCriarUnidade: true, podeCriarMarca: true }} />)

    fireEvent.click(screen.getByRole('button', { name: 'Cadastrar marca' }))
    fireEvent.change(screen.getByLabelText('Nome *'), { target: { value: '3M' } })
    fireEvent.click(screen.getByRole('button', { name: 'Gravar' }))

    await waitFor(() => expect(estado.brand_id).toBe(7))
    await new Promise((r) => setTimeout(r, 50))
    expect(estado.brand_id).toBe(7)
    expect(chamadas).toEqual([{ url: '/brands', corpo: { name: '3M', description: '' } }])
    expect(recarregar).toHaveBeenCalledWith({ only: ['brands'] })
  })

  it('sem nome não grava; com recusa do servidor mostra a mensagem e não troca a marca', async () => {
    responder({ success: false, msg: 'Algo deu errado.' })
    render(<Dados opcoes={{ ...opcoesBase, podeCriarUnidade: true, podeCriarMarca: true }} />)

    fireEvent.click(screen.getByRole('button', { name: 'Cadastrar marca' }))
    expect((screen.getByRole('button', { name: 'Gravar' }) as HTMLButtonElement).disabled).toBe(true)
    fireEvent.change(screen.getByLabelText('Nome *'), { target: { value: '3M' } })
    fireEvent.click(screen.getByRole('button', { name: 'Gravar' }))

    expect((await screen.findByRole('alert')).textContent).toBe('Algo deu errado.')
    expect(estado.brand_id).toBe(2)
    expect(recarregar).not.toHaveBeenCalled()
  })
})
