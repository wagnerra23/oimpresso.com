/**
 * Ponto/Aprovacoes/Index — forma do protótipo (thread 15 do playbook do Ponto).
 *
 * Não cita UC de propósito: forma não é contrato de comportamento (os UC-PAPR-* são do
 * servidor e do diálogo de lote). O que este arquivo trava é o que o ALVO mede.
 *
 * Alvo: governance/design/targets/ponto--aprovacoes--index.alvo.json — seção `kpis` são 6
 * botões sob `[data-contract="aprovacoes-kpis-estado"]`, e a barra tem Estado · Tipo · Prioridade.
 */
import { afterEach, describe, expect, it, vi } from 'vitest'
import { cleanup, render } from '@testing-library/react'

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Deferred: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  Link: ({ children }: { children: React.ReactNode }) => <a>{children}</a>,
  router: { delete: vi.fn(), get: vi.fn(), post: vi.fn() },
}))
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))
vi.mock('@/Pages/Ponto/_shared/PontoAreaHeader', () => ({ default: () => null }))

import { router } from '@inertiajs/react'
import AprovacoesIndex from '@/Pages/Ponto/Aprovacoes/Index'

afterEach(() => {
  cleanup()
  vi.mocked(router.get).mockReset()
})

const props = {
  aprovacoes: { data: [], total: 0, per_page: 20, current_page: 1, last_page: 1, links: [] },
  filtros: { estado: 'PENDENTE', tipo: null, prioridade: null },
  contagens: { PENDENTE: 2, APROVADA: 1 },
  tipos: [{ value: 'ATESTADO_MEDICO', label: 'Atestado médico' }],
  pode_recusar_mobile: false,
}

describe('Ponto/Aprovacoes · forma do protótipo (thread 15)', () => {
  it('os KPIs de estado chegam ao DOM com a âncora do alvo, como 6 KPI-filtro', () => {
    const { container } = render(<AprovacoesIndex {...(props as never)} />)
    const kpis = container.querySelector('[data-contract="aprovacoes-kpis-estado"]')
    expect(kpis).not.toBeNull()
    const botoes = kpis!.querySelectorAll(':scope > button')
    expect(botoes.length).toBe(6)
    botoes.forEach((b) => expect(b.getAttribute('data-variant')).toBe('filter'))
    // O estado ativo segue anunciado.
    expect(kpis!.querySelector('[aria-pressed="true"]')?.textContent).toContain('Pendente')
  })

  it('desligar o KPI ativo pede "Todos" com estado vazio explícito (o controller cairia em PENDENTE)', () => {
    const { container } = render(<AprovacoesIndex {...(props as never)} />)
    const ativo = container.querySelector('[data-contract="aprovacoes-kpis-estado"] [aria-pressed="true"]') as HTMLElement
    ativo.click()
    expect(router.get).toHaveBeenCalledTimes(1)
    const [url, params] = vi.mocked(router.get).mock.calls[0]
    expect(url).toBe('/ponto/aprovacoes')
    expect(params).toEqual({ estado: '' })
  })

  it('a barra tem os 3 filtros do protótipo, na ordem', () => {
    const { container } = render(<AprovacoesIndex {...(props as never)} />)
    const rotulos = [...container.querySelectorAll('[data-slot="page-filters"] label')].map((l) => l.textContent)
    expect(rotulos).toEqual(['Estado', 'Tipo', 'Prioridade'])
  })
})
