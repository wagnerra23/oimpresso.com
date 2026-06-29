// @memcofre tela=/nfe-brasil/manifestacao module=NfeBrasil
//   us: US-NFE-052 (manifestação destinatário UI)
//   adrs: 0039 (cockpit), 0093 (multi-tenant Tier 0), 0116 (caso Gold)
//   visual-comparison: memory/requisitos/NfeBrasil/manifestacao-visual-comparison.md (approved 2026-05-09)
//   runbook: memory/requisitos/NfeBrasil/RUNBOOK-manifestacao.md
import AppShellV2 from '@/Layouts/AppShellV2'
import { Head, router, usePage } from '@inertiajs/react'
import { useEffect, useMemo, useState } from 'react'
import {
  CheckCircle2,
  XCircle,
  Ban,
  RefreshCw,
  Clock,
  FileText,
  Inbox,
  Search,
  Loader2,
} from 'lucide-react'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { Checkbox } from '@/Components/ui/checkbox'
import { Input } from '@/Components/ui/input'
import { toast } from 'sonner'
import { LinkedItens } from './_components/LinkedItens'
import { LinkedFornecedor } from './_components/LinkedFornecedor'
import { LinkedHistorico } from './_components/LinkedHistorico'

interface Dfe {
  id: number
  chave_44: string
  cnpj_emitente: string
  nome_emitente: string | null
  valor_total: number
  data_emissao: string | null
  status_manifestacao: 'pendente' | 'ciencia' | 'confirmada' | 'desconhecida' | 'nao_realizada'
  manifestado_em: string | null
  prazo_confirmacao_em: string | null
  dias_ate_prazo: number | null
}

interface Props {
  itens: { data: Dfe[]; meta: { total: number; current_page: number; last_page: number } }
  filters: { status: string | null; q: string | null }
  kpis: { pendentes: number; vencendo_7d: number; confirmadas_mes: number }
  nsuState: { last_nsu: number; ultimo_check_em: string | null; ultimo_lote_count: number } | null
  permissions: { canManage: boolean }
}

interface FlashProps {
  flash?: { success?: string; error?: string }
}

const STATUS_LABEL: Record<Dfe['status_manifestacao'], string> = {
  pendente: 'Pendente',
  ciencia: 'Ciência',
  confirmada: 'Confirmada',
  desconhecida: 'Desconhecida',
  nao_realizada: 'Não realizada',
}

const STATUS_BADGE: Record<Dfe['status_manifestacao'], string> = {
  pendente: 'bg-amber-50 text-amber-700 border border-amber-200',
  ciencia: 'bg-slate-50 text-slate-700 border border-slate-200',
  confirmada: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
  desconhecida: 'bg-slate-50 text-slate-600 border border-slate-200',
  nao_realizada: 'bg-orange-50 text-orange-700 border border-orange-200',
}

function formatCnpj(cnpj: string): string {
  if (!cnpj || cnpj.length !== 14) return cnpj
  return `${cnpj.slice(0, 2)}.${cnpj.slice(2, 5)}.${cnpj.slice(5, 8)}/${cnpj.slice(8, 12)}-${cnpj.slice(12)}`
}

function formatBrl(value: number): string {
  return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function PrazoBadge({ dias }: { dias: number | null }) {
  if (dias === null) return <span className="text-xs text-muted-foreground">—</span>
  if (dias < 0) {
    return (
      <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-mono tabular-nums bg-destructive-soft text-destructive-fg border border-destructive/20">
        <Clock size={12} />
        Vencido {Math.abs(dias)}d
      </span>
    )
  }
  if (dias <= 7) {
    return (
      <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-mono tabular-nums bg-destructive-soft text-destructive-fg border border-destructive/20">
        <Clock size={12} />
        {dias}d
      </span>
    )
  }
  if (dias <= 30) {
    return (
      <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-mono tabular-nums bg-amber-50 text-amber-700 border border-amber-200">
        <Clock size={12} />
        {dias}d
      </span>
    )
  }
  return <span className="text-xs font-mono tabular-nums text-muted-foreground">{dias}d</span>
}

function Index({ itens, filters, kpis, nsuState, permissions }: Props) {
  const { props } = usePage<FlashProps>()
  const [foco, setFoco] = useState<Dfe | null>(itens.data[0] ?? null)
  const [selecionados, setSelecionados] = useState<Set<number>>(new Set())
  const [q, setQ] = useState(filters.q ?? '')
  const [statusFilter, setStatusFilter] = useState(filters.status ?? 'pendente')
  const [submitting, setSubmitting] = useState(false)

  // Flash messages
  useEffect(() => {
    if (props.flash?.success) toast.success(props.flash.success)
    if (props.flash?.error) toast.error(props.flash.error)
  }, [props.flash?.success, props.flash?.error])

  // Persistência localStorage (DESIGN.md §12)
  useEffect(() => {
    try {
      localStorage.setItem('oimpresso.nfebrasil.manifestacao.filter', statusFilter)
    } catch (e) {
      // ignore
    }
  }, [statusFilter])

  // Atalhos teclado (canon Cockpit J/K + verbos C/D/R desta tela)
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return
      if (!foco) return
      const idx = itens.data.findIndex((i) => i.id === foco.id)

      if (e.key === 'j' && idx < itens.data.length - 1) {
        e.preventDefault()
        setFoco(itens.data[idx + 1])
      }
      if (e.key === 'k' && idx > 0) {
        e.preventDefault()
        setFoco(itens.data[idx - 1])
      }
      if (e.key === 'c' && foco.status_manifestacao === 'pendente' && permissions.canManage) {
        e.preventDefault()
        confirmar(foco.id)
      }
      if (e.key === 'd' && foco.status_manifestacao === 'pendente' && permissions.canManage) {
        e.preventDefault()
        desconhecer(foco.id)
      }
      if (e.key === 'r' && foco.status_manifestacao === 'pendente' && permissions.canManage) {
        e.preventDefault()
        naoRealizada(foco.id)
      }
      if (e.key === '/') {
        e.preventDefault()
        document.getElementById('busca-manifestacao')?.focus()
      }
    }
    window.addEventListener('keydown', handler)
    return () => window.removeEventListener('keydown', handler)
  }, [foco, itens.data, permissions.canManage])

  const post = (url: string, data: object = {}, msg = 'Processando...') => {
    setSubmitting(true)
    router.post(url, data as never, {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => setSubmitting(false),
    })
  }

  const cienciar = (id: number) => {
    if (!confirm('Registrar Ciência da Operação? Indica que você viu a NF-e.')) return
    post(`/nfe-brasil/manifestacao/${id}/cienciar`)
  }

  const confirmar = (id: number) => {
    if (!confirm('Confirmar recebimento desta NF-e? Esta ação registra evento 220 SEFAZ.')) return
    post(`/nfe-brasil/manifestacao/${id}/confirmar`)
  }

  const desconhecer = (id: number) => {
    const just = prompt('Justificativa (≥15 caracteres) — exigida pela NT 2014.002:')
    if (!just) return
    if (just.trim().length < 15) {
      toast.error('Justificativa precisa ter pelo menos 15 caracteres.')
      return
    }
    post(`/nfe-brasil/manifestacao/${id}/desconhecer`, { justificativa: just })
  }

  const naoRealizada = (id: number) => {
    const just = prompt('Justificativa (≥15 caracteres) — exigida pela NT 2014.002:')
    if (!just) return
    if (just.trim().length < 15) {
      toast.error('Justificativa precisa ter pelo menos 15 caracteres.')
      return
    }
    post(`/nfe-brasil/manifestacao/${id}/nao-realizada`, { justificativa: just })
  }

  const bulkConfirmar = () => {
    const ids = Array.from(selecionados)
    if (ids.length === 0) return
    if (!confirm(`Confirmar recebimento de ${ids.length} NF-e? Cada uma vira evento 220 SEFAZ — irreversível via UI.`)) return
    post('/nfe-brasil/manifestacao/bulk/confirmar', { ids })
    setSelecionados(new Set())
  }

  const syncNow = () => {
    post('/nfe-brasil/manifestacao/sync-now')
  }

  const aplicarFiltros = () => {
    router.get(
      '/nfe-brasil/manifestacao',
      { status: statusFilter, q: q || undefined },
      { preserveScroll: true, preserveState: true, only: ['itens', 'filters', 'kpis'] },
    )
  }

  const toggleSelecionar = (id: number) => {
    setSelecionados((s) => {
      const n = new Set(s)
      if (n.has(id)) n.delete(id)
      else n.add(id)
      return n
    })
  }

  const toggleSelecionarTodos = () => {
    const pendentesIds = itens.data.filter((i) => i.status_manifestacao === 'pendente').map((i) => i.id)
    setSelecionados((s) => (s.size === pendentesIds.length ? new Set() : new Set(pendentesIds)))
  }

  const ultimaSync = useMemo(() => {
    if (!nsuState?.ultimo_check_em) return 'Nunca'
    return new Date(nsuState.ultimo_check_em).toLocaleString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    })
  }, [nsuState?.ultimo_check_em])

  const todosPendentesSelecionados =
    selecionados.size > 0 &&
    selecionados.size === itens.data.filter((i) => i.status_manifestacao === 'pendente').length

  return (
    <>
      <Head title="Manifestação do Destinatário" />

      {/* Sticky bulk toolbar quando há seleção */}
      {selecionados.size > 0 && (
        <div className="sticky top-0 z-10 border-b border-border bg-background/80 backdrop-blur-sm py-3 px-4 -mx-4 mb-4">
          <div className="flex items-center justify-between max-w-7xl mx-auto">
            <div className="text-sm">
              <span className="font-mono tabular-nums text-2xl font-semibold mr-2">{selecionados.size}</span>
              NF-e selecionada(s)
            </div>
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={() => setSelecionados(new Set())}>
                Limpar seleção
              </Button>
              <Button
                size="sm"
                className="bg-emerald-600 hover:bg-emerald-700 text-white"
                onClick={bulkConfirmar}
                disabled={submitting}
              >
                {submitting ? <Loader2 className="animate-spin" size={14} /> : <CheckCircle2 size={14} />}
                Confirmar selecionadas
              </Button>
            </div>
          </div>
        </div>
      )}

      <div className="px-4 py-6 max-w-full">
        {/* PageHeader */}
        <div className="flex items-start justify-between mb-6">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight">Notas recebidas</h1>
            <p className="text-sm text-muted-foreground mt-1">
              Confirme o recebimento das NF-e que fornecedores emitiram contra você. Prazo SEFAZ: 180 dias.
            </p>
            <p className="text-xs text-muted-foreground mt-1">
              Última busca SEFAZ: <span className="font-mono">{ultimaSync}</span>
              {nsuState && nsuState.ultimo_lote_count > 0 && (
                <span className="ml-2">· {nsuState.ultimo_lote_count} novas no último lote</span>
              )}
            </p>
          </div>
          <Button variant="outline" size="sm" onClick={syncNow} disabled={submitting || !permissions.canManage}>
            <RefreshCw size={14} className={submitting ? 'animate-spin' : ''} />
            Buscar agora
          </Button>
        </div>

        {/* KPI cards */}
        <div className="grid grid-cols-3 gap-4 mb-6">
          <div className="p-4 rounded-lg border border-border bg-card shadow-sm">
            <div className="text-xs uppercase tracking-widest text-muted-foreground">Pendentes</div>
            <div className="text-4xl font-semibold tabular-nums mt-1">{kpis.pendentes}</div>
          </div>
          <div className="p-4 rounded-lg border border-amber-200 bg-amber-50/50 shadow-sm dark:bg-amber-900/10 dark:border-amber-900/30">
            <div className="text-xs uppercase tracking-widest text-amber-700 dark:text-amber-300">Vencendo em 7 dias</div>
            <div className="text-4xl font-semibold tabular-nums mt-1 text-amber-900 dark:text-amber-100">
              {kpis.vencendo_7d}
            </div>
          </div>
          <div className="p-4 rounded-lg border border-border bg-card shadow-sm">
            <div className="text-xs uppercase tracking-widest text-muted-foreground">Confirmadas no mês</div>
            <div className="text-4xl font-semibold tabular-nums mt-1 text-success">
              {kpis.confirmadas_mes}
            </div>
          </div>
        </div>

        {/* Filtros */}
        <div className="flex items-center gap-2 mb-4">
          <div className="flex gap-1">
            {(['pendente', 'confirmada', 'desconhecida', 'nao_realizada', ''] as const).map((s) => (
              <button
                key={s || 'todos'}
                onClick={() => {
                  setStatusFilter(s)
                  router.get(
                    '/nfe-brasil/manifestacao',
                    { status: s || undefined, q: q || undefined },
                    { preserveScroll: true, preserveState: true, only: ['itens', 'filters', 'kpis'] },
                  )
                }}
                className={`px-3 py-1.5 text-sm rounded-md transition-colors ${
                  statusFilter === s
                    ? 'bg-accent-soft text-foreground border border-accent-2'
                    : 'text-muted-foreground hover:bg-panel-2'
                }`}
              >
                {s ? STATUS_LABEL[s as Dfe['status_manifestacao']] : 'Todas'}
              </button>
            ))}
          </div>
          <div className="ml-auto relative w-64">
            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <Input
              id="busca-manifestacao"
              type="text"
              placeholder="Buscar CNPJ ou nome emitente"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && aplicarFiltros()}
              className="pl-9"
            />
          </div>
        </div>

        {/* Master/Detail layout */}
        <div className="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-4">
          {/* Lista (master) */}
          <div className="rounded-lg border border-border bg-card overflow-hidden">
            {itens.data.length === 0 ? (
              <div className="py-16 px-6 text-center">
                <Inbox size={48} className="mx-auto text-muted-foreground mb-3" />
                <h3 className="text-lg font-semibold">Nenhuma NF-e recebida</h3>
                <p className="text-sm text-muted-foreground mt-1 max-w-md mx-auto">
                  O job de Distribuição DFe roda diariamente às 06:15 BRT. Você pode forçar uma busca agora se há
                  NF-e que esperava receber.
                </p>
                {permissions.canManage && (
                  <Button variant="outline" size="sm" className="mt-4" onClick={syncNow}>
                    <RefreshCw size={14} />
                    Buscar agora
                  </Button>
                )}
              </div>
            ) : (
              <table className="w-full text-sm">
                <thead className="text-xs text-muted-foreground border-b border-border">
                  <tr>
                    <th className="px-3 py-2 text-left w-8">
                      {permissions.canManage && (
                        <Checkbox
                          checked={todosPendentesSelecionados}
                          onCheckedChange={toggleSelecionarTodos}
                          aria-label="Selecionar todas pendentes"
                        />
                      )}
                    </th>
                    <th className="px-3 py-2 text-left">Emitente</th>
                    <th className="px-3 py-2 text-right">Valor</th>
                    <th className="px-3 py-2 text-left">Status</th>
                    <th className="px-3 py-2 text-center">Prazo</th>
                    <th className="px-3 py-2 text-right">Ações</th>
                  </tr>
                </thead>
                <tbody>
                  {itens.data.map((dfe) => {
                    const focada = foco?.id === dfe.id
                    const selecionada = selecionados.has(dfe.id)
                    return (
                      <tr
                        key={dfe.id}
                        onClick={() => setFoco(dfe)}
                        className={`border-b border-border last:border-b-0 cursor-pointer transition-colors hover:bg-panel-2 ${
                          focada ? 'bg-accent-soft' : ''
                        } ${selecionada ? 'bg-emerald-50/50 border-l-2 border-l-emerald-500 dark:bg-emerald-900/10' : ''}`}
                      >
                        <td className="px-3 py-3" onClick={(e) => e.stopPropagation()}>
                          {permissions.canManage && dfe.status_manifestacao === 'pendente' && (
                            <Checkbox
                              checked={selecionada}
                              onCheckedChange={() => toggleSelecionar(dfe.id)}
                              aria-label={`Selecionar NF-e ${dfe.chave_44}`}
                            />
                          )}
                        </td>
                        <td className="px-3 py-3">
                          <div className="font-medium">{dfe.nome_emitente ?? '—'}</div>
                          <div className="font-mono text-xs text-muted-foreground">
                            {formatCnpj(dfe.cnpj_emitente)}
                          </div>
                        </td>
                        <td className="px-3 py-3 text-right font-mono tabular-nums">
                          {formatBrl(dfe.valor_total)}
                        </td>
                        <td className="px-3 py-3">
                          <span
                            className={`inline-flex px-2 py-0.5 rounded text-xs ${STATUS_BADGE[dfe.status_manifestacao]}`}
                          >
                            {STATUS_LABEL[dfe.status_manifestacao]}
                          </span>
                        </td>
                        <td className="px-3 py-3 text-center">
                          <PrazoBadge dias={dfe.dias_ate_prazo} />
                        </td>
                        <td className="px-3 py-3" onClick={(e) => e.stopPropagation()}>
                          {dfe.status_manifestacao === 'pendente' && permissions.canManage && (
                            <div className="flex justify-end gap-1">
                              <Button
                                size="sm"
                                variant="outline"
                                className="h-7 px-2"
                                onClick={() => confirmar(dfe.id)}
                                disabled={submitting}
                                title="Confirmar (atalho: C)"
                              >
                                <CheckCircle2 size={14} className="text-success" />
                                <span className="ml-1 text-xs">Confirmar</span>
                              </Button>
                              <Button
                                size="sm"
                                variant="ghost"
                                className="h-7 px-2"
                                onClick={() => desconhecer(dfe.id)}
                                disabled={submitting}
                                title="Desconhecer (atalho: D)"
                              >
                                <XCircle size={14} className="text-slate-500" />
                              </Button>
                              <Button
                                size="sm"
                                variant="ghost"
                                className="h-7 px-2"
                                onClick={() => naoRealizada(dfe.id)}
                                disabled={submitting}
                                title="Não realizada (atalho: R)"
                              >
                                <Ban size={14} className="text-orange-500" />
                              </Button>
                            </div>
                          )}
                          {dfe.status_manifestacao !== 'pendente' && (
                            <span className="text-xs text-muted-foreground">
                              <FileText size={12} className="inline mr-1" />
                              Manifestada
                            </span>
                          )}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            )}
          </div>

          {/* Apps Vinculados (coluna direita — visível em xl:) */}
          <div className="hidden xl:block space-y-3">
            {foco ? (
              <>
                <LinkedFornecedor dfe={foco} />
                <LinkedItens dfeId={foco.id} />
                <LinkedHistorico dfeId={foco.id} />
              </>
            ) : (
              <div className="rounded-lg border border-border bg-card p-4 text-sm text-muted-foreground">
                Selecione uma NF-e na lista pra ver itens, fornecedor e histórico.
              </div>
            )}
          </div>
        </div>

        {/* Atalhos hint */}
        <div className="mt-6 text-xs text-muted-foreground">
          Atalhos: <kbd className="px-1.5 py-0.5 rounded bg-muted text-foreground font-mono">J</kbd>/
          <kbd className="px-1.5 py-0.5 rounded bg-muted text-foreground font-mono">K</kbd> navegar ·{' '}
          <kbd className="px-1.5 py-0.5 rounded bg-muted text-foreground font-mono">C</kbd> confirmar ·{' '}
          <kbd className="px-1.5 py-0.5 rounded bg-muted text-foreground font-mono">D</kbd> desconhecer ·{' '}
          <kbd className="px-1.5 py-0.5 rounded bg-muted text-foreground font-mono">R</kbd> não realizada ·{' '}
          <kbd className="px-1.5 py-0.5 rounded bg-muted text-foreground font-mono">/</kbd> buscar
        </div>
      </div>
    </>
  )
}

Index.layout = (page: React.ReactNode) => <AppShellV2>{page}</AppShellV2>

export default Index
