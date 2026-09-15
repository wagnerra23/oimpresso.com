/**
 * Ponto/Colaboradores/Index — a lista REDIGE o documento, não só o formata.
 *
 * @covers-us UC-COLIDX-03
 *
 * Este caso vive aqui, e não no Pest, porque o que ele prova é o RENDER: o backend já manda
 * `cpf` e `pis` inteiros (ColaboradorController@index linhas 47-48, e deve mandar — o form de
 * edição precisa deles). Quem decide o que o gestor LÊ na varredura é a tela. Pest provaria a
 * prop; só o render prova a redação.
 *
 * ⚠️ O ASSERT É ESCRITO PRA DISTINGUIR REDIGIR DE FORMATAR, e isso não é preciosismo: o front
 * tem `maskCPF`, que parece resolver e não resolve — ela insere pontos e hífen e entrega o
 * documento INTEIRO. Um assert que só procurasse "•" passaria numa tela que mostra um bullet
 * COLADO no documento formatado inteiro. Então cada caso cobra as duas pontas: o `•` presente
 * E a sequência completa de dígitos ausente.
 */
import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children }: { children: React.ReactNode }) => <a>{children}</a>,
  router: { get: vi.fn(), post: vi.fn() },
}))
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))
vi.mock('@/Pages/Ponto/_shared/PontoSubNav', () => ({ default: () => null }))

import ColaboradoresIndex from '@/Pages/Ponto/Colaboradores/Index'

// Dado sintético de teste — nenhum destes é documento de pessoa real.  // pii-allowlist (fixture de teste, não é dado de cliente)
const COM_PIS = { cpf: '11122233396', pis: '12034567890' }   // pii-allowlist (fixture de teste)
const SEM_PIS = { cpf: '44455566678', pis: null }            // pii-allowlist (fixture de teste)

const linha = (id: number, nome: string, doc: { cpf: string; pis: string | null }) => ({
  id,
  nome,
  email: null,
  matricula: String(1000 + id),
  cpf: doc.cpf,
  pis: doc.pis,
  escala: 'Comercial 44h',
  controla_ponto: true,
  usa_banco_horas: false,
  saldo_banco_horas_minutos: 0,
  ultimo_ponto: null,
})

const props = (linhas: ReturnType<typeof linha>[]) => ({
  colaboradores: { data: linhas, links: [], current_page: 1, last_page: 1, total: linhas.length, per_page: 20 },
  filtros: { search: '' },
  search: '',
})

describe('UC-COLIDX-03 · lista redige CPF e PIS', () => {
  it('mostra só os 3 últimos dígitos do CPF, e nunca o documento inteiro', () => {
    render(<ColaboradoresIndex {...(props([linha(1, 'Maria Andrade', COM_PIS)]) as never)} />)

    // A ponta positiva: os 3 últimos aparecem, precedidos de redação.
    expect(screen.getByText('••••••••396')).toBeTruthy()
    // A ponta negativa — é ela que separa redigir de formatar. Varro o texto da tela inteira
    // porque o defeito pode aparecer em qualquer célula, não só na que eu escolhi olhar.
    const tela = document.body.textContent ?? ''
    expect(tela).not.toContain(COM_PIS.cpf)          // cru
    // O literal formatado É o objeto do assert: é exatamente o que `maskCPF` produziria.
    expect(tela).not.toContain('111.222.333-96')     // pii-allowlist (fixture sintética do teste)
  })

  it('redige o PIS junto, no mesmo padrão', () => {
    render(<ColaboradoresIndex {...(props([linha(1, 'Maria Andrade', COM_PIS)]) as never)} />)

    expect(screen.getByText(/PIS\s+••••••••890/)).toBeTruthy()
    expect(document.body.textContent ?? '').not.toContain(COM_PIS.pis as string)
  })

  it('diz "PIS não cadastrado" quando falta — PIS ausente é acionável, não vazio', () => {
    render(<ColaboradoresIndex {...(props([linha(2, 'João Pereira', SEM_PIS)]) as never)} />)

    // Não é cosmético: o AFD da Portaria 671/2021 é chaveado por PIS, então colaborador sem PIS
    // não tem marcação importada. Um "—" mudo esconderia isso do gestor.
    expect(screen.getByText('PIS não cadastrado')).toBeTruthy()
    // E o CPF dele segue redigido — a ausência de PIS não afrouxa a outra coluna.
    expect(screen.getByText('••••••••678')).toBeTruthy()
  })

  it('redige TODAS as linhas, não só a primeira', () => {
    // Controle contra o defeito clássico de aplicar a mudança no primeiro `map` e parar: com duas
    // linhas, uma redigida e outra crua passaria num assert de linha única.
    render(
      <ColaboradoresIndex
        {...(props([linha(1, 'Maria Andrade', COM_PIS), linha(2, 'João Pereira', SEM_PIS)]) as never)}
      />,
    )

    const tela = document.body.textContent ?? ''
    expect(screen.getByText('••••••••396')).toBeTruthy()
    expect(screen.getByText('••••••••678')).toBeTruthy()
    expect(tela).not.toContain(COM_PIS.cpf)
    expect(tela).not.toContain(SEM_PIS.cpf)
  })
})
