/**
 * Ponto/Mobile/Index (REP-P) — dois defeitos visuais achados no smoke em produção de 2026-09-29.
 *
 * 1. A nota "REP-P — o aparelho do colaborador" quebrava cada termo em negrito (GPS, relógio,
 *    geofence) numa linha: o AlertDescription é grid, e o texto estava solto dentro dele.
 * 2. A tela não tinha o container das outras telas do Ponto e ocupava a largura inteira da janela
 *    (header medido com 2300px de largura).
 */
import { afterEach, describe, expect, it, vi } from 'vitest'
import { cleanup, render, screen } from '@testing-library/react'

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a>,
  router: { reload: vi.fn(), visit: vi.fn() },
  usePage: () => ({ props: {} }),
}))
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div data-testid="shell">{children}</div>,
}))
vi.mock('@/Pages/Ponto/_shared/PontoAreaHeader', () => ({ default: () => null }))

import Mobile from '@/Pages/Ponto/Mobile/Index'

afterEach(() => cleanup())

const props = {
  colaborador: null,
  marcacoes_hoje: [],
  hoje: '2026-09-29',
  pode_ver_modulo: false,
  limites: { accuracy_max: 500, drift_max: 30 },
}

describe('REP-P · nota e container', () => {
  it('os termos em negrito da nota ficam dentro de um parágrafo (não viram linhas soltas do grid)', () => {
    render(<Mobile {...(props as never)} />)
    for (const termo of ['GPS', 'relógio', 'geofence']) {
      expect(screen.getByText(termo).parentElement?.tagName).toBe('P')
    }
  })

  it('a tela usa o container com largura máxima das outras telas do Ponto', () => {
    render(<Mobile {...(props as never)} />)
    const raiz = screen.getByTestId('shell').firstElementChild as HTMLElement
    expect(raiz.className).toContain('max-w-7xl')
    expect(raiz.className).toContain('mx-auto')
  })
})
