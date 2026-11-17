/**
 * ProductCatalogue/CatalogueQr — "Copiar link" diz a verdade (UC-CQR-12).
 *
 * A escrita na área de transferência é assíncrona e pode ser recusada. Os casos abaixo
 * só ficam verdes se a tela esperar a resposta do navegador antes de dizer "Link copiado".
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, cleanup, fireEvent, render, screen } from '@testing-library/react'
import type { ReactNode } from 'react'

vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: ReactNode }) => <>{children}</> }))
vi.mock('@/Components/PageHeader', () => ({ PageHeader: () => null }))
vi.mock('@/Components/ui/select', () => ({
  Select: ({ value, onValueChange, children }: { value: string; onValueChange: (v: string) => void; children: ReactNode }) => (
    <select aria-label="Local comercial" value={value} onChange={(e) => onValueChange(e.target.value)}>
      <option value="">Selecione</option>
      {children}
    </select>
  ),
  SelectTrigger: () => null,
  SelectValue: () => null,
  SelectContent: ({ children }: { children: ReactNode }) => <>{children}</>,
  SelectItem: ({ value, children }: { value: string; children: ReactNode }) => <option value={value}>{children}</option>,
}))
vi.mock('@/Components/ui/SafeSelectItem', () => ({
  SafeSelectItem: ({ value, children }: { value: string; children: ReactNode }) => <option value={value}>{children}</option>,
}))

import CatalogueQr from '@/Pages/ProductCatalogue/CatalogueQr'

const props = {
  locais: [{ id: 7, nome: 'Loja Centro' }],
  negocio: { nome: 'Negócio Teste', logo_url: null },
  link_base: 'https://example.test/catalogue/98',
  qr_script: '/qr.js',
}
const LINK = 'https://example.test/catalogue/98/7'
const AVISO = /Não foi possível copiar o link/

function definirClipboard(valor: unknown) {
  Object.defineProperty(navigator, 'clipboard', { value: valor, configurable: true })
}

async function gerarQr() {
  render(<CatalogueQr {...props} />)
  fireEvent.change(screen.getByLabelText('Local comercial'), { target: { value: '7' } })
  await act(async () => {
    fireEvent.click(screen.getByRole('button', { name: /Gerar QR code/ }))
  })
}

async function clicarCopiar() {
  await act(async () => {
    fireEvent.click(screen.getByRole('button', { name: 'Copiar link' }))
  })
}

beforeEach(() => {
  ;(window as unknown as { QRCode: unknown }).QRCode = class {}
})

afterEach(() => {
  cleanup()
  definirClipboard(undefined)
})

describe('CatalogueQr · copiar link', () => {
  it('UC-CQR-12 · copia o mesmo link do QR e volta ao rótulo depois de 1,6 s', async () => {
    vi.useFakeTimers()
    const writeText = vi.fn().mockResolvedValue(undefined)
    definirClipboard({ writeText })
    await gerarQr()

    await clicarCopiar()

    expect(writeText).toHaveBeenCalledWith(LINK)
    expect(screen.getByRole('button', { name: 'Link copiado' })).toBeTruthy()
    expect(screen.queryByText(AVISO)).toBeNull()

    act(() => { vi.advanceTimersByTime(1600) })
    expect(screen.getByRole('button', { name: 'Copiar link' })).toBeTruthy()
  })

  it('UC-CQR-12 · enquanto o navegador não responde, não diz que copiou', async () => {
    let confirmar: () => void = () => {}
    definirClipboard({ writeText: () => new Promise<void>((resolve) => { confirmar = resolve }) })
    await gerarQr()

    await clicarCopiar()
    expect(screen.queryByRole('button', { name: 'Link copiado' })).toBeNull()

    await act(async () => { confirmar() })
    expect(screen.getByRole('button', { name: 'Link copiado' })).toBeTruthy()
  })

  it('UC-CQR-12 · navegador recusa: mostra o aviso e não diz que copiou', async () => {
    definirClipboard({ writeText: vi.fn().mockRejectedValue(new Error('negado')) })
    await gerarQr()

    await clicarCopiar()

    expect(screen.queryByRole('button', { name: 'Link copiado' })).toBeNull()
    expect(screen.getByText(AVISO).closest('[role="alert"]')).toBeTruthy()
  })

  it('UC-CQR-12 · sem área de transferência (http): mostra o aviso', async () => {
    definirClipboard(undefined)
    await gerarQr()

    await clicarCopiar()

    expect(screen.queryByRole('button', { name: 'Link copiado' })).toBeNull()
    expect(screen.getByText(AVISO).closest('[role="alert"]')).toBeTruthy()
  })
})
