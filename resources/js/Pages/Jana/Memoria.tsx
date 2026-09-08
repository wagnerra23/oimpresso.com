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
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert'
import { Badge } from '@/Components/ui/badge'
// Deriva do próprio componente em vez de repetir a união à mão: variante nova no DS
// entra sozinha, e variante removida vira erro de tipo aqui em vez de classe morta.
type BadgeVariant = NonNullable<React.ComponentProps<typeof Badge>['variant']>
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import EmptyState from '@/Components/shared/EmptyState'
import { Search, Settings } from 'lucide-react'
import FabJana from './_components/FabJana'
import { JanaAreaHeader } from '@/Pages/Jana/_components/JanaAreaHeader'
import JanaConfigDrawer from '@/Pages/Jana/_components/JanaConfigDrawer'
import { JanaPlanoBadge } from '@/Pages/Jana/_components/JanaPlanoBadge'
import { useJanaPro } from '@/Pages/Jana/_components/useJanaPro'
import { useJanaConfig } from '@/Pages/Jana/_components/useJanaConfig'
import { Box, Inline, Stack, Text } from '@/Components/layout'

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
// têm carga semântica — ficam nos dois neutros, o que preserva as 5 pills distinguíveis.
//
// COMO os dois neutros se distinguem (medido 2026-08-26, OKLab, tokens de inertia.css):
//   `neutral`   = bg-muted + `border-border` (borda VISÍVEL) + texto muted-foreground
//   `secondary` = bg-secondary + `border-transparent` (herda o cva base) + texto near-fg
// No tema escuro os dois FUNDOS são o mesmo valor (ΔE 0.0000): quem separa é a BORDA e o
// brilho do texto (ΔE 0.2250), não o fundo. No claro os fundos ficam a ΔE 0.0110.
// A distinção existe, mas é sutil — registrado aqui pra ninguém "consertar" no escuro
// achando que é bug de fundo: não é, é o desenho dos tokens.
//
// Por que `preferencia` NÃO migra pras alternativas óbvias:
//   `success`  → carrega tom positivo, e preferir não é dar certo (contraria a regra acima).
//   `outline`  → JÁ É o fallback de categoria desconhecida (linha ~98): "Preferência" ficaria
//                visualmente idêntica a "sem categoria".
//   `neutral`  → colide com `contexto`, some uma das 5.
// Um segundo neutro soft próprio no DS resolveria melhor, mas token novo é Tier 0 [W].
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
  const motivoId = `memoria-motivo-${memoria.id}`

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

  // Onda 4 da paridade — a LINHA do fato toma a forma da âncora (`.jm-fato`,
  // jana-merge.css). Era `Card > CardContent pt-6 space-y-3`, empilhado com a meta
  // ACIMA do texto e ações em botão-ícone; a âncora é uma LINHA: corpo à esquerda
  // (texto → meta), ações em TEXTO à direita, tudo dentro de uma superfície rasa.
  //
  // Deltas MEDIDOS contra a âncora, e por que cada um fica (escolha de técnica minha,
  // §5 2026-08-24 — o que muda pixel de contrato vai medido no visual-comparison):
  //   raio     `rounded="lg"` = 8px × 10px na âncora. A âncora usa 10px CRU, fora da
  //            própria rampa dela (`--radius-lg: 12px` no cockpit); o token do DS é o
  //            que vale aqui, e 2px é o preço declarado.
  //   padding  `p={3}` = 12px × `11px 13px`. A escala do `Box` é enumerada por CVA de
  //            propósito (recusa px cru em tempo de compilação) — 12/12 é o degrau.
  //   pill     `Badge` = `text-xs` (12px) × 10.5px da `.jm-tag`. Padding e raio batem
  //            exatos (`px-2 py-0.5` = 2px 8px · full). Tamanho é do componente do DS.
  return (
    <Box bg="card" border rounded="lg" p={3}>
      {!editando ? (
        <Inline gap={3} align="start">
          <Stack gap={2} className="flex-1 min-w-0">
            {/* `size="lg"` = `--fs-4` (13.5px) com leading 1.45 — a âncora é 13px/1.5.
                A rampa é a âncora ÚNICA de tipografia (ADR 0253); px cru não entra. */}
            <Text size="lg" className="text-pretty">{memoria.fato}</Text>

            {/* Meta numa linha só, mono, na ORDEM da âncora: pill · origem · desde ·
                relevância. A produção trazia relevância logo após a pill e prefixava
                cada item com `·` — separador que o `gap` do flex já faz. */}
            <Inline gap={3} align="center" wrap>
              <Badge variant={catCfg.variant}>{catCfg.label}</Badge>
              {/* Charter Goal 4: "Mostrar `origem` do fato (chat / brief auto / inserção
                  manual) — transparência". */}
              {origem && (
                <Text as="span" size="xs" family="mono" tone="muted">origem: {origem}</Text>
              )}
              <Text as="span" size="xs" family="mono" tone="muted">
                desde {formatData(memoria.valid_from)}
              </Text>
              {rel !== undefined && (
                <Text as="span" size="xs" family="mono" tone="muted">relevância {rel}/10</Text>
              )}
              {/* ⚠️ AUSENTE de propósito: `editado por … · motivo`. A âncora mostra o rastro
                  da última edição AQUI, e a produção GRAVA mais do que mostra (autor + motivo
                  + PII redigida, UC-MEM-03/04) — mas o dado não chega ao componente: o payload
                  é `MemoriaPersistida::toArray()`, DTO `final readonly` de 8 chaves, e o
                  Controller não consulta `activity_log`. É backend (ordem 1 do
                  Memoria-visual-comparison), não render — inventar aqui seria fingir. */}
            </Inline>
          </Stack>

          {/* Ações em TEXTO, `h-6` (= os 24px da âncora), ghost, à direita. Eram
              botão-ícone (Pencil/Trash2): ícone sozinho obriga hover pra saber o que faz,
              e num fluxo LGPD "Apagar" precisa se anunciar. */}
          <div className="shrink-0">
            {confirmandoApagar ? (
              <Inline gap={2} align="center">
                <span className="text-xs text-destructive">Apagar é irreversível.</span>
                <Button size="xs" variant="destructive" onClick={onEsquecer}>
                  Apagar
                </Button>
                <Button size="xs" variant="ghost" onClick={() => setConfirmandoApagar(false)}>
                  Manter
                </Button>
              </Inline>
            ) : (
              <Inline gap={2} align="center">
                <Button size="xs" variant="ghost" onClick={() => setEditando(true)}>
                  Editar
                </Button>
                <Button
                  size="xs"
                  variant="ghost"
                  className="text-destructive"
                  onClick={() => setConfirmandoApagar(true)}
                >
                  Apagar
                </Button>
              </Inline>
            )}
          </div>
        </Inline>
      ) : (
        <Stack gap={2}>
          {/* `rows` em vez de `min-h-[80px]`: `.cw-input.cw-textarea` mora UNLAYERED
              (cowork-fields.css) e vence utilitária Tailwind de `@layer utilities` —
              o `min-h-*` seria ignorado em silêncio, como o `pl-9` da busca de Clientes. */}
          <Textarea
            rows={4}
            value={data.fato}
            onChange={(e) => setData('fato', e.target.value)}
            disabled={processing}
            aria-label="Texto do fato"
          />
          {errors.fato && <p className="text-xs text-destructive">{errors.fato}</p>}

          {/* ⚠️ A âncora edita também `Categoria` e `Relevância` (dois `<select>`); aqui só o
              texto. NÃO é omissão de forma: o `MemoriaController@update` valida `fato` e
              `motivo` e repassa `metadata` sem contrato — expor os campos sem o servidor
              honrá-los seria UI que promete o que o backend não cumpre. Fila: ordem 2 do
              `memory/requisitos/Jana/Memoria-visual-comparison.md`. */}

          {/* Label CANON associada por `htmlFor`/`id` em vez de envolver o campo: o
              `.cw-label` é `display:flex`, então envolver poria label e input lado a
              lado. O `id` carrega o `memoria.id` porque a tela renderiza N cards. */}
          <div>
            <Label htmlFor={motivoId}>Motivo da correção</Label>
            <Input
              id={motivoId}
              className="mt-1"
              value={data.motivo}
              onChange={(e) => setData('motivo', e.target.value)}
              disabled={processing}
              placeholder="fica no log de auditoria"
              aria-label="Motivo da correção"
            />
          </div>
          {errors.motivo && <p className="text-xs text-destructive">{errors.motivo}</p>}

          {/* Rodapé da âncora (`.jm-fato-edit-f`): a frase e os DOIS botões na mesma
              linha, em texto. O `title` do Salvar é só-da-viva e fica — ele explica
              POR QUE o botão está desabilitado, coisa que a âncora não faz. */}
          <Inline gap={2} align="center" wrap>
            <span className="flex-1 min-w-0 text-xs text-muted-foreground">
              Toda correção registra autor, horário e motivo.
            </span>
            <Button
              size="xs"
              onClick={onSalvar}
              disabled={processing || !podeSalvar}
              title={podeSalvar ? 'Salvar' : 'Preencha o fato e o motivo da correção'}
            >
              Salvar
            </Button>
            <Button size="xs" variant="ghost" onClick={onCancelar}>
              Cancelar
            </Button>
          </Inline>
        </Stack>
      )}
    </Box>
  )
}

const TODAS = '__todas__'

function Memoria({ memorias, janaContext }: Props) {
  // Config da Jana — mesmo hook e mesmo drawer do Painel (onda 4 da paridade).
  const [configAberto, setConfigAberto] = useState(false)
  const { config, alternarAnalise } = useJanaConfig()
  // Tier do pacote (`jana_pro_module`), não estado do cliente — ver JanaPlanoBadge.
  const pro = useJanaPro()

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
          <>
            {/* Selo de plano — ANTES de Configurar, como na âncora
                (`chat-jana.jsx:217`: `{plano}` precede os botões da zona direita). */}
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

      {/* Onda 4 da paridade — LARGURA. Era `max-w-4xl mx-auto` (896px numa viewport de
          2560): a Memória era a ÚNICA das quatro telas da área presa numa coluna central —
          `Index.tsx` e `Chat.tsx` já ocupam a largura toda, e a âncora (`.jm-mem`) também
          (medido `left=284 w=2252` a 2560). Coluna estreita numa tela de LISTA joga a meta
          pra segunda linha e desperdiça metade do monitor de 1280 da Larissa.
          `space-y-3` = os 12px do `gap` de `.jm-mem`; era 24px. */}
      <Stack gap={3} className="p-6">
        {/* Título próprio REMOVIDO na onda de fusão (2026-08-08, US-COPI-148).
            Três motivos independentes, cada um suficiente:
            (a) era o SEGUNDO `<h1>` da página — o `<PageHeader>` canon já provê
                o dele ("Jana · Analista IA") logo acima, e dois h1 quebram a
                estrutura de heading que o leitor de tela usa pra navegar;
            (b) dizia "O Copiloto", nome que o #5401 padronizou pra "Jana";
            (c) o protótipo (`JmMemoria`, jana-merge.jsx) abre direto no alerta
                LGPD — não tem título de tela.
            O ícone `Brain` continua em uso no empty state abaixo — agora pelo nome
            (`<EmptyState icon="brain">`), não pelo import direto do lucide. */}

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
            {/* Mesmo idioma da busca da lista de Clientes: o espaço da lupa vem da
                utilitária CANON `.cw-input-icon-left` (cowork-fields.css), NÃO de
                `pl-8` — a Tailwind é layered e perde pro `.cw-input` unlayered.
                `InputGroup` não serve aqui: ele é addon/botão em caixa ao lado, não
                ícone DENTRO do campo. O nome acessível segue no `aria-label`, que já
                era o que nomeava o campo (o `<label>` envolvente não tinha texto). */}
            <div className="relative flex-1 min-w-[220px]">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
              <Input
                className="cw-input-icon-left"
                value={busca}
                onChange={(e) => setBusca(e.target.value)}
                placeholder="Buscar em fatos…"
                aria-label="Buscar em fatos"
              />
            </div>

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

        {/* Copy literal do protótipo — os DOIS empty states (JmMemoria: `semNada` × `filtrado`).
            Produção tinha só um, e com texto diferente. O `EmptyState` canon
            (@/Components/shared) substitui o Card+ícone+título+descrição+CTA montado à mão;
            o ramo filtrado usa `variant="search"`, que é exatamente o caso dele. */}
        {semNada ? (
          <EmptyState
            icon="brain"
            title="A Jana ainda não aprendeu nada sobre o seu negócio"
            description="Ela guarda o que você conta durante a conversa — rotinas, preferências, jeito de cobrar. Comece perguntando algo na aba Conversa."
          />
        ) : filtradoAZero ? (
          <EmptyState
            icon="search"
            variant="search"
            title="Nenhum fato com esse filtro"
            description="Nada casa com a busca e a categoria escolhidas."
            action={
              <Button size="sm" variant="ghost" onClick={limparFiltro}>
                Limpar filtro
              </Button>
            }
          />
        ) : (
          <div className="space-y-2">
            {lista.map((m) => <FatoCard key={m.id} memoria={m} />)}
          </div>
        )}

        <FabJana />
      </Stack>
    </>
  )
}

Memoria.layout = (page: React.ReactNode) => (
  // `breadcrumbItems` removido — dado morto (mesma razão do Index: AppShellV2:559 só
  // renderiza sob `!hideTopbar`, e o default é `true`).
  <AppShellV2 title="Jana — Memória">
    {page}
  </AppShellV2>
)

export default Memoria
