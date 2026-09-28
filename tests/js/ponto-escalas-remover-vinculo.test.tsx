/**
 * Ponto/Escalas/Index — "Remover" entra na UI, mas INDISPONÍVEL com vínculo.
 *
 * @covers-us UC-ESCIDX-03
 * @covers-us UC-ESCIDX-05
 *
 * `D-ESC-DESTROY` ([W] 2026-09-14). O caso vive aqui porque é sobre o que o gestor VÊ e pode
 * clicar; a trava de verdade é servidor (`EscalaController@destroy` → `Escala::podeSerRemovida`)
 * e tem caso próprio — o botão é conveniência, a rota é pública.
 *
 * ⚠️ O ASSERT COBRE AS DUAS PONTAS, e a segunda é a que pega o defeito real: não basta que o
 * botão desapareça com vínculo — ele tem que APARECER sem vínculo. Um `return null` na célula
 * inteira passaria num teste que só checasse a ausência, e aí a feature simplesmente não existe.
 *
 * E o motivo é TEXTO ao lado, não tooltip: botão desabilitado não recebe foco, então o Tooltip
 * do DS seria inalcançável por teclado — [W] pediu "com o motivo escrito".
 */
import { afterEach, describe, expect, it, vi } from 'vitest'
import { cleanup, fireEvent, render, screen } from '@testing-library/react'

// `vi.mock` é HOISTADO acima das declarações do módulo, então o factory não pode fechar sobre um
// `const` daqui — a 1ª versão fazia isso e morria em "Cannot access 'routerDelete' before
// initialization". O stub nasce dentro do factory; quem asserta a chamada usa `vi.mocked(router)`.
vi.mock('@inertiajs/react', () => ({
  Head: () => null,
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
import EscalasIndex from '@/Pages/Ponto/Escalas/Index'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
  vi.mocked(router.delete).mockReset()
})

const escala = (id: number, nome: string, colaboradores_count: number) => ({
  id,
  nome,
  codigo: `ESC-${id}`,
  tipo: 'FIXA',
  carga_diaria_minutos: 480,
  carga_semanal_minutos: 2640,
  permite_banco_horas: false,
  turnos_count: 1,
  colaboradores_count,
})

const props = (linhas: ReturnType<typeof escala>[]) => ({
  escalas: { data: linhas, links: [], current_page: 1, last_page: 1, total: linhas.length, per_page: 20 },
})

describe('UC-ESCIDX-03 · remover escala é indisponível com vínculo', () => {
  it('sem vínculo: oferece "Remover" — a ponta positiva, senão a feature não existe', () => {
    render(<EscalasIndex {...(props([escala(1, 'Comercial 44h', 0)]) as never)} />)
    expect(screen.getByRole('button', { name: 'Remover' })).toBeTruthy()
  })

  it('com vínculo: NÃO oferece "Remover", e diz por quê — com a contagem', () => {
    render(<EscalasIndex {...(props([escala(2, 'Turno noturno', 3)]) as never)} />)

    expect(screen.queryByRole('button', { name: 'Remover' })).toBeNull()
    // O motivo é texto, não tooltip (botão desabilitado não recebe foco).
    expect(screen.getByText('Em uso por 3 colaboradores')).toBeTruthy()
  })

  it('concorda em número: 1 vínculo fala no singular', () => {
    // Plural cravado ("1 colaboradores") é o defeito mais barato de cometer e o mais visível
    // pro gestor — e o motivo só serve se ele for legível.
    render(<EscalasIndex {...(props([escala(3, 'Meio período', 1)]) as never)} />)
    expect(screen.getByText('Em uso por 1 colaborador')).toBeTruthy()
  })

  it('decide POR LINHA, não pela primeira', () => {
    // Controle contra aplicar a condição fora do `map`: com uma escala livre e uma em uso, a tela
    // tem que mostrar exatamente um "Remover" — nem zero, nem dois.
    render(
      <EscalasIndex
        {...(props([escala(1, 'Comercial 44h', 0), escala(2, 'Turno noturno', 3)]) as never)}
      />,
    )
    expect(screen.getAllByRole('button', { name: 'Remover' })).toHaveLength(1)
    expect(screen.getByText('Em uso por 3 colaboradores')).toBeTruthy()
  })
})

describe('UC-ESCIDX-05 · remover confirma no diálogo do DS e remove de fato', () => {
  // A ata D-ESC-DESTROY registra que a 1ª versão do protótipo abriu um modal SEM botão de ação:
  // o ramo sem vínculo nunca tinha rodado. Estes casos exercitam esse ramo até a chamada que remove.
  const abrir = () => {
    render(<EscalasIndex {...(props([escala(7, 'Comercial 44h', 0)]) as never)} />)
    fireEvent.click(screen.getByRole('button', { name: 'Remover' }))
  }

  it('"Remover" abre o diálogo com Cancelar e "Remover escala", sem window.confirm', () => {
    const nativo = vi.spyOn(window, 'confirm')
    abrir()

    expect(screen.getByRole('alertdialog').textContent).toContain('Remover Comercial 44h?')
    expect(screen.getByRole('button', { name: 'Cancelar' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Remover escala' })).toBeTruthy()
    expect(nativo).not.toHaveBeenCalled()
    // Abrir o diálogo não remove nada; só a confirmação remove.
    expect(router.delete).not.toHaveBeenCalled()
  })

  it('confirmar dispara o DELETE da escala certa', () => {
    abrir()
    fireEvent.click(screen.getByRole('button', { name: 'Remover escala' }))

    expect(router.delete).toHaveBeenCalledTimes(1)
    expect(vi.mocked(router.delete).mock.calls[0][0]).toBe('/ponto/escalas/7')
  })

  it('enquanto processa, o botão de confirmar fica desabilitado e o diálogo não fecha', () => {
    abrir()
    fireEvent.click(screen.getByRole('button', { name: 'Remover escala' }))

    // O stub do router não chama onFinish: o estado fica "processando".
    const botao = screen.getByRole('button', { name: 'Removendo…' }) as HTMLButtonElement
    expect(botao.disabled).toBe(true)
    expect(screen.getByRole('alertdialog')).toBeTruthy()
  })

  it('Cancelar fecha sem remover', () => {
    abrir()
    fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(screen.queryByRole('alertdialog')).toBeNull()
    expect(router.delete).not.toHaveBeenCalled()
  })
})
