/**
 * Ponto — navegação por abas igual ao protótipo (W9, [W] 2026-09-28, ADR 0418).
 *
 * A fonte é o protótipo (`prototipo-ui/cowork/Wagner/ponto-page.jsx`, constante `ABAS`) — o teste
 * LÊ as duas pontas (protótipo e DataController) em vez de copiar a lista pra cá, senão a lista do
 * teste vira um terceiro dono e drifa em silêncio.
 *
 * Até a thread 06 o REP-P (key `mobile`) ficava fora — W10 aberta e sem rota. W10 ratificada
 * ([W] 2026-09-29, ADR 0419) e `/ponto/mobile` existindo, a aba entrou junto com a tela: 13 de 13.
 */
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'

const raiz = resolve(__dirname, '../..')
const ler = (p: string) => readFileSync(resolve(raiz, p), 'utf8')

/** Rótulos da constante ABAS do protótipo, na ordem. */
function abasDoPrototipo(): Array<{ key: string; label: string }> {
  const src = ler('prototipo-ui/cowork/Wagner/ponto-page.jsx')
  const bloco = src.slice(src.indexOf('const ABAS = ['), src.indexOf('];', src.indexOf('const ABAS = [')))
  return [...bloco.matchAll(/key:\s*"([^"]+)",\s*label:\s*"([^"]+)"/g)].map((m) => ({ key: m[1], label: m[2] }))
}

/** Rótulos dos ghosts do Ponto no DataController, na ordem. */
function ghostsDoServidor(): Array<{ key: string; label: string; icon: string | null }> {
  const src = ler('Modules/Ponto/Http/Controllers/DataController.php')
  // a lista vive em `$abas` (com `perm` por aba desde o #8116) e vira `$ghosts` filtrando permissão
  const ini = src.indexOf('$abas = [')
  // fim = o `],` que FECHA o array de ghosts (linha própria), não o `],` de cada item
  const fim = src.slice(ini).search(/\r?\n\s*\],/)
  const bloco = src.slice(ini, ini + fim)
  return [...bloco.matchAll(/\['key' => '([^']+)',\s*'label' => '([^']+)'[^\]]*?(?:'icon' => '([^']+)')?\]/g)].map((m) => ({
    key: m[1],
    label: m[2],
    icon: m[3] ?? null,
  }))
}

describe('W9 · as abas do Ponto seguem o protótipo', () => {
  it('controle: as duas leituras acharam o que deviam (senão o resto passa vazio)', () => {
    expect(abasDoPrototipo()).toHaveLength(13)
    // a 1ª e a última aba do servidor — uma leitura cortada (já aconteceu na escrita deste teste)
    // perde a última sem avisar
    const keys = ghostsDoServidor().map((g) => g.key)
    expect(keys[0]).toBe('dashboard')
    expect(keys.at(-1)).toBe('configuracoes')
  })

  it('mesma ORDEM e mesmo RÓTULO do protótipo — as 13, REP-P incluída', () => {
    const esperado = abasDoPrototipo().map((a) => a.label)
    expect(ghostsDoServidor().map((g) => g.label)).toEqual(esperado)
  })

  it('toda aba tem ícone', () => {
    expect(ghostsDoServidor().filter((g) => !g.icon)).toEqual([])
  })
})

// ── Render: todas as abas inline, sem `⋯ Mais`, com scroll ────────────────────────────────
const ghosts = ghostsDoServidor().map((g) => ({
  key: g.key,
  label: g.label,
  href: `/ponto/${g.key}`,
  icon: g.icon ?? undefined,
}))

// Mutável por caso: o teste da linha de contexto troca `ponto_contexto` entre renders.
const diferidas: { ponto_contexto?: { competencia: string; colaboradores_no_ponto: number } } = {
  ponto_contexto: { competencia: 'Setembro/2026', colaboradores_no_ponto: 7 },
}

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, ...p }: { children: React.ReactNode }) => <a {...p}>{children}</a>,
  router: { reload: vi.fn() },
  usePage: () => ({
    props: {
      shell: {
        cockpit: { businessNome: 'ROTA LIVRE' },
        menu: [{ label: 'Ponto', primary: { label: 'Bater ponto', href: '/ponto' }, ghosts }],
      },
      business: { name: 'nome-da-sessao' },
      // `ponto_abas` (W9 · contagens): 0 em Aprovações de propósito — o DS mostra zero; null em
      // Conformidade — sem apuração a aba fica SEM número.
      ponto_abas: { aprovacoes: 0, intercorrencias: 8, conformidade: null, colaboradores: 9 },
      ...diferidas,
    },
  }),
}))

import PontoSubNav from '@/Pages/Ponto/_shared/PontoSubNav'
import PontoAreaHeader from '@/Pages/Ponto/_shared/PontoAreaHeader'

describe('W9 · PontoSubNav mostra todas as abas', () => {
  it('uma aba por ghost, sem o menu "Mais"', () => {
    render(<PontoSubNav active="escalas" hidePrimary />)
    expect(screen.getAllByRole('tab')).toHaveLength(ghosts.length)
    expect(screen.queryByRole('button', { name: /mais/i })).toBeNull()
  })

  it('a barra rola também no desktop (sem md:overflow-visible) e marca a aba ativa', () => {
    render(<PontoSubNav active="escalas" hidePrimary />)
    const lista = screen.getByRole('tablist')
    expect(lista.className).toContain('overflow-x-auto')
    expect(lista.className).not.toContain('md:overflow-visible')
    expect(screen.getByRole('tab', { selected: true }).textContent).toContain('Escalas')
  })
})

// ── Header de módulo: o `MP.Header` do protótipo (`ponto-page.jsx` §PontoPage) ──────────
describe('W9 · PontoAreaHeader é o header do protótipo, igual em toda tela', () => {
  it('título "Ponto" + papel da Portaria, sem título por tela', () => {
    render(<PontoAreaHeader active="escalas" />)
    expect(screen.getByRole('heading', { level: 1 }).textContent).toContain('Ponto')
    expect(screen.getByText('Ponto eletrônico · Portaria MTP 671/2021')).toBeTruthy()
  })

  it('linha de contexto completa: empresa do SHELL · competência · N colaboradores no ponto', () => {
    diferidas.ponto_contexto = { competencia: 'Setembro/2026', colaboradores_no_ponto: 7 }
    render(<PontoAreaHeader active="escalas" />)
    expect(screen.getByText('ROTA LIVRE · Setembro/2026 · 7 colaboradores no ponto')).toBeTruthy()
    expect(screen.queryByText(/nome-da-sessao/i)).toBeNull()
  })

  it('concorda em número: 1 colaborador no ponto', () => {
    diferidas.ponto_contexto = { competencia: 'Setembro/2026', colaboradores_no_ponto: 1 }
    render(<PontoAreaHeader active="escalas" />)
    expect(screen.getByText('ROTA LIVRE · Setembro/2026 · 1 colaborador no ponto')).toBeTruthy()
  })

  it('antes da prop diferida chegar, só a empresa — sem pedaço inventado nem separador órfão', () => {
    delete diferidas.ponto_contexto
    render(<PontoAreaHeader active="escalas" />)
    expect(screen.getByText('ROTA LIVRE')).toBeTruthy()
    diferidas.ponto_contexto = { competencia: 'Setembro/2026', colaboradores_no_ponto: 7 }
  })

  it('selo "Atualizado" e a ação "Nova intercorrência" → create', () => {
    render(<PontoAreaHeader active="escalas" atualizadoAs="09:18" />)
    expect(screen.getByRole('button', { name: /Atualizado 09:18/ })).toBeTruthy()
    expect(screen.getByRole('link', { name: /Nova intercorrência/ }).getAttribute('href')).toBe('/ponto/intercorrencias/create')
  })

  it('as abas ficam ABAIXO da linha do título (faixa própria), não dentro dela', () => {
    render(<PontoAreaHeader active="escalas" />)
    const h1 = screen.getByRole('heading', { level: 1 })
    const lista = screen.getByRole('tablist')
    // a faixa de abas não é descendente do bloco do título
    expect(h1.parentElement?.contains(lista)).toBe(false)
    expect(h1.compareDocumentPosition(lista) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
  })
})

// ── Contagens nas abas (`ponto_abas`) ────────────────────────────────────────────────────
describe('W9 · contagens nas abas, como o protótipo', () => {
  const aba = (rotulo: string) => screen.getAllByRole('tab').find((t) => t.textContent?.startsWith(rotulo))!

  it('mostra o número que o servidor mandou, na aba certa', () => {
    render(<PontoAreaHeader active="escalas" />)
    expect(aba('Intercorrências').textContent).toBe('Intercorrências8')
    expect(aba('Colaboradores').textContent).toBe('Colaboradores9')
  })

  it('zero APARECE (é número, como no TabBar do DS)', () => {
    render(<PontoAreaHeader active="escalas" />)
    expect(aba('Aprovações').textContent).toBe('Aprovações0')
  })

  it('Conformidade null = aba SEM número (nunca 0) · aba sem chave também sem número', () => {
    render(<PontoAreaHeader active="escalas" />)
    expect(aba('Conformidade').textContent).toBe('Conformidade')
    expect(aba('Escalas').textContent).toBe('Escalas')
  })

  it('sem `ponto_abas` (1º paint, prop diferida) nenhuma aba tem número', () => {
    render(<PontoSubNav active="escalas" hidePrimary />)
    expect(aba('Intercorrências').textContent).toBe('Intercorrências')
  })
})
