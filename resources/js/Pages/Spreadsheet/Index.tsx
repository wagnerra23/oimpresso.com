import { useMemo, useState } from 'react'
import { CompartilharPlanilha, Excluir, Mover, NovaPasta, type Alvo } from './_components/AcoesPlanilha'
import { Deferred, Head } from '@inertiajs/react'
import { ChevronRight, FileSpreadsheet, Folder, MoreVertical, Plus, Search } from 'lucide-react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { PageHeader } from '@/Components/PageHeader'
import EmptyState from '@/Components/shared/EmptyState'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Skeleton } from '@/Components/ui/skeleton'
import { Inline } from '@/Components/layout'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'

/**
 * Planilhas — /spreadsheet/sheets (thread telas-soltas/04, D1 [W] 2026-10-07).
 *
 * Lei: `Index.charter.md`. Casos: `Index.casos.md`. Receita:
 * `memory/requisitos/Spreadsheet/RUNBOOK-spreadsheet-index.md`. Forma:
 * `prototipo-ui/cowork/Wagner/planilhas-page.jsx`.
 *
 * Atrás da flag `useV2SpreadsheetIndex`. Nova pasta, mover, compartilhar e excluir acontecem aqui
 * (`_components/AcoesPlanilha`, mesmos endpoints da Blade). O editor (Luckysheet) segue na Blade
 * até o #10033 (thread 04b).
 */

type Planilha = {
  id: number
  nome: string
  pasta_id: number | null
  atualizada_em: string | null
  criada_por: string | null
  minha: boolean
  compartilhada: { usuarios: number; funcoes: number; tarefas: number }
}
type Pasta = { id: number; nome: string }
type Props = { podeCriar: boolean; planilhas?: Planilha[]; pastas?: Pasta[] }

const SEM_PASTA = 0
const abrir = (id: number) => `/spreadsheet/sheets/${id}`
const criar = (pasta?: number) => `/spreadsheet/sheets/create${pasta ? `?folder_id=${pasta}` : ''}`

function editada(iso: string | null): string {
  if (!iso) return 'Editada —'
  const h = (Date.now() - new Date(iso).getTime()) / 36e5
  if (h < 1) return 'Editada agora'
  if (h < 24) return `Editada há ${Math.round(h)} h`
  const d = Math.round(h / 24)
  return d === 1 ? 'Editada ontem' : `Editada há ${d} dias`
}

const plural = (n: number, um: string, varios: string) => (n ? `${n} ${n > 1 ? varios : um}` : null)

type Acoes = { compartilhar: (a: Alvo) => void; mover: (a: Alvo) => void; excluir: (a: Alvo) => void }

function Linha({ s, acoes }: { s: Planilha; acoes: Acoes }) {
  const c = s.compartilhada
  const compart = [
    plural(c.usuarios, 'usuário', 'usuários'),
    plural(c.funcoes, 'função', 'funções'),
    plural(c.tarefas, 'tarefa', 'tarefas'),
  ].filter(Boolean).join(' · ')
  const soCriador = s.minha ? undefined : 'Só quem criou a planilha'

  return (
    <Inline asChild gap={3}><li className="border-t px-3 py-2" data-testid="planilha">
      <FileSpreadsheet className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
      <a href={abrir(s.id)} className="min-w-0 flex-1">
        <span className="block truncate font-medium">{s.nome}</span>
        <small className="block text-xs text-muted-foreground">
          <span title={s.atualizada_em ? new Date(s.atualizada_em).toLocaleString('pt-BR') : undefined}>
            {editada(s.atualizada_em)}
          </span>
          {' · '}
          {s.minha ? 'criada por você' : `criada por ${s.criada_por ?? '—'}`}
          {compart && ` · compartilhada com ${compart}`}
        </small>
      </a>
      {!s.minha && <Badge variant="neutral">compartilhada com você</Badge>}
      <Button asChild variant="outline" size="sm">
        <a href={abrir(s.id)}>Abrir</a>
      </Button>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" size="icon-sm" aria-label={`Ações de ${s.nome}`}>
            <MoreVertical className="size-4" aria-hidden="true" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem asChild><a href={abrir(s.id)}>Abrir planilha</a></DropdownMenuItem>
          <DropdownMenuItem disabled={!s.minha} title={soCriador} onSelect={() => acoes.compartilhar(s)}>
            Compartilhar…
          </DropdownMenuItem>
          <DropdownMenuItem disabled={!s.minha} title={soCriador} onSelect={() => acoes.mover(s)}>
            Mover para outra pasta…
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem disabled={!s.minha} title={soCriador} onSelect={() => acoes.excluir(s)}>
            Excluir planilha
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </li></Inline>
  )
}

function Arvore({ planilhas, pastas, podeCriar, acoes }: { planilhas: Planilha[]; pastas: Pasta[]; podeCriar: boolean; acoes: Acoes }) {
  const [busca, setBusca] = useState('')
  const [abertas, setAbertas] = useState<Set<number>>(() => new Set())
  const q = busca.trim().toLowerCase()

  const grupos = useMemo(() => {
    const filtra = (arr: Planilha[]) => (q ? arr.filter((s) => s.nome.toLowerCase().includes(q)) : arr)
    const g = pastas.map((p) => ({ id: p.id, nome: p.nome, itens: filtra(planilhas.filter((s) => s.pasta_id === p.id)) }))
    g.push({ id: SEM_PASTA, nome: 'Sem pasta', itens: filtra(planilhas.filter((s) => s.pasta_id === null)) })
    return g
  }, [pastas, planilhas, q])

  const visiveis = grupos.reduce((a, g) => a + g.itens.length, 0)
  const toggle = (id: number) =>
    setAbertas((s) => {
      const n = new Set(s)
      if (n.has(id)) n.delete(id)
      else n.add(id)
      return n
    })

  return (
    <>
      <Inline wrap className="mb-3">
        <div className="relative min-w-64 flex-1">
          <Search className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
          <Input
            className="pl-8"
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
            placeholder="Buscar planilha por nome"
            aria-label="Buscar planilha"
          />
        </div>
        <Button variant="outline" size="sm" onClick={() => setAbertas(new Set(grupos.map((g) => g.id)))}>Expandir tudo</Button>
        <Button variant="outline" size="sm" onClick={() => setAbertas(new Set())}>Recolher tudo</Button>
      </Inline>

      <div className="rounded-md border" data-contract="arvore">
        {q && visiveis === 0 ? (
          <EmptyState
            variant="search"
            title={`Nenhuma planilha com “${busca}”.`}
            description="A busca olha só o nome da planilha — não o conteúdo das células."
          />
        ) : (
          grupos.map((g) => {
            if (q && g.itens.length === 0) return null
            const open = abertas.has(g.id) || (!!q && g.itens.length > 0)
            return (
              <section key={g.id} className="border-t first:border-t-0">
                <Inline className="px-2 py-1.5">
                  <Inline asChild className="flex-1 text-left"><button type="button" aria-expanded={open} onClick={() => toggle(g.id)}>
                    <ChevronRight className={`size-3.5 transition-transform ${open ? 'rotate-90' : ''}`} aria-hidden="true" />
                    <Folder className="size-4 text-muted-foreground" aria-hidden="true" />
                    <b>{g.nome}</b>
                    <span className="text-xs text-muted-foreground">{g.itens.length}</span>
                  </button></Inline>
                  {podeCriar && (
                    <Button asChild variant="ghost" size="sm">
                      <a href={criar(g.id || undefined)}>Criar aqui</a>
                    </Button>
                  )}
                </Inline>
                {open && (
                  <ul>
                    {g.itens.length === 0 ? (
                      <li className="border-t px-3 py-2 text-sm text-muted-foreground">
                        Pasta vazia{podeCriar ? ' — crie a primeira planilha aqui.' : '.'}
                      </li>
                    ) : (
                      g.itens.map((s) => <Linha key={s.id} s={s} acoes={acoes} />)
                    )}
                  </ul>
                )}
              </section>
            )
          })
        )}
      </div>
    </>
  )
}

export default function SpreadsheetIndex({ podeCriar, planilhas, pastas }: Props) {
  const lista = planilhas ?? []
  const [novaPasta, setNovaPasta] = useState(false)
  const [compartilhar, setCompartilhar] = useState<Alvo | null>(null)
  const [mover, setMover] = useState<Alvo | null>(null)
  const [excluir, setExcluir] = useState<Alvo | null>(null)
  const acoes: Acoes = { compartilhar: setCompartilhar, mover: setMover, excluir: setExcluir }
  const subtitulo = planilhas && pastas
    ? `${lista.length} planilhas em ${pastas.length} pastas · ${lista.filter((s) => s.minha).length} criadas por você`
    : undefined

  return (
    <AppShellV2 title="Planilhas" breadcrumbItems={[{ label: 'Planilhas' }]}>
      <Head title="Planilhas" />
      <div data-contract="cabecalho">
        <PageHeader
          title="Planilhas"
          subtitle={subtitulo}
          actions={
            podeCriar ? (
              <>
                <Button variant="outline" onClick={() => setNovaPasta(true)}>Nova pasta</Button>
                <Button asChild>
                  <a href={criar()}><Plus className="size-4" aria-hidden="true" /> Criar planilha</a>
                </Button>
              </>
            ) : (
              <>
                <Button variant="outline" disabled>Nova pasta</Button>
                <Button disabled><Plus className="size-4" aria-hidden="true" /> Criar planilha</Button>
              </>
            )
          }
        />
      </div>

      {!podeCriar && (
        <p className="mb-3 rounded-md border bg-muted/40 px-3 py-2 text-sm">
          Você só tem acesso de leitura: abre e edita o que foi compartilhado com você, mas não cria pasta nem planilha.
        </p>
      )}

      <Deferred data={['planilhas', 'pastas']} fallback={<Skeleton className="h-64 w-full" />}>
        <Arvore planilhas={lista} pastas={pastas ?? []} podeCriar={podeCriar} acoes={acoes} />
      </Deferred>

      <p className="mt-3 text-xs text-muted-foreground">
        Compartilhar, mover e excluir aparecem só para quem criou a planilha — quem recebeu abre e edita, mas não redistribui.
      </p>

      <NovaPasta aberto={novaPasta} onFechar={() => setNovaPasta(false)} />
      <CompartilharPlanilha alvo={compartilhar} onFechar={() => setCompartilhar(null)} />
      <Mover alvo={mover} pastas={pastas ?? []} onFechar={() => setMover(null)} />
      <Excluir alvo={excluir} onFechar={() => setExcluir(null)} />
    </AppShellV2>
  )
}
