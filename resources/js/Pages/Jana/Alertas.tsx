// Jana/Alertas — a aba Alertas da área Jana (`/ia/alertas`).
//
// Âncora de design: `prototipo-ui/cowork/jana-telas-novas.jsx` §`JmAlertas` — âncora de
// SÍMBOLO, nunca de linha (`grep -n "function JmAlertas" prototipo-ui/cowork/jana-telas-novas.jsx`;
// resolva com `node prototipo-ui/ancora.mjs Jana/Alertas`). A ABA vive no `JmTabs` de
// `jana-merge.jsx` (Painel · Conversa · Alertas · Ações · Memória · Plataforma) e aqui
// nasce do ghost `alertas` do `DataController`, lido pelo `JanaSubNav` compartilhado.
//
// A CONTA É DO SERVIDOR: projetado, realizado, desvio, severidade e `dispara` chegam
// prontos do `AlertaService::calcular()` — a MESMA fórmula que dispara a
// `MetaDesvioNotification`. Esta tela filtra e formata; não recalcula (é o §Anti-hooks do
// farol no Painel, agora no eixo do desvio). Ver `Alertas.charter.md` pra tudo que a
// âncora desenha e o servidor NÃO honra (silenciar por meta, config, "perguntar por que caiu").
import React, { useMemo, useState } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Button } from '@/Components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import DataTable from '@/Components/shared/DataTable'
import EmptyState from '@/Components/shared/EmptyState'
import { Inline, Stack } from '@/Components/layout'
import { MoreHorizontal, Settings } from 'lucide-react'
import type { ColumnDef } from '@tanstack/react-table'
import FabJana from './_components/FabJana'
import { JanaAreaHeader } from './_components/JanaAreaHeader'
import JanaConfigDrawer from './_components/JanaConfigDrawer'
import { JanaPlanoBadge } from './_components/JanaPlanoBadge'
import { useJanaPro } from './_components/useJanaPro'
import { useJanaConfig } from './_components/useJanaConfig'
import { formatValue } from './_components/metaFormat'

type Severidade = 'alta' | 'media' | 'baixa'

/** Uma linha = uma meta com base pra comparar. Tudo calculado no servidor. */
export interface AlertaLinha {
  id: number
  meta: string
  slug: string
  unidade: string
  /** `YYYY-MM-DD` da última apuração. */
  data_ref: string
  projetado: number
  realizado: number
  desvio_pct: number
  severidade: Severidade
  /** `|desvio| > corte` — decidido pelo servidor, com o corte dele. */
  dispara: boolean
  /** `lido` só quando a `MetaDesvioNotification` do usuário logado tem `read_at`. */
  status: 'novo' | 'lido'
}

interface Props {
  alertas: AlertaLinha[]
  /** `config('copiloto.alertas.desvio_threshold_default')` — 10 em prod. */
  corte: number
  janaContext?: { businessId: number | null; businessName: string; userName?: string | null }
}

// Rótulos da âncora (`JTN_SEV_LABEL`): "media" sem acento é a CHAVE, "média" é o rótulo.
const SEV_LABEL: Record<Severidade, string> = { alta: 'alta', media: 'média', baixa: 'baixa' }
// Dot por token semântico, não pelo oklch cru da âncora (`.jtn-dot.alta` etc. — AP1).
const SEV_DOT: Record<Severidade, string> = {
  alta: 'bg-destructive',
  media: 'bg-warning',
  baixa: 'bg-info',
}
const FILTROS_SEV: Array<'todas' | Severidade> = ['todas', 'alta', 'media', 'baixa']

const fmtDesvio = (pct: number): string => `${pct < 0 ? '' : '+'}${pct.toFixed(1).replace('.', ',')}%`
const fmtData = (iso: string): string => {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso)
  return m ? `${m[3]}/${m[2]}/${m[1]}` : iso
}

export default function Alertas({ alertas, corte, janaContext }: Props) {
  // Header da área — mesmo drawer/hook das outras 3 telas (onda 4 da paridade).
  const [configAberto, setConfigAberto] = useState(false)
  const { config, alternarAnalise } = useJanaConfig()
  const pro = useJanaPro()

  const [sev, setSev] = useState<'todas' | Severidade>('todas')

  // A lista mostra só o que DISPARA (a regra é do servidor); o filtro de severidade
  // é local porque é só recorte de exibição — não muda nenhum veredito.
  const rows = useMemo(
    () => alertas.filter((a) => a.dispara && (sev === 'todas' || a.severidade === sev)),
    [alertas, sev],
  )
  const abaixoDoCorte = alertas.filter((a) => !a.dispara).length

  const columns = useMemo((): ColumnDef<AlertaLinha>[] => [
    {
      accessorKey: 'meta',
      header: 'Meta',
      cell: ({ row }) => (
        <div className="min-w-0">
          <div className="truncate font-medium text-foreground">{row.original.meta}</div>
          <div className="truncate font-mono text-[10.5px] text-muted-foreground">{row.original.slug}</div>
        </div>
      ),
    },
    {
      accessorKey: 'desvio_pct',
      header: 'Desvio',
      meta: { align: 'right', mono: true },
      cell: ({ row }) => (
        <span className={row.original.desvio_pct < 0 ? 'text-destructive' : 'text-success'}>
          {fmtDesvio(row.original.desvio_pct)}
        </span>
      ),
    },
    {
      accessorKey: 'severidade',
      header: 'Severidade',
      cell: ({ row }) => (
        <span className="inline-flex items-center gap-1.5 text-[11.5px] text-muted-foreground">
          <i aria-hidden className={`size-[7px] shrink-0 rounded-full ${SEV_DOT[row.original.severidade]}`} />
          {SEV_LABEL[row.original.severidade]}
        </span>
      ),
    },
    {
      id: 'conta',
      header: 'Projetado × realizado',
      meta: { align: 'right', mono: true },
      cell: ({ row }) => (
        <span className="text-muted-foreground">
          {formatValue(row.original.projetado, row.original.unidade)} × {formatValue(row.original.realizado, row.original.unidade)}
        </span>
      ),
    },
    {
      accessorKey: 'data_ref',
      header: 'Data ref.',
      meta: { mono: true },
      cell: ({ row }) => <span className="text-muted-foreground">{fmtData(row.original.data_ref)}</span>,
    },
    {
      id: 'canal',
      header: 'Chegou por',
      meta: { mono: true },
      // O ÚNICO canal que existe hoje: `MetaDesvioNotification::via()` = database +
      // broadcast. E-mail/WhatsApp entram quando a config for persistida (US-COPI-061).
      cell: () => <span className="text-muted-foreground">in-app</span>,
    },
    {
      id: 'acoes',
      header: '',
      meta: { align: 'right' },
      cell: ({ row }) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="sm" aria-label={`Ações do alerta de ${row.original.meta}`}>
              <MoreHorizontal className="size-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {/* `<a href>` nativo, nunca `<Link>`: `MetasController@show` devolve BLADE —
                a mesma armadilha do "Nova meta" do Painel (clique viraria no-op). */}
            <DropdownMenuItem asChild>
              <a href={`/ia/metas/${row.original.id}`}>Abrir a meta</a>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ], [])

  return (
    <>
      <JanaAreaHeader
        active="alertas"
        businessName={janaContext?.businessName || undefined}
        businessId={janaContext?.businessId ?? undefined}
        actions={
          <>
            <JanaPlanoBadge pro={pro} onConfigurar={() => setConfigAberto(true)} />
            <Button
              variant="outline"
              size="sm"
              onClick={() => setConfigAberto(true)}
              aria-haspopup="dialog"
              aria-expanded={configAberto}
            >
              <Settings className="h-3.5 w-3.5" /> Configurar
            </Button>
          </>
        }
      />
      <JanaConfigDrawer
        open={configAberto}
        onClose={() => setConfigAberto(false)}
        config={config}
        onAlternarAnalise={alternarAnalise}
      />

      {/* Primitivo de layout (ADR 0253), não flex solto — o layout-primitives-guard mede por arquivo. */}
      <Stack gap={3} className="p-6">
        {/* Toolbar — os chips de severidade da âncora (`jm-mem-cats`) + a contagem
            (`jmc-count`). Os chips de STATUS (abertos · silenciados · todos) não
            entram: sem "silenciar" no servidor, "silenciados" seria filtro sempre
            vazio — affordance morta (charter §Anti-hooks). */}
        <Inline gap={3} align="center" wrap data-contract="alertas-toolbar">
          <Inline gap={1} align="center" wrap role="group" aria-label="Filtrar por severidade">
            {FILTROS_SEV.map((f) => (
              <Button
                key={f}
                size="sm"
                variant={sev === f ? 'secondary' : 'ghost'}
                onClick={() => setSev(f)}
                aria-pressed={sev === f}
              >
                {f === 'todas' ? 'todas' : SEV_LABEL[f]}
              </Button>
            ))}
          </Inline>
          <span className="ml-auto text-sm text-muted-foreground">
            {rows.length} disparando · corte em {corte}%
          </span>
        </Inline>

        {rows.length > 0 ? (
          <div data-contract="alertas-lista">
            <DataTable<AlertaLinha>
              columns={columns}
              data={rows}
              // Lista inteira vem numa prop (poucas metas por business); o paginador de
              // uma página só satisfaz o contrato do `DataTable` sem inventar endpoint.
              pagination={{ data: rows, total: rows.length, current_page: 1, last_page: 1, from: 1, to: rows.length, links: [] }}
              endpoint="/ia/alertas"
              showSearch={false}
              rowKey={(r) => r.id}
              // Trilha vermelha da âncora (`state: "urgent"`): severidade alta.
              rowState={(r) => (r.severidade === 'alta' ? 'urgent' : undefined)}
            />
          </div>
        ) : (
          <div data-contract="alertas-vazio">
            {/* Copy literal da âncora (`EmptyState variant="done"`). */}
            <EmptyState
              variant="success"
              icon="check"
              title="Nenhum desvio acima do corte"
              description={`Com corte em ${corte}%, as ${alertas.length} metas apuradas estão dentro da projeção linear do período.`}
            />
          </div>
        )}

        {/* Copy literal da âncora (`jtn-nota`). `AlertaService::avaliar` é o símbolo real. */}
        <p data-contract="alertas-nota" className="m-0 max-w-[82ch] text-[11px] leading-relaxed text-muted-foreground">
          Projeção é linear entre <code>data_ini</code> e <code>data_fim</code> do período vigente
          (<code>AlertaService::avaliar</code>); severidade é múltiplo do corte — 1× baixa, 1,5× média, 3× alta.
          {abaixoDoCorte > 0 && ` ${abaixoDoCorte} meta(s) apurada(s) ficaram abaixo do corte e por isso não aparecem.`}
          {' '}Sem período ativo ou sem apuração o serviço volta calado: não existe alerta sem com o que comparar.
        </p>
      </Stack>

      <FabJana contextRoute="/ia/alertas" />
    </>
  )
}

Alertas.layout = (page: React.ReactNode) => (
  // `data-screen-label="Jana — Alertas"` da âncora → título do shell, no mesmo padrão
  // das irmãs (`Jana — Painel`, `Jana — Memória`).
  <AppShellV2 title="Jana — Alertas">{page}</AppShellV2>
)
