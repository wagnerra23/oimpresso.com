// Jana/Plataforma — a aba Plataforma da área Jana (`/ia/superadmin/metas`), só superadmin.
//
// Âncora de design: `prototipo-ui/cowork/jana-telas-novas.jsx` §`JmPlataforma` — âncora de
// SÍMBOLO (`grep -n "function JmPlataforma" prototipo-ui/cowork/jana-telas-novas.jsx`; resolva
// com `node prototipo-ui/ancora.mjs Jana/Plataforma`). A ABA vem do `JmTabs` de `jana-merge.jsx`
// (6ª, `can(papel, "jana.superadmin")`) e aqui nasce do ghost `plataforma` do `DataController`,
// que usa o MESMO gate real da rota (P0 #6421) — menu e rota concordam.
//
// Listagem CRUA, de propósito: a agregação cross-business não existe no controller, e somar aqui
// na tela seria inventar total de plataforma no cliente. As contagens do bloco de instalação
// vêm do servidor (disco/registry), não do "21 · 4 · 24" fixo da âncora.
import React, { useMemo, useState } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Button } from '@/Components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import DataTable from '@/Components/shared/DataTable'
import { Grid, Inline, Stack } from '@/Components/layout'
import { Settings } from 'lucide-react'
import type { ColumnDef } from '@tanstack/react-table'
import FabJana from './_components/FabJana'
import { JanaAreaHeader } from './_components/JanaAreaHeader'
import JanaConfigDrawer from './_components/JanaConfigDrawer'
import { JanaPlanoBadge } from './_components/JanaPlanoBadge'
import { useJanaPro } from './_components/useJanaPro'
import { useJanaConfig } from './_components/useJanaConfig'

interface MetaPlataforma { id: number; nome: string; slug: string; unidade: string; origem: string }
interface MetaCliente {
  id: number
  business_id: number
  empresa: string | null
  nome: string
  unidade: string
  periodo: { data_ini: string | null; data_fim: string | null } | null
  /** `YYYY-MM-DD` da última apuração, ou `null` = nunca apurada. */
  ultima: string | null
}
interface Props {
  metasPlataforma: MetaPlataforma[]
  metasDeClientes: MetaCliente[]
  instalacao: { migrations: number; seeders: number; permissoes: number; versao: string | null; podeOperar: boolean }
  janaContext?: { businessId: number | null; businessName: string; userName?: string | null }
}

const dm = (iso: string | null | undefined): string => {
  const m = iso ? /^(\d{4})-(\d{2})-(\d{2})/.exec(iso) : null
  return m ? `${m[3]}/${m[2]}` : '—'
}
const dmy = (iso: string | null): string => {
  const m = iso ? /^(\d{4})-(\d{2})-(\d{2})/.exec(iso) : null
  return m ? `${m[3]}/${m[2]}/${m[1]}` : '—'
}
// Paginador de uma página: a lista inteira vem na prop; sem endpoint inventado.
const umaPagina = <T,>(rows: T[]) => ({ data: rows, total: rows.length, current_page: 1, last_page: 1, from: rows.length ? 1 : null, to: rows.length, links: [] })

function Secao({ titulo, sub, children }: { titulo: string; sub: string; children: React.ReactNode }) {
  return (
    // Primitivos de layout (ADR 0253) — o layout-primitives-guard conta flex/grid solto por arquivo.
    <Stack gap={2} asChild>
      <section>
        <Inline gap={2} align="baseline">
          <h3 className="m-0 text-[13.5px] font-semibold text-foreground">{titulo}</h3>
          <small className="font-mono text-[10.5px] text-muted-foreground">{sub}</small>
        </Inline>
        {children}
      </section>
    </Stack>
  )
}

export default function Plataforma({ metasPlataforma, metasDeClientes, instalacao, janaContext }: Props) {
  const [configAberto, setConfigAberto] = useState(false)
  const { config, alternarAnalise } = useJanaConfig()
  const pro = useJanaPro()
  const [confirmar, setConfirmar] = useState<'update' | 'uninstall' | null>(null)

  const nEmpresas = new Set(metasDeClientes.map((c) => c.business_id)).size

  const colsPlat = useMemo((): ColumnDef<MetaPlataforma>[] => [
    { accessorKey: 'nome', header: 'Meta', cell: ({ row }) => (
      <div className="min-w-0"><div className="truncate font-medium text-foreground">{row.original.nome}</div><div className="truncate font-mono text-[10.5px] text-muted-foreground">{row.original.slug}</div></div>
    ) },
    { accessorKey: 'unidade', header: 'Unidade', meta: { mono: true } },
    // `manual` → copy da âncora; os demais valores do enum (`seed`, `chat_ia`) são dado, ficam crus.
    { accessorKey: 'origem', header: 'Origem', cell: ({ row }) => <span className="text-muted-foreground">{row.original.origem === 'manual' ? 'cadastro manual' : row.original.origem}</span> },
  ], [])

  const colsCli = useMemo((): ColumnDef<MetaCliente>[] => [
    { accessorKey: 'business_id', header: 'Business', cell: ({ row }) => (
      <div className="min-w-0"><div className="font-mono font-medium text-foreground">#{row.original.business_id}</div><div className="truncate text-[10.5px] text-muted-foreground">{row.original.empresa ?? '—'}</div></div>
    ) },
    { accessorKey: 'nome', header: 'Meta' },
    { accessorKey: 'unidade', header: 'Unidade', meta: { mono: true } },
    { id: 'periodo', header: 'Período atual', meta: { mono: true }, cell: ({ row }) => (
      <span className="text-muted-foreground">{row.original.periodo ? `${dm(row.original.periodo.data_ini)}–${dm(row.original.periodo.data_fim)}` : '—'}</span>
    ) },
    { accessorKey: 'ultima', header: 'Última apuração', meta: { mono: true }, cell: ({ row }) => (
      <span className="text-muted-foreground">{row.original.ultima ? dmy(row.original.ultima) : 'nunca apurada'}</span>
    ) },
  ], [])

  return (
    <>
      <JanaAreaHeader
        active="plataforma"
        businessName={janaContext?.businessName || undefined}
        businessId={janaContext?.businessId ?? undefined}
        actions={
          <>
            <JanaPlanoBadge pro={pro} onConfigurar={() => setConfigAberto(true)} />
            <Button variant="outline" size="sm" onClick={() => setConfigAberto(true)} aria-haspopup="dialog" aria-expanded={configAberto}>
              <Settings className="h-3.5 w-3.5" /> Configurar
            </Button>
          </>
        }
      />
      <JanaConfigDrawer open={configAberto} onClose={() => setConfigAberto(false)} config={config} onAlternarAnalise={alternarAnalise} />

      <Stack gap={4} className="p-6">
        {/* Âncora LITERAL: o gate contrato-de-tela lê `data-contract="…"` no fonte, não em runtime. */}
        <div data-contract="plat-metas-plataforma">
        <Secao titulo="Metas da plataforma" sub={`business_id NULL · ${metasPlataforma.length} metas`}>
          <DataTable<MetaPlataforma> columns={colsPlat} data={metasPlataforma} pagination={umaPagina(metasPlataforma)} endpoint="/ia/superadmin/metas" showSearch={false} rowKey={(r) => r.id} emptyMessage="Nenhuma meta da plataforma cadastrada." />
        </Secao>
        </div>

        {/* Âncora LITERAL: o gate contrato-de-tela lê `data-contract="…"` no fonte, não em runtime. */}
        <div data-contract="plat-metas-clientes">
        <Secao titulo="Metas de clientes" sub={`cross-business · ${metasDeClientes.length} metas em ${nEmpresas} empresas`}>
          <DataTable<MetaCliente> columns={colsCli} data={metasDeClientes} pagination={umaPagina(metasDeClientes)} endpoint="/ia/superadmin/metas" showSearch={false} rowKey={(r) => r.id} rowState={(r) => (r.ultima ? undefined : 'archived')} emptyMessage="Nenhum cliente configurou metas ainda." />
          {/* Copy literal da âncora — o "27/08/2026" é fato datado (a medição), não presente. */}
          <p className="m-0 max-w-[82ch] text-[11px] leading-relaxed text-muted-foreground">
            Listagem crua, de propósito: a <b>agregação cross-business</b> que o docblock antigo prometia não
            existe no controller (nenhum <code>sum</code>/<code>count</code>/<code>groupBy</code>, medido em
            27/08/2026). Somar aqui na tela seria inventar total de plataforma no cliente — a pendência fica
            declarada até alguém decidir o que a plataforma quer medir.
          </p>
        </Secao>
        </div>

        {/* Âncora LITERAL: o gate contrato-de-tela lê `data-contract="…"` no fonte, não em runtime. */}
        <div data-contract="plat-instalacao">
        <Secao titulo="Instalação do módulo" sub="/ia/install · nWidart">
          {/* `min="sm"` = auto-fill 14rem — o mais perto do minmax(150px) da âncora com token do DS. */}
          <Grid min="sm" gap={2}>
            {([
              [instalacao.migrations, 'migrations'],
              [instalacao.seeders, 'seeders'],
              [instalacao.permissoes, 'permissões'],
              [instalacao.versao ? 'instalado' : 'não instalado', 'situação'],
            ] as Array<[number | string, string]>).map(([v, k]) => (
              <div key={k} className="rounded-[10px] border border-border bg-card px-3 py-2.5">
                <b className="block font-mono text-sm font-semibold tabular-nums text-foreground">{v}</b>
                <small className="mt-0.5 block text-[10.5px] uppercase tracking-wider text-muted-foreground">{k}</small>
              </div>
            ))}
          </Grid>
          {/* Só nascem com `can('superadmin')` REAL — é o gate de `BaseModuleInstallController`
              (mais estreito que `jana.superadmin`). Botão que levaria a 403 é botão que mente. */}
          {instalacao.podeOperar && (
            <Inline gap={2} wrap>
              <Button variant="outline" size="sm" onClick={() => setConfirmar('update')}>Rodar atualização</Button>
              <Button variant="destructive" size="sm" onClick={() => setConfirmar('uninstall')}>Desinstalar módulo</Button>
            </Inline>
          )}
          <p className="m-0 max-w-[82ch] text-[11px] leading-relaxed text-muted-foreground">
            Disparado hoje pelo <code>/manage-modules</code> do superadmin. Desinstalar derruba as tabelas
            <code> jana_*</code> — conversas, memória, metas e apurações — e é irreversível sem backup.
          </p>
        </Secao>
        </div>
      </Stack>

      {/* Confirmação destrutiva da âncora. Navegação FULL (`<a href>`): as rotas de /ia/install
          são GET que rodam migrations e redirecionam — não são resposta Inertia. */}
      <Dialog open={!!confirmar} onOpenChange={(aberto) => !aberto && setConfirmar(null)}>
        <DialogContent className="sm:max-w-[440px]">
          <DialogHeader>
            <DialogTitle className="text-base">{confirmar === 'uninstall' ? 'Desinstalar o módulo Jana' : 'Rodar atualização'}</DialogTitle>
            <DialogDescription>
              {confirmar === 'uninstall'
                ? 'O rollback apaga as tabelas jana_* deste ambiente: conversas, mensagens, fatos de memória, metas, períodos e apurações. Não há lixeira.'
                : 'Roda as migrations pendentes e grava a versão nova no módulo. Nada é apagado.'}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2">
            <Button variant="ghost" onClick={() => setConfirmar(null)}>Cancelar</Button>
            <Button variant={confirmar === 'uninstall' ? 'destructive' : 'default'} asChild>
              <a href={confirmar === 'uninstall' ? '/ia/install/uninstall' : '/ia/install/update'}>
                {confirmar === 'uninstall' ? 'Desinstalar' : 'Atualizar'}
              </a>
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <FabJana contextRoute="/ia/superadmin/metas" />
    </>
  )
}

Plataforma.layout = (page: React.ReactNode) => <AppShellV2 title="Jana — Plataforma">{page}</AppShellV2>
