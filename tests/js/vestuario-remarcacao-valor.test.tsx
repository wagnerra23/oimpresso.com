/**
 * Vestuario/Remarcacao/Index: o número que sai da tela é o número que a pessoa digitou.
 *
 * @covers-us UC-VREM-08
 *
 * O Pest prova a conta do servidor, mas o servidor recebe um número pronto: `-25` e `-2.5` passam
 * os dois na validação (`between:-90,300`). Só o render prova o que a tela manda no POST.
 * Os valores são fictícios.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Deferred: ({ children }: { children: React.ReactNode }) => children,
}))
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

import RemarcacaoIndex from '@/Pages/Vestuario/Remarcacao/Index'

const PREVIA = {
  linhas: [{ variation_id: 1, produto: 'Camisa', variacao: 'M', sku: 'CAM-M', preco_antes: 100, preco_depois: 97.5 }],
  ignoradas: 0,
  assinatura: 'a'.repeat(64),
}

const fetchMock = vi.fn()

function corpoDaChamada(n: number) {
  return JSON.parse(fetchMock.mock.calls[n][1].body as string)
}

function abrirTela(modo: 'percentual' | 'valor') {
  render(<RemarcacaoIndex categorias={[]} marcas={[]} lotes={[]} />)
  if (modo === 'valor') fireEvent.click(screen.getByRole('radio', { name: 'Valor fixo' }))
}

function digitar(texto: string) {
  fireEvent.change(document.getElementById('rem-valor') as HTMLInputElement, { target: { value: texto } })
}

beforeEach(() => {
  fetchMock.mockReset()
  fetchMock.mockImplementation(async (url: string) => ({
    ok: true,
    status: 200,
    json: async () => (url.endsWith('/previa') ? PREVIA : { lote: 7 }),
  }))
  vi.stubGlobal('fetch', fetchMock)
})

afterEach(() => vi.unstubAllGlobals())

describe('UC-VREM-08 · o valor digitado chega ao servidor como foi digitado', () => {
  it.each([
    ['percentual', '-2.5', -2.5],
    ['percentual', '12.5', 12.5],
    ['percentual', '-2,5', -2.5],
    ['percentual', '-30', -30],
    ['valor', '39.90', 39.9],
    ['valor', '80.00', 80],
    ['valor', '39,90', 39.9],
    ['valor', '1.234,56', 1234.56],
  ] as const)('UC-VREM-08 · %s "%s" vai na prévia como %s', async (modo, texto, esperado) => {
    abrirTela(modo)
    digitar(texto)
    fireEvent.click(screen.getByRole('button', { name: 'Ver prévia' }))

    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1))
    expect(fetchMock.mock.calls[0][0]).toBe('/vestuario/remarcacao/previa')
    expect(corpoDaChamada(0).valor).toBe(esperado)
    expect(corpoDaChamada(0).modo).toBe(modo)
  })

  it('UC-VREM-08 · a gravação manda o mesmo número da prévia', async () => {
    abrirTela('valor')
    digitar('39.90')
    fireEvent.click(screen.getByRole('button', { name: 'Ver prévia' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Gravar remarcação' }))
    fireEvent.change(await screen.findByLabelText('Nome da remarcação'), { target: { value: 'Teste' } })
    fireEvent.click(screen.getByRole('button', { name: 'Gravar' }))

    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2))
    expect(fetchMock.mock.calls[1][0]).toBe('/vestuario/remarcacao')
    expect(corpoDaChamada(1).valor).toBe(39.9)
    expect(corpoDaChamada(1).valor).toBe(corpoDaChamada(0).valor)
  })

  it.each(['abc', '-', '.'])('UC-VREM-08 · texto "%s" não vira número: nada vai ao servidor e a tela avisa', async (texto) => {
    abrirTela('percentual')
    digitar(texto)
    fireEvent.click(screen.getByRole('button', { name: 'Ver prévia' }))

    expect((await screen.findByRole('alert')).textContent).toContain('Valor inválido')
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('UC-VREM-08 · valor apagado depois da prévia não abre a confirmação nem grava', async () => {
    abrirTela('valor')
    digitar('39,90')
    fireEvent.click(screen.getByRole('button', { name: 'Ver prévia' }))
    const gravar = await screen.findByRole('button', { name: 'Gravar remarcação' })
    digitar('abc')
    fireEvent.click(gravar)

    expect((await screen.findByRole('alert')).textContent).toContain('Valor inválido')
    expect(screen.queryByLabelText('Nome da remarcação')).toBeNull()
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })
})
