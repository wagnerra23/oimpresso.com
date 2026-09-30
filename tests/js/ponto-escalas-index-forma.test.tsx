/**
 * Ponto/Escalas/Index — a FORMA do protótipo (`ponto-telas.jsx`, símbolo `Escalas`).
 *
 * @covers-us UC-ESCIDX-06
 *
 * O que é contrato aqui é o que o gestor LÊ na linha: o horário do 1º turno sob o nome, ou
 * "sem turno configurado" — a casca de escala que a apuração não consegue usar. O valor vem do
 * servidor (`primeiro_turno`, provado em `EscalaIndexContratoTest` UC-ESCIDX-06); este caso
 * prova que a tela mostra o que recebeu e não inventa um horário.
 *
 * As seções da tela (barra · lista · nota) seguem o ALVO medido em
 * governance/design/targets/ponto--escalas--index.secoes.json — a conferência delas é do
 * `secao-check`, não deste teste.
 */
import { afterEach, describe, expect, it, vi } from 'vitest'
import { cleanup, render, screen } from '@testing-library/react'

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a>,
  router: { delete: vi.fn(), get: vi.fn(), post: vi.fn() },
}))
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))
vi.mock('@/Pages/Ponto/_shared/PontoSubNav', () => ({ default: () => null }))
// Header de módulo (W9, ADR 0418): lê o shell via `usePage`; este teste é do CORPO da tela.
vi.mock('@/Pages/Ponto/_shared/PontoAreaHeader', () => ({ default: () => null }))

import EscalasIndex from '@/Pages/Ponto/Escalas/Index'

afterEach(() => cleanup())

const escala = (id: number, primeiro_turno: string | null) => ({
  id,
  nome: `Escala ${id}`,
  codigo: `ESC-${id}`,
  tipo: 'ESCALA_12X36',
  carga_diaria_minutos: 720,
  carga_semanal_minutos: 2160,
  permite_banco_horas: true,
  turnos_count: primeiro_turno ? 1 : 0,
  primeiro_turno,
  colaboradores_count: 0,
})

const props = (linhas: ReturnType<typeof escala>[]) => ({
  escalas: { data: linhas, links: [], from: 1, to: linhas.length, current_page: 1, last_page: 1, total: linhas.length },
})

describe('UC-ESCIDX-06 · a linha mostra o horário do 1º turno, ou que não há turno', () => {
  it('mostra o horário que o servidor mandou, POR LINHA', () => {
    render(<EscalasIndex {...(props([escala(1, '07:00–19:00'), escala(2, null)]) as never)} />)

    expect(screen.getByTestId('escala-1-turno').textContent).toBe('07:00–19:00')
    // A casca não herda o horário da vizinha: diz que não tem turno.
    expect(screen.getByTestId('escala-2-turno').textContent).toBe('sem turno configurado')
  })

  it('traz a forma do protótipo: tipo por rótulo, BH "Permite", contagem no título', () => {
    render(<EscalasIndex {...(props([escala(1, '07:00–19:00')]) as never)} />)

    // Rótulo do enum, não o valor cru (`ESCALA_12X36` virava "ESCALA 12X36").
    expect(screen.getByText('12x36')).toBeTruthy()
    expect(screen.getByText('Permite')).toBeTruthy()
    expect(screen.getByRole('heading', { level: 2, name: /Escalas cadastradas/ })).toBeTruthy()
    expect(screen.getByText('(1 no business)')).toBeTruthy()
    // "Nova escala" mora na barra, com a rota própria (D-PONTO-DETALHE).
    expect(screen.getByRole('link', { name: /Nova escala/ }).getAttribute('href')).toBe('/ponto/escalas/create')
  })
})

// Pílulas SÓLIDAS, como o protótipo desenha (medido no espelho em 2026-09-29): fundo no tom,
// texto branco, sem dot. A tela antes usava o tom `-soft` com dot do Badge padrão do DS.
describe('pílulas do tipo e do banco de horas seguem a forma do protótipo', () => {
  const pilula = (texto: string) => screen.getByText(texto).closest('[data-slot="badge"]') as HTMLElement

  it('tipo e "Permite" são sólidos (bg-info / bg-success, texto branco), sem dot', () => {
    render(<EscalasIndex {...(props([escala(1, '07:00–19:00')]) as never)} />)

    for (const [texto, fundo] of [['12x36', 'bg-info'], ['Permite', 'bg-success']] as const) {
      const el = pilula(texto)
      expect(el.className).toContain(fundo)
      expect(el.className).toContain('text-white')
      expect(el.className).not.toContain('-soft')
      expect(el.querySelector('[data-slot="badge-dot"]')).toBeNull()
    }
  })

  it('"Não" fica neutro (bg-secondary), também sem dot', () => {
    render(<EscalasIndex {...(props([{ ...escala(1, null), permite_banco_horas: false }]) as never)} />)

    const el = pilula('Não')
    expect(el.className).toContain('bg-secondary')
    expect(el.className).not.toContain('text-white')
    expect(el.querySelector('[data-slot="badge-dot"]')).toBeNull()
  })
})
