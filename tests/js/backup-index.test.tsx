/**
 * Backup/Index — o que a TELA precisa dizer em voz alta.
 *
 * @covers-us UC-BKP-02 UC-BKP-08
 *
 * Estes dois casos são de TEXTO RENDERIZADO, não de props: o Pest prova que o backend manda
 * `destino.remoto`, mas só o render prova que o usuário LÊ o aviso. Por isso vivem aqui.
 */
import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Deferred: ({ children }: { children: React.ReactNode }) => children,
  router: { post: vi.fn(), get: vi.fn() },
}))
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

import BackupIndex from '@/Pages/Backup/Index'

const base = {
  backups: {
    // PaginatorShape: a retencao limita a lista, entao e sempre 1 pagina
    data: [
      {
        file_name: '2026-08-19-03-00-04.zip',
        file_size: 268435456,
        file_size_human: '256 MB',
        last_modified: '2026-08-19T03:00:04-03:00',
        origem: 'agendado' as const,
      },
    ],
    total: 1,
    current_page: 1,
    last_page: 1,
    from: 1,
    to: 1,
    links: [],
  },
  retencao: { estrategia: 'KeepLatestBackups', manter: 5 },
  cron: '* * * * * /usr/bin/php /var/www/artisan schedule:run',
  agendado_ok: true,
  pode: { gerar: true, baixar: true, excluir: true },
}

describe('Backup/Index', () => {
  // UC-BKP-08 — a tela declara que o zip e do banco inteiro
  it('declara que o arquivo contem os dados de TODOS os negocios', () => {
    render(<BackupIndex {...base} destino={{ disk: 'local', remoto: false, pasta: 'public/uploads/UltimatePOS' }} />)

    expect(screen.getByText(/todos os neg[óo]cios/i)).not.toBeNull()
  })

  // UC-BKP-02 — destino local avisa; destino remoto NAO avisa (controle negativo)
  it('avisa quando o destino e o disco local', () => {
    render(<BackupIndex {...base} destino={{ disk: 'local', remoto: false, pasta: 'public/uploads/UltimatePOS' }} />)

    expect(screen.getByText(/Backup no mesmo servidor n[ãa]o [ée] backup/i)).not.toBeNull()
  })

  it('NAO avisa quando o destino e remoto — controle negativo', () => {
    render(<BackupIndex {...base} destino={{ disk: 's3', remoto: true, pasta: 'UltimatePOS' }} />)

    expect(screen.queryByText(/Backup no mesmo servidor/i)).toBeNull()
  })

  // O id vai no TÍTULO, não só no docblock: UC citado apenas em comentário satisfaz o G-2 por
  // string-match mas fica INALCANÇÁVEL pelo G-7 (casos-coverage-guard.mjs:195) — nunca poderia
  // virar ✅ provado. É a forma forte da citação, e é de graça.
  //
  // Este é o caso POR TELA do nome acessível: `tests/a11y-primitives.test.tsx` prova o
  // primitivo `shared/DataTable` isolado; aqui a asserção passa pela página inteira, que é o
  // que o UC-BKP-11 promete. Sem isto, o UC seria "provado" por um teste que não renderiza
  // esta tela — presence-gate com outro nome.
  it('UC-BKP-11 · a lista de backups tem nome acessível (o zip nao fica numa tabela anonima)', () => {
    render(<BackupIndex {...base} destino={{ disk: 's3', remoto: true, pasta: 'UltimatePOS' }} />)

    // Nome COMPUTADO pela mesma cadeia de accname que a tecnologia assistiva usa — não o
    // atributo que escrevemos (§5 2026-07-16: medir a propriedade resolvida).
    expect(screen.getByRole('table', { name: 'Backups no disco' })).not.toBeNull()
  })
})
