/**
 * ProductCatalogue/CatalogueQr — o que a tela diz a quem não vê (UC-CQR-18 a 20).
 *
 * Scorecard da tela (2026-11-12): o QR gerado e o "Link copiado" mudavam em silêncio, os
 * swatches eram anunciados pelo valor rgb e o botão "Gerar" não dava sinal enquanto o
 * script do gerador baixava. Cada caso abaixo nasceu vermelho contra a tela antiga.
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
type Janela = { QRCode?: unknown }

function escolherLocal() {
  fireEvent.change(screen.getByLabelText('Local comercial'), { target: { value: '7' } })
}

async function clicarGerar() {
  await act(async () => {
    fireEvent.click(screen.getByRole('button', { name: /Gerar QR code/ }))
  })
}

beforeEach(() => {
  ;(window as unknown as Janela).QRCode = class {}
})

afterEach(() => {
  cleanup()
  delete (window as unknown as Janela).QRCode
  document.head.querySelectorAll('script[src="/qr.js"]').forEach((s) => s.remove())
  Object.defineProperty(navigator, 'clipboard', { value: undefined, configurable: true })
})

describe('CatalogueQr · anúncio e nomes', () => {
  it('UC-CQR-18 · cada cor é anunciada pelo nome, e a escolhida está marcada', () => {
    render(<CatalogueQr {...props} />)

    const nomes = ['preto', 'roxo', 'azul', 'rosa'].map((n) => `Cor do QR: ${n}`)
    for (const nome of nomes) expect(screen.getByRole('button', { name: nome })).toBeTruthy()
    expect(screen.queryByRole('button', { name: /rgb\(/ })).toBeNull()
    expect(screen.getByRole('button', { name: 'Cor do QR: roxo' }).getAttribute('aria-pressed')).toBe('true')
  })

  it('UC-CQR-19 · gerar e copiar são anunciados numa região que já existia', async () => {
    Object.defineProperty(navigator, 'clipboard', {
      value: { writeText: vi.fn().mockResolvedValue(undefined) },
      configurable: true,
    })
    render(<CatalogueQr {...props} />)
    const status = screen.getByRole('status')
    expect(status.textContent).toBe('')

    escolherLocal()
    await clicarGerar()
    expect(status.textContent).toBe('QR code gerado para Loja Centro.')

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Copiar link' }))
    })
    expect(status.textContent).toBe('Link copiado.')

    fireEvent.change(screen.getByLabelText('Título'), { target: { value: 'Outro' } })
    expect(status.textContent).toBe('')
  })

  it('UC-CQR-20 · enquanto o gerador baixa, o botão diz que está gerando e não aceita outro clique', async () => {
    delete (window as unknown as Janela).QRCode
    render(<CatalogueQr {...props} />)
    escolherLocal()

    await clicarGerar()

    const botao = screen.getByRole('button', { name: /Gerando QR code/ })
    expect((botao as HTMLButtonElement).disabled).toBe(true)
    expect(botao.getAttribute('aria-busy')).toBe('true')
    expect(screen.getByRole('status').textContent).toBe('Gerando QR code…')

    const script = document.head.querySelector('script[src="/qr.js"]') as HTMLScriptElement
    ;(window as unknown as Janela).QRCode = class {}
    await act(async () => { script.dispatchEvent(new Event('load')) })

    expect(screen.getByRole('button', { name: /Gerar QR code/ })).toBeTruthy()
    expect(screen.getByRole('status').textContent).toBe('QR code gerado para Loja Centro.')
  })
})
