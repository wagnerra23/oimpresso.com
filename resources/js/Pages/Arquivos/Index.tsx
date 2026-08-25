// @arquivos
//   tela: /arquivos
//   adrs: 0123 (Modules/Arquivos DMS backbone), 0093 (multi-tenant Tier 0), 0360 (Admin Center deprecado)
//   spec: memory/requisitos/Arquivos/SPEC.md US-ARQ-013
//   runbook: memory/requisitos/Arquivos/RUNBOOK-index.md
//   charter: ./Index.charter.md · casos: ./Index.casos.md
//
// ONDA 1 · PR-1 ACERVO + PR-2 TRILHA. Leitura pura: nenhum caminho aqui escreve, apaga
// ou dispara job. As outras duas vistas do charter (retenção · cofre) chegam nos PR-3/4.
//
// A BARRA DE ABAS NASCE AQUI, com a segunda vista — não antes: aba que não leva a lugar
// nenhum é promessa, não navegação. Ela navega por rota (`?tab=`), como Financeiro,
// Fiscal/Dfe e Cliente — não por estado local, senão o link não é compartilhável e o
// botão voltar do navegador mente.
//
// Layout por PRIMITIVOS (ADR 0253): nada de `<div className="flex gap-4">` solto — o
// layout-primitives-guard é catraca e reprova adotante novo.

import { Deferred, router } from '@inertiajs/react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { PageHeader } from '@/Components/PageHeader'
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs'
import DataTable from '@/Components/shared/DataTable'
import EmptyState from '@/Components/shared/EmptyState'
import { Stack, Inline } from '@/Components/layout'
import { Badge } from '@/Components/ui/badge'
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

/** Uma linha de `arquivos_audit_log`. O arquivo é `#id` — nunca o nome (ver charter). */
interface LinhaTrilha {
  id: number
  quando: string
  acao: string
  arquivo: number
  quem: string | null
  detalhe: string | null
}

/** Faceta de ação: só existe chip pra ação que este business realmente registrou. */
interface FacetaAcao {
  acao: string
  total: number
}

interface Politica {
  sub: string
  dias: number
  lei: string
}

interface Filtros {
  tab: 'acervo' | 'trilha'
  bucket: string | null
  owner_type: string | null
  mime: string | null
  from: string | null
  to: string | null
  q: string | null
  acao: string | null
  with_trashed: boolean
  per_page: number
}

interface Paginator<T> {
  data: T[]
  total: number
  current_page: number
  last_page: number
  from: number | null
  to: number | null
  links: Array<{ url: string | null; label: string; active: boolean }>
}

interface TrilhaPayload {
  eventos: Paginator<LinhaTrilha>
  acoes: FacetaAcao[]
}

interface Props {
  filtros: Filtros
  politica: Politica[]
  /** Só chega quando `tab=acervo` — a vista fechada não é computada no servidor. */
  acervo?: Paginator<LinhaAcervo>
  /** Só chega quando `tab=trilha`. */
  trilha?: TrilhaPayload
}

/** Os buckets que o Curador de fato grava (`CuradorEngine`), na ordem em que interessam a
 *  quem responde por conformidade. `common` e `public` NAO existem — vieram de um palpite meu
 *  e filtravam por valor que o banco nao tem, entao a lista voltava sempre vazia (medido no
 *  smoke de producao, 2026-08-25). O enum do banco tem 7; `user`/`spec`/`ambiguous` nao sao
 *  escritos por nenhum caminho vivo, entao ficam de fora do filtro ate que sejam. */
const BUCKETS = ['sensitive', 'active', 'memory', 'discard'] as const

/**
 * Tom por ação — espelha o mapa `ACAO` do protótipo (`arquivos-page.jsx`), traduzido
 * pras variantes de status do Badge canon.
 *
 * O `default` existe porque o vocabulário é do ENUM da coluna, que já cresceu 2× por
 * migration: ação nova aparece em cinza, nunca quebra a tela.
 */
const TOM_ACAO: Record<string, 'info' | 'neutral' | 'warning' | 'success' | 'danger'> = {
  upload: 'info',
  download: 'neutral',
  classify: 'info',
  reclassify: 'info',
  soft_delete: 'warning',
  restore: 'success',
  hard_delete: 'danger',
  signed_url_issued: 'warning',
  signed_url_consumed: 'warning',
  exported: 'warning',
}

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

/** `filters` do DataTable aceita string|number|null|undefined — `with_trashed` e boolean,
 *  entao vira 1/undefined (o mesmo que a ListArquivosRequest ja aceita como boolean). */
function paraQuery(f: Filtros): Record<string, string | number | null | undefined> {
  return {
    bucket: f.bucket,
    owner_type: f.owner_type,
    mime: f.mime,
    from: f.from,
    to: f.to,
    q: f.q,
    per_page: f.per_page,
    with_trashed: f.with_trashed ? 1 : undefined,
  }
}

/**
 * Query dos chips de navegação.
 *
 * `with_trashed` NÃO pode viajar como boolean cru, e isto foi MEDIDO (2026-08-25), não
 * deduzido: `qs.stringify({with_trashed:false})` — o serializador do Inertia — produz
 * `with_trashed=false`, e a regra `boolean` do Laravel **reprova a string "false"**
 * (aceita só `true|false` nativos, `0|1` e `"0"|"1"`). O `paraQuery` do DataTable já
 * normalizava pra `1|undefined`; o caminho dos chips não, então clicar num chip de bucket
 * voltava com erro de validação em vez de filtrar. Uma normalização só pros dois.
 */
type ValorDeQuery = string | number | null | undefined

function paraNavegacao(f: Filtros, patch: Record<string, ValorDeQuery>): Record<string, ValorDeQuery> {
  return {
    ...f,
    with_trashed: f.with_trashed ? 1 : undefined,
    ...patch,
  }
}

/** A paginação da trilha precisa carregar `tab` — sem ele, a página 2 volta pro acervo. */
function paraQueryTrilha(f: Filtros): Record<string, string | number | null | undefined> {
  return {
    tab: 'trilha',
    acao: f.acao,
    from: f.from,
    to: f.to,
    per_page: f.per_page,
  }
}

/**
 * Chip de filtro.
 *
 * Antes, ativo e inativo diferiam só por `font-medium` — no render de 1728px do visreg
 * o chip selecionado era quase indistinguível dos outros. Agora o ativo carrega o mesmo
 * roxo do `--primary` que a aba ativa usa (ADR 0190), e o inativo ganha `hover`: o
 * elemento passa a dizer que é clicável antes do clique.
 *
 * Tokens do DS (`primary`/`muted`), nunca cor crua — a camada canônica é vigiada por
 * lint pra isso.
 */
const chip = (ativo: boolean) =>
  'rounded-full border px-3.5 py-1.5 text-xs transition-colors ' +
  (ativo
    ? 'border-primary/30 bg-primary/10 font-medium text-primary'
    : 'text-muted-foreground hover:bg-muted hover:text-foreground')

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
              {/* `no_rule_matched` e o fallback do CuradorEngine quando nenhuma regra casa
                  (CuradorEngine:248) — nao e um classificador, e a ausencia de um. Mostrar o
                  identificador tecnico na tela foi o que o smoke de producao pegou. */}
              {a.classified_by && a.classified_by !== 'no_rule_matched' ? (
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

/**
 * Colunas da trilha — a ordem é a do protótipo: Quando · Ação · Arquivo · Quem · Detalhe.
 *
 * Não há coluna de ação-de-linha, e isso é contrato, não esquecimento: `arquivos_audit_log`
 * é append-only e a tela não oferece editar nem apagar. Alterar auditoria é incidente.
 */
const COLUNAS_TRILHA: ColumnDef<LinhaTrilha, unknown>[] = [
  {
    id: 'quando',
    header: 'Quando',
    cell: ({ row }) => (
      <span className="tabular-nums text-xs">{row.original.quando}</span>
    ),
  },
  {
    id: 'acao',
    header: 'Ação',
    cell: ({ row }) => (
      <Badge variant={TOM_ACAO[row.original.acao] ?? 'neutral'}>{row.original.acao}</Badge>
    ),
  },
  {
    id: 'arquivo',
    header: 'Arquivo',
    cell: ({ row }) => (
      <span
        className="tabular-nums text-xs text-muted-foreground"
        title="A trilha guarda o id — o nome do arquivo se vê no Acervo."
      >
        #{row.original.arquivo}
      </span>
    ),
  },
  {
    id: 'quem',
    header: 'Quem',
    cell: ({ row }) =>
      row.original.quem ? (
        <span className="text-xs">{row.original.quem}</span>
      ) : (
        // Sem usuário = comando/job (CLI não tem sessão). Não é anônimo suspeito por si só:
        // o `arquivos:audit-log --suspicious` é quem separa link vazado de rotina agendada.
        <span className="text-xs text-muted-foreground" title="Sem usuário na sessão — comando ou job.">
          sistema
        </span>
      ),
  },
  {
    id: 'detalhe',
    header: 'Detalhe',
    cell: ({ row }) => (
      <span className="text-xs text-muted-foreground">{row.original.detalhe ?? '—'}</span>
    ),
  },
]

function TabelaSkeleton() {
  return (
    <Stack gap={2} className="p-2">
      {Array.from({ length: 6 }).map((_, i) => (
        <Skeleton key={i} className="h-12 w-full" />
      ))}
    </Stack>
  )
}

function Acervo({ acervo, politica, filtros }: { acervo?: Paginator<LinhaAcervo>; politica: Politica[]; filtros: Filtros }) {
  if (!acervo || acervo.data.length === 0) {
    return (
      <EmptyState
        title="Nenhum arquivo guardado ainda."
        description="O acervo enche sozinho: XML de NF-e autorizada, foto de OS, anexo de ticket. Nada é enviado por esta tela — ela administra o que os módulos guardaram."
      />
    )
  }
  return (
    <DataTable
      columns={colunas(politica)}
      data={acervo.data}
      pagination={acervo}
      endpoint="/arquivos"
      filters={paraQuery(filtros)}
      searchPlaceholder="Buscar por nome, dono ou contexto…"
      initialSearch={filtros.q ?? ''}
      rowKey={(a) => a.id}
    />
  )
}

function Trilha({ trilha, filtros }: { trilha?: TrilhaPayload; filtros: Filtros }) {
  if (!trilha || trilha.eventos.data.length === 0) {
    // Filtrada-vazia e vazia-de-verdade contam histórias diferentes: com um chip ativo,
    // quem explica o vazio é o filtro; sem chip, é o módulo que ainda não registrou nada.
    return filtros.acao ? (
      <EmptyState
        title={`Nenhum evento de ${filtros.acao} no período.`}
        description="A trilha guarda o que aconteceu — se não aconteceu, não há linha. Tire o filtro pra ver as outras ações."
      />
    ) : (
      <EmptyState
        title="Nenhum evento registrado ainda."
        description="A trilha enche sozinha: cada upload, download, link assinado, exclusão e restauração vira uma linha. Ela é append-only e nunca é purgada — nem quando o arquivo é."
      />
    )
  }

  return (
    <DataTable
      columns={COLUNAS_TRILHA}
      data={trilha.eventos.data}
      pagination={trilha.eventos}
      endpoint="/arquivos"
      filters={paraQueryTrilha(filtros)}
      showSearch={false}
      rowKey={(t) => t.id}
    />
  )
}

export default function Index({ filtros, politica, acervo, trilha }: Props) {
  const vista = filtros.tab === 'trilha' ? 'trilha' : 'acervo'

  const irPara = (patch: Record<string, ValorDeQuery>) =>
    router.get('/arquivos', paraNavegacao(filtros, patch), { preserveState: true, replace: true })

  const total = acervo?.data.length ?? 0
  const cifrados = acervo?.data.filter((a) => a.encrypted).length ?? 0

  // As abas NÃO carregam badge de contagem. O protótipo mostra uma porque tem tudo em
  // memória; aqui custaria um COUNT na tabela inteira, eager, pra pintar um número na
  // aba que o usuário nem abriu. O número da vista aberta vai no subtítulo, de graça,
  // vindo do paginador que já veio.
  const subtitulo =
    vista === 'trilha'
      ? trilha
        ? `${trilha.eventos.total} eventos registrados`
        : 'carregando a trilha…'
      : acervo
        ? `${total} nesta página · ${cifrados} no cofre cifrado`
        : 'carregando o acervo…'

  return (
    <AppShellV2>
      {/* Fundo cream warm (hue 90) + respiro no rodapé da página.
          `.main-body` do AppShellV2 NÃO tem padding nem cor de fundo próprios (medido em
          `cockpit.css`: só min-height/overflow/flex) — cada tela paga o seu. Sem isto a
          tela nascia branca e apertada contra a sidebar. Mesmo par das telas maduras
          (Cliente/Index · Financeiro/ProvaViva · Jana/Pro · Produto/Unificado). */}
      <div className="flex-1 bg-page-cream pb-8">
        <div data-contract="cabecalho">
          {/* O PageHeader canon já traz `pt-6 px-6 pb-3.5` por dentro — por isso ele fica
              FORA do wrapper de padding abaixo, senão o título ganharia 48px e desalinharia
              ao contrário. */}
          <PageHeader title="Arquivos" subtitle={subtitulo} />
        </div>

        {/* `px-6` casa com o px-6 interno do header: sem ele o header ficava recuado 24px e
            TODO o resto (abas, chips, tabela, rodapé) colava na borda da sidebar — visível
            no render do visreg. `space-y-6` dá o ritmo entre os blocos, no lugar dos
            `py-3`/`pb-3`/`pt-4` soltos que cada bloco carregava. */}
        <div className="w-full px-6 pt-5 space-y-6">
          <div data-contract="abas">
            <PageHeaderTabs
              ghosts={[
                { key: 'acervo', label: 'Acervo', href: '/arquivos?tab=acervo' },
                { key: 'trilha', label: 'Trilha', href: '/arquivos?tab=trilha' },
              ]}
              activeGhostKey={vista}
            />
          </div>

          {vista === 'acervo' && (
            /* Ritmo interno da vista (16px) menor que o do wrapper (24px): abas e vista são
               blocos distintos; filtro, tabela e nota são a MESMA conversa. */
            <div className="space-y-4">
              <Inline gap={3} justify="between" wrap data-contract="acervo-filtros">
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
              </Inline>

              <div data-contract="acervo">
                <Deferred data="acervo" fallback={<TabelaSkeleton />}>
                  <Acervo acervo={acervo} politica={politica} filtros={filtros} />
                </Deferred>
              </div>

              {/* Largura de leitura: sem ela a nota virava uma linha de ponta a ponta do
                  monitor — no render de 1728px do visreg media ~1400px (~230 caracteres).
                  `ch` e não `max-w-3xl` porque o critério é caractere por linha, e 72ch cai
                  na faixa legível; o repo já tem precedente da forma (`max-w-[76ch]`,
                  `max-w-[80ch]`). NÃO usar `max-w-prose`: não há `prose` no theme deste
                  projeto (conferido), então a classe seria ignorada em silêncio. */}
              <p className="max-w-[72ch] text-xs leading-relaxed text-muted-foreground">
                O acervo é administrativo: o arquivo continua sendo alcançado pela tela do dono. Baixar do
                cofre passa sempre pelo <code>DownloadController</code> — <code>Storage::url</code> direto
                não serve arquivo cifrado (ADR 0123 §6), e o link assinado expira em 60 min. Esta tela não
                envia arquivo: upload entra pelos módulos, via trait <code>HasArquivos</code>.
              </p>
            </div>
          )}

          {vista === 'trilha' && (
            <div className="space-y-4">
              {/* Os chips saem do próprio log (GROUP BY), então só existe filtro pra ação que
                  este business registrou de fato — nunca um chip que leva a lista vazia. */}
              <Inline gap={3} justify="between" wrap data-contract="trilha-filtros">
                <Inline gap={2} wrap>
                  <button type="button" onClick={() => irPara({ acao: null })} className={chip(!filtros.acao)}>
                    Todas
                  </button>
                  {(trilha?.acoes ?? []).map((f) => (
                    <button
                      key={f.acao}
                      type="button"
                      onClick={() => irPara({ acao: f.acao })}
                      className={chip(filtros.acao === f.acao)}
                    >
                      {f.acao} <span className="tabular-nums opacity-70">{f.total}</span>
                    </button>
                  ))}
                </Inline>
              </Inline>

              <div data-contract="trilha">
                <Deferred data="trilha" fallback={<TabelaSkeleton />}>
                  <Trilha trilha={trilha} filtros={filtros} />
                </Deferred>
              </div>

              <p className="max-w-[72ch] text-xs leading-relaxed text-muted-foreground">
                <code>arquivos_audit_log</code> é append-only e <strong>nunca purgado</strong> — nem quando o
                arquivo é apagado. Esta tela não oferece editar nem apagar linha: alterar auditoria é
                incidente, não conserto. Para varredura de padrão suspeito (link assinado sem usuário,
                exclusão em série, download repetido do mesmo IP), quem responde é o comando{' '}
                <code>arquivos:audit-log --suspicious</code>.
              </p>
            </div>
          )}
        </div>
      </div>
    </AppShellV2>
  )
}
