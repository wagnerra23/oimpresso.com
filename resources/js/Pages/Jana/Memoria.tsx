// @memcofre
//   tela: /copiloto/memoria
//   stories: US-COPI-MEM-005, US-COPI-MEM-008, US-COPI-MEM-012
//   rules: R-COPI-MEM-LGPD-001, R-COPI-MEM-MULTITENANT-001
//   adrs: 0031, 0033, 0035, 0036, 0037
//   tests: tests/Feature/Modules/Copiloto/MemoriaContratoTest, tests/Feature/Modules/Copiloto/MemoriaControllerTest
//   status: implementada
//   module: Copiloto

import React, { useMemo, useState } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { router, useForm } from '@inertiajs/react'
import { Button } from '@/Components/ui/button'
import { Card, CardContent } from '@/Components/ui/card'
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert'
import { Badge } from '@/Components/ui/badge'
// Deriva do próprio componente em vez de repetir a união à mão: variante nova no DS
// entra sozinha, e variante removida vira erro de tipo aqui em vez de classe morta.
type BadgeVariant = NonNullable<React.ComponentProps<typeof Badge>['variant']>
import { Brain, Trash2, Pencil, Save, Search, Settings, X } from 'lucide-react'
import FabJana from './components/FabJana'
import { JanaAreaHeader } from '@/Pages/Jana/components/JanaAreaHeader'
import JanaConfigDrawer from '@/Pages/Jana/_components/JanaConfigDrawer'
import { useJanaConfig } from '@/Pages/Jana/_components/useJanaConfig'
import { Inline } from '@/Components/layout'

interface MemoriaFato {
  id: number
  business_id: number
  user_id: number
  fato: string
  metadata: {
    categoria?: string
    relevancia?: number
    origem?: string
    [key: string]: unknown
  }
  valid_from: string | null
  valid_until: string | null
  score: number | null
}

interface Props {
  memorias: MemoriaFato[]
  businessId: number
  /** Onda 2 da paridade: empresa + `biz=` no header, como no Painel. */
  janaContext?: { businessId: number; businessName: string; userName?: string | null }
  userId: number
}

// Pills soft do DS, não escala crua. As 5 categorias eram `bg-<cor>-100 text-<cor>-800`
// SEM par `dark:` nenhum — chip claro-sobre-claro no tema escuro, nas cinco. O pedido
// [CC] de 2026-08-13 marcou só `acao_pendente` porque o grep dele cobria
// `violet|fuchsia|pink|sky|emerald|rose|amber` e não via `blue|purple|red|gray`; medindo
// aqui, o defeito é do mapa inteiro (+ o fallback abaixo).
//
// O `variant` soft do `Badge` já carrega light+dark no token (`-soft`/`-fg`, inertia.css),
// então não se escreve `dark:` — é o que o próprio componente exige das Pages.
// O mapeamento é por SEMÂNTICA, não por hue: `restricao` restringe (danger),
// `acao_pendente` pende (warning), `meta` informa (info). `preferencia` e `contexto` não
// têm carga semântica — ficam nos dois tons neutros distintos que o DS oferece, o que
// preserva as 5 pills distinguíveis.
const CATEGORIA_LABELS: Record<string, { label: string; variant: BadgeVariant }> = {
  meta:           { label: 'Meta',           variant: 'info' },
  preferencia:    { label: 'Preferência',    variant: 'secondary' },
  restricao:      { label: 'Restrição',      variant: 'danger' },
  contexto:       { label: 'Contexto',       variant: 'neutral' },
  acao_pendente:  { label: 'Ação pendente',  variant: 'warning' },
}

function formatData(iso: string | null): string {
  if (!iso) return '—'
  try {
    return new Date(iso).toLocaleString('pt-BR', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    })
  } catch {
    return iso
  }
}

function FatoCard({ memoria }: { memoria: MemoriaFato }) {
  const [editando, setEditando] = useState(false)
  const [confirmandoApagar, setConfirmandoApagar] = useState(false)
  const { data, setData, patch, processing, reset, errors, clearErrors } = useForm({
    fato: memoria.fato,
    // LGPD: toda correção registra autor + motivo no log de auditoria. O backend
    // rejeita (422) sem motivo — este campo é a conveniência, não a garantia.
    motivo: '',
  })
  const podeSalvar = data.fato.trim() !== '' && data.motivo.trim() !== ''
  const cat = memoria.metadata?.categoria as string | undefined
  const catCfg = (cat && CATEGORIA_LABELS[cat]) || { label: cat || 'sem categoria', variant: 'outline' as BadgeVariant }
  // Escala /10 MANTIDA — decisão [W] 2026-08-07: a produção é a fonte e o protótipo
  // (que desenha 1–5) se adapta. Mudar a escala seria migração de `metadata.relevancia`
  // já gravado, e não há razão de domínio pra isso.
  const rel = memoria.metadata?.relevancia as number | undefined
  const origem = memoria.metadata?.origem as string | undefined

  const onSalvar = () => {
    if (!podeSalvar) return
    patch(`/ia/memoria/${memoria.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        setEditando(false)
        setData('motivo', '') // motivo é por-correção: nunca reaproveita o anterior
      },
    })
  }

  const onCancelar = () => {
    reset()
    clearErrors()
    setEditando(false)
  }

  // Confirmação INLINE, na própria linha — como o protótipo (`apagando === f.id`).
  // O `confirm()` nativo sai do fluxo da página, não dá pra estilizar, é bloqueante
  // e não diz QUAL fato está sendo apagado.
  const onEsquecer = () => {
    setConfirmandoApagar(false)
    router.delete(`/ia/memoria/${memoria.id}`, { preserveScroll: true })
  }

  return (
    <Card>
      <CardContent className="pt-6 space-y-3">
        <div className="flex items-start justify-between gap-3">
          <div className="flex items-center gap-2 flex-wrap">
            <Badge variant={catCfg.variant}>{catCfg.label}</Badge>
            {rel !== undefined && (
              <span className="text-xs text-muted-foreground">
                relevância {rel}/10
              </span>
            )}
            {/* Charter Goal 4: "Mostrar `origem` do fato (chat / brief auto / inserção
                manual) — transparência". O dado já vinha no payload e não era renderizado:
                o titular via O QUE a Jana aprendeu, mas não DE ONDE. */}
            {origem && (
              <span className="text-xs text-muted-foreground">
                · origem: {origem}
              </span>
            )}
            <span className="text-xs text-muted-foreground">
              · desde {formatData(memoria.valid_from)}
            </span>
          </div>
          <div className="flex gap-1">
            {!editando && confirmandoApagar ? (
              <Inline gap={2} align="center">
                <span className="text-xs text-destructive">Apagar é irreversível.</span>
                <Button size="sm" variant="destructive" onClick={onEsquecer}>
                  Apagar
                </Button>
                <Button size="sm" variant="ghost" onClick={() => setConfirmandoApagar(false)}>
                  Manter
                </Button>
              </Inline>
            ) : !editando ? (
              <>
                <Button size="sm" variant="ghost" onClick={() => setEditando(true)} title="Editar">
                  <Pencil className="size-4" />
                </Button>
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => setConfirmandoApagar(true)}
                  title="Esquecer"
                >
                  <Trash2 className="size-4 text-destructive" />
                </Button>
              </>
            ) : (
              <>
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={onSalvar}
                  disabled={processing || !podeSalvar}
                  title={podeSalvar ? 'Salvar' : 'Preencha o fato e o motivo da correção'}
                >
                  <Save className="size-4 text-success" />
                </Button>
                <Button size="sm" variant="ghost" onClick={onCancelar} title="Cancelar">
                  <X className="size-4" />
                </Button>
              </>
            )}
          </div>
        </div>

        {!editando ? (
          <p className="text-sm">{memoria.fato}</p>
        ) : (
          <div className="space-y-2">
            <textarea
              className="w-full text-sm rounded-md border p-2 min-h-[80px]"
              value={data.fato}
              onChange={(e) => setData('fato', e.target.value)}
              disabled={processing}
              aria-label="Texto do fato"
            />
            {errors.fato && <p className="text-xs text-destructive">{errors.fato}</p>}

            <label className="block text-xs text-muted-foreground">
              Motivo da correção
              <input
                className="mt-1 w-full text-sm rounded-md border p-2"
                value={data.motivo}
                onChange={(e) => setData('motivo', e.target.value)}
                disabled={processing}
                placeholder="fica no log de auditoria"
                aria-label="Motivo da correção"
              />
            </label>
            {errors.motivo && <p className="text-xs text-destructive">{errors.motivo}</p>}

            <p className="text-xs text-muted-foreground">
              Toda correção registra autor, horário e motivo.
            </p>
          </div>
        )}
      </CardContent>
    </Card>
  )
}

const TODAS = '__todas__'

function Memoria({ memorias, janaContext }: Props) {
  // Config da Jana — mesmo hook e mesmo drawer do Painel (onda 4 da paridade).
  const [configAberto, setConfigAberto] = useState(false)
  const { config, alternarAnalise } = useJanaConfig()

  const [busca, setBusca] = useState('')
  const [categoria, setCategoria] = useState<string>(TODAS)

  // Chips DERIVADOS do dado, não da lista literal do protótipo.
  // O protótipo lista ["preferência","operação","financeiro","cliente","sazonalidade","equipe"],
  // que é a taxonomia do MOCK do Martinho — a de produção é outra (CATEGORIA_LABELS:
  // meta/preferencia/restricao/contexto/acao_pendente). Copiar a lista de lá seria importar
  // uma solução cuja premissa não vale aqui (§5 2026-07-16); o que se traduz é o COMPORTAMENTO
  // (filtrar por categoria), não a lista de categorias.
  const categorias = useMemo(() => {
    const vistas = new Set<string>()
    memorias.forEach((m) => vistas.add((m.metadata?.categoria as string | undefined) || 'sem_categoria'))
    return Array.from(vistas).sort((a, b) =>
      (CATEGORIA_LABELS[a]?.label || a).localeCompare(CATEGORIA_LABELS[b]?.label || b, 'pt-BR'),
    )
  }, [memorias])

  // FILTRA (protótipo) em vez de AGRUPAR (o que a produção fazia — é outra coisa:
  // agrupar mostra tudo sempre, filtrar reduz a lista ao que você procura).
  const lista = useMemo(() => {
    const termo = busca.trim().toLowerCase()
    return memorias.filter((m) => {
      const cat = (m.metadata?.categoria as string | undefined) || 'sem_categoria'
      return (categoria === TODAS || cat === categoria) && m.fato.toLowerCase().includes(termo)
    })
  }, [memorias, busca, categoria])

  const semNada = memorias.length === 0
  const filtradoAZero = !semNada && lista.length === 0

  const limparFiltro = () => {
    setBusca('')
    setCategoria(TODAS)
  }

  return (
    <>
      {/* Wagner 2026-05-25: JanaAreaHeader adicionado pós-audit browser MCP —
          /ia/memoria estava sem header da área Jana (vs Brief/Dashboard/etc).
          Consistência UX entre todos os ghosts. */}
      {/* Onda 4 da paridade da área Jana: o "Configurar" existia SÓ no Painel — na
          Conversa e na Memória o header não tinha ação nenhuma, embora a âncora
          (`jana-merge.jsx` §JmConfigDrawer) o ponha no header da ÁREA, não de uma
          aba. Drawer e hook já eram reusáveis (`useJanaConfig` mora fora do
          componente de propósito, pra não quebrar react-refresh); só faltava montar. */}
      <JanaAreaHeader
        active="memoria"
        businessName={janaContext?.businessName}
        businessId={janaContext?.businessId}
        actions={
          <Button
            variant="outline"
            size="sm"
            onClick={() => setConfigAberto(true)}
            aria-haspopup="dialog"
            aria-expanded={configAberto}
          >
            <Settings className="h-3.5 w-3.5" /> Configurar
          </Button>
        }
      />
      <JanaConfigDrawer
        open={configAberto}
        onClose={() => setConfigAberto(false)}
        config={config}
        onAlternarAnalise={alternarAnalise}
      />

      <div className="max-w-4xl mx-auto p-6 space-y-6">
        {/* Título próprio REMOVIDO na onda de fusão (2026-08-08, US-COPI-148).
            Três motivos independentes, cada um suficiente:
            (a) era o SEGUNDO `<h1>` da página — o `<PageHeader>` canon já provê
                o dele ("Jana · Analista IA") logo acima, e dois h1 quebram a
                estrutura de heading que o leitor de tela usa pra navegar;
            (b) dizia "O Copiloto", nome que o #5401 padronizou pra "Jana";
            (c) o protótipo (`JmMemoria`, jana-merge.jsx) abre direto no alerta
                LGPD — não tem título de tela.
            O ícone `Brain` continua em uso no empty state abaixo. */}

        {/* Copy literal do protótipo (JmMemoria, prototipo-ui/cowork/jana-merge.jsx) —
            §1.5 do pacote exige copy literal, não paráfrase. */}
        <Alert>
          <AlertTitle>Memória da Jana — LGPD Art. 18</AlertTitle>
          <AlertDescription>
            Você vê, corrige e apaga qualquer fato que a Jana aprendeu sobre o seu negócio.
            Toda alteração registra autor e motivo no log de auditoria.
          </AlertDescription>
        </Alert>

        {!semNada && (
          <Inline gap={3} align="center" wrap>
            <label className="relative flex-1 min-w-[220px]">
              <Search className="absolute left-2 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
              <input
                className="w-full text-sm rounded-md border py-2 pl-8 pr-2"
                value={busca}
                onChange={(e) => setBusca(e.target.value)}
                placeholder="Buscar em fatos…"
                aria-label="Buscar em fatos"
              />
            </label>

            <Inline gap={1} align="center" wrap role="group" aria-label="Filtrar por categoria">
              <Button
                size="sm"
                variant={categoria === TODAS ? 'secondary' : 'ghost'}
                onClick={() => setCategoria(TODAS)}
                aria-pressed={categoria === TODAS}
              >
                todas
              </Button>
              {categorias.map((cat) => (
                <Button
                  key={cat}
                  size="sm"
                  variant={categoria === cat ? 'secondary' : 'ghost'}
                  onClick={() => setCategoria(cat)}
                  aria-pressed={categoria === cat}
                >
                  {CATEGORIA_LABELS[cat]?.label || cat}
                </Button>
              ))}
            </Inline>

            <span className="text-sm text-muted-foreground">
              {lista.length} de {memorias.length} {memorias.length === 1 ? 'fato' : 'fatos'}
            </span>
          </Inline>
        )}

        {semNada || filtradoAZero ? (
          <Card>
            <CardContent className="pt-6 text-center text-muted-foreground space-y-3">
              {semNada ? <Brain className="size-12 mx-auto opacity-30" /> : <Search className="size-12 mx-auto opacity-30" />}
              {/* Copy literal do protótipo — os DOIS empty states (JmMemoria: `semNada` × `filtrado`).
                  Produção tinha só um, e com texto diferente. */}
              <p className="font-medium text-foreground">
                {semNada
                  ? 'A Jana ainda não aprendeu nada sobre o seu negócio'
                  : 'Nenhum fato com esse filtro'}
              </p>
              <p className="text-sm">
                {semNada
                  ? 'Ela guarda o que você conta durante a conversa — rotinas, preferências, jeito de cobrar. Comece perguntando algo na aba Conversa.'
                  : 'Nada casa com a busca e a categoria escolhidas.'}
              </p>
              {filtradoAZero && (
                <Button size="sm" variant="ghost" onClick={limparFiltro}>
                  Limpar filtro
                </Button>
              )}
            </CardContent>
          </Card>
        ) : (
          <div className="space-y-2">
            {lista.map((m) => <FatoCard key={m.id} memoria={m} />)}
          </div>
        )}

        <FabJana />
      </div>
    </>
  )
}

Memoria.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Jana — Memória" breadcrumbItems={[{ label: 'Jana' }, { label: 'Memória' }]}>
    {page}
  </AppShellV2>
)

export default Memoria
