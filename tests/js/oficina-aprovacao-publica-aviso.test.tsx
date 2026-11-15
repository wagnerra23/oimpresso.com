/**
 * OficinaAuto/AprovacaoPublica — aviso da tela pública (UC-OAP-11 e UC-OAP-12).
 *
 * O `flash` compartilhado sempre vem como objeto com as chaves nulas, então testar só
 * "existe flash" desenhava uma faixa vazia em toda abertura do link.
 */
import { afterEach, describe, expect, it, vi } from 'vitest'
import { cleanup, render, screen } from '@testing-library/react'

let flash: Record<string, string | null> = { success: null, error: null, info: null }

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  usePage: () => ({ props: { flash } }),
  useForm: () => ({
    data: { pin: '', decisao: '' },
    setData: vi.fn(),
    transform: vi.fn(),
    post: vi.fn(),
    processing: false,
    errors: {},
    reset: vi.fn(),
  }),
}))

import AprovacaoPublica from '@/Pages/OficinaAuto/AprovacaoPublica'

afterEach(() => {
  cleanup()
  flash = { success: null, error: null, info: null }
})

const os = {
  id: 7,
  numero: '7',
  order_type: 'mecanica',
  status: 'orcamento',
  entered_at: '2026-06-09',
  expected_completion: '2026-06-10',
  vehicle: { plate: 'TEST-001' },
}

const base = { erro: null, mensagem: null, token: 't', os, orcamento: null, tentativasRestantes: 5 }

describe('AprovacaoPublica · aviso', () => {
  it('UC-OAP-11 · sem mensagem não desenha a faixa', () => {
    const { container } = render(<AprovacaoPublica {...(base as never)} />)
    expect(container.querySelector('.bg-blue-50')).toBeNull()
  })

  it('UC-OAP-11 · PIN errado aparece na tela', () => {
    flash = { success: null, error: 'PIN inválido. Você tem 4 tentativa(s) restante(s).', info: null }
    render(<AprovacaoPublica {...(base as never)} />)
    expect(screen.getByText(/PIN inválido/)).toBeTruthy()
  })

  it('UC-OAP-12 · aprovou: mostra a confirmação em vez de link inválido', () => {
    flash = { success: 'Aprovação registrada!', error: null, info: null }
    render(<AprovacaoPublica {...(base as never)} erro="link_invalido" os={null} />)
    expect(screen.getByText('Aprovação registrada!')).toBeTruthy()
    expect(screen.queryByText('Link inválido')).toBeNull()
  })

  it('UC-OAP-11 · link realmente inválido continua dizendo isso', () => {
    render(<AprovacaoPublica {...(base as never)} erro="link_invalido" os={null} />)
    expect(screen.getByText('Link inválido')).toBeTruthy()
  })
})
