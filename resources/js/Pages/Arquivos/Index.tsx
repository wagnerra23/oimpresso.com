// @arquivos
//   tela: /arquivos
//   adrs: 0123 (Modules/Arquivos DMS backbone), 0093 (multi-tenant Tier 0), 0360 (Admin Center deprecado)
//   spec: memory/requisitos/Arquivos/SPEC.md US-ARQ-013
//   runbook: memory/requisitos/Arquivos/RUNBOOK-index.md
//   charter: ./Index.charter.md · casos: ./Index.casos.md
//
// ONDA 1 · PR-1 — ACERVO. Leitura pura: nenhum caminho aqui escreve, apaga ou dispara job.
// As outras três vistas do charter (retenção · cofre · trilha) chegam nos PRs seguintes;
// a barra de abas nasce com elas, não antes — aba que não leva a lugar nenhum é promessa.
//
// Layout por PRIMITIVOS (ADR 0253): nada de `<div className="flex gap-4">` solto — o
// layout-primitives-guard é catraca e reprova adotante novo.

import React from 'react'
import { Deferred, router } from '@inertiajs/react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { PageHeader } from '@/Components/PageHeader'
import DataTable from '@/Components/shared/DataTable'
import EmptyState from '@/Components/shared/EmptyState'
import { Stack, Inline } from '@/Components/layout'
import { Badge } from '@/Components/ui/badge'
import { Input } from '@/Components/ui/input'
import { Skeleton } from '@/Components/ui/skeleton'
import type { ColumnDef } from '@tanstack/react-table'

interface LinhaAcervo {
  id: number
  nome: string
  sub_destination: string | null
  bucket: string | null
  visibility: string | null
  disk: string | null
  encrypted: boolean
  size_bytes: number
  classified_by: string | null
  orfao: boolean
  dono_tipo: string | null
  dono_id: number | null
  vence_em: string | null
  dias_restantes: number | null
  excluido_em: string | null
}

interface Politica {
  sub: string
  dias: number
  lei: string
}

interface Filtros {
  bucket: string | null
  owner_type: string | null
  mime: string | null
  from: string | null
  to: string | null
  q: string | null
  with_trashed: boolean
  per_page: number
}

interface Paginator {
  data: LinhaAcervo[]
  [k: string]: unknown
}

interface Props {
  filtros: Filtros
  politica: Politica[]
  acervo?: Paginator
}

const BUCKETS = ['sensitive', 'common', 'public'] as const

function tamanho(bytes: number): string {
  if (bytes >= 1_073_741_824) return `${(bytes / 1_073_741_824).toFixed(2).replace('.', ',')} GB`
  if (bytes >= 1_048_576) return `${Math.round(bytes / 1_048_576)} MB`
  return `${Math.round(bytes / 1024)} KB`
}

/** Prazo NUNCA aparece sozinho: o charter exige a lei ao lado — número sozinho não ensina o domínio. */
function leiDe(sub: string | null, politica: Politica[]): string | null {
  if (!sub) return null
  return politica.find((p) => p.sub === sub)?.lei ?? null
}

const chip = (ativo: boolean) =>
  ativo
    ? 'rounded-full border px-3 py-1 text-xs font-medium'
    : 'rounded-full border px-3 py-1 text-xs text-muted-foreground'

function colunas(politica: Politica[]): ColumnDef<LinhaAcervo, unknown>[] {
  return [
    {
      id: 'arquivo',
      header: 'Arquivo',
      cell: ({ row }) => {
        const a = row.original
        const lei = leiDe(a.sub_destination, politica)
        return (
          <Stack gap={1} className="min-w-0">
            <span className="font-medium text-foreground">{a.nome}</span>
            <span className="text-xs text-muted-foreground">
              <code>{a.sub_destination ?? 'sem contexto'}</code>
              {lei ? <> · {lei}</> : null}
              {a.classified_by ? (
                <> · classificado por {a.classified_by}</>
              ) : (
                <> · sem classificação humana</>
              )}
            </span>
          </Stack>
        )
      },
    },
    {
      id: 'dono',
      header: 'Onde está preso',
      cell: ({ row }) => {
        const a = row.original
        // Órfão é ACHADO, não item de lista — o charter trata assim e o casos.md defende.
        if (a.orfao) {
          return (
            <Badge variant="destructive" title="Sem arquivable — ninguém alcança pela tela do dono.">
              órfão
            </Badge>
          )
        }
        return (
          <Stack gap={1} className="min-w-0">
            <span>
              {a.dono_tipo} #{a.dono_id}
            </span>
          </Stack>
        )
      },
    },
    {
      id: 'classificacao',
      header: 'Classificação',
      cell: ({ row }) => {
        const a = row.original
        return (
          <Stack gap={1} align="start">
            <Badge variant={a.bucket === 'sensitive' ? 'destructive' : 'secondary'}>
              {a.bucket ?? '—'}
            </Badge>
            <span className="text-xs text-muted-foreground">{a.visibility ?? '—'}</span>
          </Stack>
        )
      },
    },
    {
      id: 'disco',
      header: 'Disco',
      cell: ({ row }) =>
        row.original.encrypted ? (
          <span className="text-xs font-medium">vault · cifrado</span>
        ) : (
          <span className="text-xs text-muted-foreground">{row.original.disk ?? '—'}</span>
        ),
    },
    {
      id: 'tamanho',
      header: 'Tamanho',
      cell: ({ row }) => (
        <span className="text-right tabular-nums">{tamanho(row.original.size_bytes)}</span>
      ),
    },
    {
      id: 'vence',
      header: 'Vence em',
      cell: ({ row }) => {
        const a = row.original
        if (!a.vence_em) return <span className="text-muted-foreground">—</span>
        const d = a.dias_restantes
        // Prazo vencido nunca vira contagem negativa — o casos.md declara isso.
        const rotulo = d !== null && d <= 0 ? 'prazo vencido' : `em ${d} dias`
        return (
          <Stack gap={1} align="start">
            <span className="tabular-nums">{a.vence_em}</span>
            <span
              className={
                d !== null && d <= 30
                  ? 'text-xs font-medium text-destructive'
                  : 'text-xs text-muted-foreground'
              }
            >
              {rotulo}
            </span>
          </Stack>
        )
      },
    },
  ]
}

function TabelaSkeleton() {
  return (
    <Stack gap={2} className="p-2">
      {Array.from({ length: 6 }).map((_, i) => (
        <Skeleton key={i} className="h-12 w-full" />
      ))}
    </Stack>
  )
}

function Acervo({ acervo, politica }: { acervo?: Paginator; politica: Politica[] }) {
  if (!acervo || acervo.data.length === 0) {
    return (
      <EmptyState
        title="Nenhum arquivo guardado ainda."
        description="O acervo enche sozinho: XML de NF-e autorizada, foto de OS, anexo de ticket. Nada é enviado por esta tela — ela administra o que os módulos guardaram."
      />
    )
  }
  return <DataTable columns={colunas(politica)} data={acervo.data} pagination={acervo as never} />
}

export default function Index({ filtros, politica, acervo }: Props) {
  const [busca, setBusca] = React.useState(filtros.q ?? '')

  const irPara = (patch: Record<string, unknown>) =>
    router.get('/arquivos', { ...filtros, ...patch }, { preserveState: true, replace: true })

  const total = acervo?.data.length ?? 0
  const cifrados = acervo?.data.filter((a) => a.encrypted).length ?? 0

  return (
    <AppShellV2>
      <div data-contract="cabecalho">
        <PageHeader
          title="Arquivos"
          subtitle={
            acervo ? `${total} nesta página · ${cifrados} no cofre cifrado` : 'carregando o acervo…'
          }
        />
      </div>

      <Inline gap={3} justify="between" wrap className="py-3" data-contract="acervo-filtros">
        <Inline gap={2} wrap>
          <button type="button" onClick={() => irPara({ bucket: null })} className={chip(!filtros.bucket)}>
            Todos
          </button>
          {BUCKETS.map((b) => (
            <button
              key={b}
              type="button"
              onClick={() => irPara({ bucket: b })}
              className={chip(filtros.bucket === b)}
            >
              {b}
            </button>
          ))}
        </Inline>
        <Input
          value={busca}
          onChange={(e) => setBusca(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') irPara({ q: busca || null })
          }}
          placeholder="Buscar por nome, dono ou contexto…"
          aria-label="Buscar arquivo"
          className="max-w-xs"
        />
      </Inline>

      <div data-contract="acervo">
        <Deferred data="acervo" fallback={<TabelaSkeleton />}>
          <Acervo acervo={acervo} politica={politica} />
        </Deferred>
      </div>

      <p className="pt-4 text-xs leading-relaxed text-muted-foreground">
        O acervo é administrativo: o arquivo continua sendo alcançado pela tela do dono. Baixar do
        cofre passa sempre pelo <code>DownloadController</code> — <code>Storage::url</code> direto
        não serve arquivo cifrado (ADR 0123 §6), e o link assinado expira em 60 min. Esta tela não
        envia arquivo: upload entra pelos módulos, via trait <code>HasArquivos</code>.
      </p>
    </AppShellV2>
  )
}
