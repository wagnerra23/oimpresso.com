/**
 * Ponto/Aprovacoes/Index — aprovação em lote confirma no diálogo do DS.
 *
 * @covers-us UC-PAPR-05
 *
 * Ata Ponto 2026-09-14 (R3): `confirm` nativo não era pergunta — usa o diálogo do DS. O caso
 * cobre o que o gestor VÊ e pode clicar; o isolamento do lote no servidor é o UC-PAPR-02.
 */
import { afterEach, describe, expect, it, vi } from 'vitest'
import { cleanup, fireEvent, render, screen } from '@testing-library/react'

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Deferred: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  Link: ({ children }: { children: React.ReactNode }) => <a>{children}</a>,
  router: { delete: vi.fn(), get: vi.fn(), post: vi.fn() },
}))
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))
vi.mock('@/Pages/Ponto/_shared/PontoSubNav', () => ({ default: () => null }))
// Header de módulo (W9, ADR 0418): lê o shell via `usePage`; este teste é do CORPO da tela.
vi.mock('@/Pages/Ponto/_shared/PontoAreaHeader', () => ({ default: () => null }))

import { router } from '@inertiajs/react'
import AprovacoesIndex from '@/Pages/Ponto/Aprovacoes/Index'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
  vi.mocked(router.post).mockReset()
})

const item = (id: number, codigo: string) => ({
  id,
  codigo,
  tipo: 'ATESTADO',
  estado: 'PENDENTE',
  prioridade: 'NORMAL',
  data: '2026-09-01',
  dia_todo: true,
  intervalo_inicio: null,
  intervalo_fim: null,
  justificativa: 'x',
  impacta_apuracao: false,
  descontar_banco_horas: false,
  created_at_human: 'há 1 dia',
  created_at: '2026-09-01 10:00',
  colaborador: { id, matricula: null, nome: `Colaborador ${id}` },
  solicitante: { nome: 'Gestor' },
})

const props = {
  aprovacoes: {
    data: [item(11, 'INT-11'), item(12, 'INT-12')],
    total: 2,
    per_page: 20,
    current_page: 1,
    last_page: 1,
    links: [],
  },
  filtros: { estado: 'PENDENTE', tipo: null, prioridade: null },
  contagens: { PENDENTE: 2 },
  tipos: [{ value: 'ATESTADO', label: 'Atestado' }],
}

const selecionarAsDuas = () => {
  fireEvent.click(screen.getByLabelText('Selecionar INT-11'))
  fireEvent.click(screen.getByLabelText('Selecionar INT-12'))
}

const abrirDialogo = () => {
  fireEvent.click(screen.getByRole('button', { name: /Aprovar selecionadas/ }))
}

describe('UC-PAPR-05 · aprovação em lote confirma no diálogo do DS', () => {
  it('"Aprovar selecionadas" abre o diálogo com a contagem — sem confirm nativo e sem enviar', () => {
    const nativo = vi.spyOn(window, 'confirm')
    render(<AprovacoesIndex {...(props as never)} />)
    selecionarAsDuas()
    abrirDialogo()

    expect(screen.getByRole('alertdialog').textContent).toContain('2 intercorrências selecionadas')
    expect(screen.getByRole('button', { name: 'Aprovar 2' })).toBeTruthy()
    expect(nativo).not.toHaveBeenCalled()
    // Abrir o diálogo não aprova nada — só a confirmação envia o lote.
    expect(router.post).not.toHaveBeenCalled()
  })

  it('confirmar envia o lote com os ids selecionados', () => {
    render(<AprovacoesIndex {...(props as never)} />)
    selecionarAsDuas()
    abrirDialogo()
    fireEvent.click(screen.getByRole('button', { name: 'Aprovar 2' }))

    expect(router.post).toHaveBeenCalledTimes(1)
    const [url, dados] = vi.mocked(router.post).mock.calls[0]
    expect(url).toBe('/ponto/aprovacoes/lote')
    expect(dados).toEqual({ ids: [11, 12] })
  })

  it('enquanto processa, o botão de confirmar fica desabilitado e o diálogo não fecha', () => {
    render(<AprovacoesIndex {...(props as never)} />)
    selecionarAsDuas()
    abrirDialogo()
    fireEvent.click(screen.getByRole('button', { name: 'Aprovar 2' }))

    // O stub do router não chama onFinish: o estado fica "processando".
    const botao = screen.getByRole('button', { name: 'Aprovando…' }) as HTMLButtonElement
    expect(botao.disabled).toBe(true)
    expect(screen.getByRole('alertdialog')).toBeTruthy()
  })

  it('Cancelar fecha sem enviar', () => {
    render(<AprovacoesIndex {...(props as never)} />)
    selecionarAsDuas()
    abrirDialogo()
    fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(screen.queryByRole('alertdialog')).toBeNull()
    expect(router.post).not.toHaveBeenCalled()
  })
})
