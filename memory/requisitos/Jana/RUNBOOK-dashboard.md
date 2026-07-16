---
slug: copiloto-runbook-dashboard
title: "Jana — Runbook da tela Dashboard de Metas"
type: runbook
module: Jana
status: active
date: 2026-05-05
---

# RUNBOOK — Dashboard de Metas (Jana)

> **Tipo:** runbook reproduzível
> **Refs:** [ADR 0026](../../decisions/0026-posicionamento-erp-grafico-com-ia.md), [ADR 0031](../../decisions/0031-memoriacontrato-mem0-default.md), [ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md), [ADR 0036](../../decisions/0036-replanejamento-meilisearch-first.md), [ADR 0039](../../decisions/0039-ui-chat-cockpit-padrao.md), [_DS UI-0008](../_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md), [_DS UI-0009](../_DesignSystem/adr/ui/0009-cockpit-sidebar-light-padrao.md)
> **Validado:** tela em produção `https://oimpresso.com/copiloto/dashboard`

Tela read-only que lista as metas ativas do business em foco como cards com **farol** (verde/amarelo/vermelho/cinza) calculado client-side. Persona: dono operador (Larissa, ROTA LIVRE biz=4) abre de manhã pra ver "como tô indo nas metas que conversei com a Jana". Vive dentro do `AppShellV2` (Cockpit) — sem coluna direita, sem master/detail. Acesso ao detalhe da meta é via `Link` pra `/copiloto/metas/{id}` (outra rota). FAB inferior direito conduz ao chat da Jana com contexto preservado.

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Tela renderiza em `/copiloto/dashboard` | Login com `copiloto.access` → URL → header "Dashboard de Metas" + N cards |
| AppShellV2 envolvendo | Inspetor: `<div class="cockpit">` ao redor; sidebar preta (dark-fixo) + breadcrumb "Jana / Dashboard" |
| Farol lateral colorido em cada card | Faixa de 1px à esquerda do card: emerald/amber/rose/muted-foreground/30 |
| Sparkline renderiza com ≥ 2 apurações | SVG inline 120×32 + ícone TrendingUp/Down/Minus |
| Empty state quando `metas.length === 0` | Icon MessageSquare + "Nenhuma meta ativa" + Button "Iniciar conversa" → `/copiloto` |
| FAB Jana fixo bottom-right | Link circular 56px com MessageSquare → `/copiloto?context=/copiloto/dashboard` |

## 1. Objetivo

Painel read-only de leitura rápida do estado das **metas ativas** que o cliente operacional configurou via chat com a Jana IA (US-COPI-010, 011, 012). Renderiza N cards (1/2/3 colunas conforme breakpoint) com farol semafórico calculado on-the-fly em client-side via `calcularFarol()`, valor realizado vs alvo, sparkline das últimas apurações e link pra detalhe da meta. Não há master/detail interno — cada card é independente. FAB no canto inferior direito permite voltar ao chat preservando contexto via query string `?context=/copiloto/dashboard`. Cliente é o dono operador (persona Larissa, business=4); dev/superadmin também usa pra debugar metas seedadas.

## 2. Pré-condições

- [ ] Módulo `Jana` instalado em `/manage-modules` (ADR 0024 — botão Install funcional)
- [ ] Permissão `copiloto.access` atribuída ao role do usuário
- [ ] Rotas registradas em [`Modules/Jana/Routes/web.php`](../../../Modules/Jana/Routes/web.php) — pelo menos `/copiloto/dashboard` apontando pro Controller que renderiza `Jana/Dashboard`
- [ ] Page Inertia em [`resources/js/Pages/Jana/Dashboard.tsx`](../../../resources/js/Pages/Jana/Dashboard.tsx) — módulo em **PascalCase** (`Jana`, não `copiloto`)
- [ ] Skill irmã carregada: `copiloto-arch` (stack ADRs 0035-0053) — tela toca conceitos da Jana
- [ ] Skill irmã `multi-tenant-patterns` se Controller filtrar por `business_id`
- [ ] Seed: `php artisan module:seed Jana` popula 5 metas template + meta raiz Wagner ROI

## 3. Passo-a-passo

### 1. Controller renderiza Inertia com array tipado de metas

```php
// Modules/Jana/Http/Controllers/DashboardController.php
namespace Modules\Jana\Http\Controllers;

use Inertia\Inertia;
use Modules\Jana\Entities\Meta;

class DashboardController extends Controller
{
    public function index()
    {
        $businessId = session('user.business_id'); // global scope multi-tenant

        $metas = Meta::ativas()
            ->where('business_id', $businessId)
            ->with(['periodoAtual', 'ultimaApuracao', 'apuracoesRecentes'])
            ->get()
            ->map(fn (Meta $m) => [
                'id'                  => $m->id,
                'slug'                => $m->slug,
                'nome'                => $m->nome,
                'unidade'             => $m->unidade,
                'tipo_agregacao'      => $m->tipo_agregacao,
                'periodo_atual'       => $m->periodoAtual?->only(['data_ini', 'data_fim', 'valor_alvo', 'trajetoria']),
                'ultima_apuracao'     => $m->ultimaApuracao?->only(['data_ref', 'valor_realizado']),
                'apuracoes_recentes'  => $m->apuracoesRecentes->map->only(['data_ref', 'valor_realizado'])->values(),
            ]);

        return Inertia::render('Jana/Dashboard', [
            'metas' => $metas,
        ]);
    }
}
```

**Validação:** `php artisan route:list --path=copiloto/dashboard` retorna 1 linha.

### 2. Page Inertia recebe Props tipados

```tsx
// resources/js/Pages/Jana/Dashboard.tsx
interface Apuracao { data_ref: string; valor_realizado: number }
interface Periodo  { data_ini: string; data_fim: string; valor_alvo: number; trajetoria: string }
interface Meta {
  id: number
  slug: string
  nome: string
  unidade: string
  tipo_agregacao: string
  periodo_atual: Periodo | null
  ultima_apuracao: Apuracao | null
  apuracoes_recentes: Apuracao[]
}
interface Props { metas: Meta[] }

export default function Dashboard({ metas }: Props) { /* ... */ }
```

### 3. Persistent Layout AppShellV2 com title + breadcrumbItems

```tsx
import AppShellV2 from '@/Layouts/AppShellV2'

Dashboard.layout = (page: React.ReactNode) => (
  <AppShellV2
    title="Jana — Dashboard"
    breadcrumbItems={[{ label: 'Jana' }, { label: 'Dashboard' }]}
  >
    {page}
  </AppShellV2>
)
```

**Validação:** abrir `/copiloto/dashboard`; topbar mostra `Jana / Dashboard`; aba do navegador `Jana — Dashboard`.

### 4. Função `calcularFarol()` — regra R-COPI-FAROL-001

Determina cor lateral do card baseada em **desvio do realizado vs projetado linear** no período:

```tsx
function calcularFarol(meta: Meta): 'verde' | 'amarelo' | 'vermelho' | 'cinza' {
  const periodo = meta.periodo_atual
  const ultima  = meta.ultima_apuracao
  if (!periodo || !ultima) return 'cinza' // sem dado → neutro

  const hoje        = new Date()
  const ini         = new Date(periodo.data_ini)
  const fim         = new Date(periodo.data_fim)
  const totalMs     = fim.getTime() - ini.getTime()
  const decorridoMs = hoje.getTime() - ini.getTime()
  const progresso   = Math.min(1, Math.max(0, decorridoMs / totalMs))
  const projetado   = periodo.valor_alvo * progresso
  const realizado   = ultima.valor_realizado

  if (projetado <= 0) return 'cinza'

  const desvioPct = ((realizado - projetado) / projetado) * 100
  if (desvioPct >= -5)  return 'verde'    // até 5% abaixo é OK
  if (desvioPct >= -15) return 'amarelo'  // 5-15% abaixo: alerta
  return 'vermelho'                       // >15% abaixo: crítico
}
```

**Pegadinha:** trajetória linear é assumida — se a meta tem sazonalidade (ex.: vendas concentradas em dezembro), o farol vai mentir em meses fracos. Substituir por `periodo.trajetoria` quando schedule for sazonal.

### 5. Renderizar `MetaCard` com farol + valor + sparkline + link

```tsx
function MetaCard({ meta }: { meta: Meta }) {
  const farol     = calcularFarol(meta)
  const realizado = meta.ultima_apuracao?.valor_realizado ?? null
  const alvo      = meta.periodo_atual?.valor_alvo ?? null
  const progresso = alvo && realizado !== null ? Math.min(100, (realizado / alvo) * 100) : null

  return (
    <Card className="relative overflow-hidden">
      <div className={`absolute left-0 top-0 h-full w-1 ${FAROL_CLASSES[farol]}`} aria-hidden="true" />
      <CardHeader className="pb-2 pl-5">
        <div className="flex items-start justify-between gap-2">
          <CardTitle className="text-base">{meta.nome}</CardTitle>
          <Badge variant="outline" className="shrink-0">{meta.unidade}</Badge>
        </div>
      </CardHeader>
      <CardContent className="pl-5 space-y-3">
        {realizado !== null
          ? <div className="text-2xl font-bold tabular-nums">{formatValue(realizado, meta.unidade)}</div>
          : <div className="text-sm text-muted-foreground">Aguardando apuração…</div>
        }
        {alvo !== null && (
          <div className="text-xs text-muted-foreground">
            Alvo: {formatValue(alvo, meta.unidade)}
            {progresso !== null && <span className="ml-2 font-medium text-foreground">{progresso.toFixed(0)}%</span>}
          </div>
        )}
        {meta.apuracoes_recentes.length > 0 && <Sparkline dados={meta.apuracoes_recentes} />}
        <Link href={`/copiloto/metas/${meta.id}`} className="inline-flex items-center gap-1 text-xs text-primary hover:underline">
          Ver detalhe <ExternalLink className="h-3 w-3" />
        </Link>
      </CardContent>
    </Card>
  )
}
```

### 6. Empty state quando `metas.length === 0`

```tsx
{metas.length === 0 ? (
  <div className="flex flex-col items-center justify-center gap-4 py-16 text-center">
    <MessageSquare className="h-12 w-12 text-muted-foreground/50" />
    <p className="text-muted-foreground">
      Nenhuma meta ativa. Converse com a Jana para criar a primeira.
    </p>
    <Link href="/copiloto"><Button>Iniciar conversa</Button></Link>
  </div>
) : (
  <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    {metas.map(meta => <MetaCard key={meta.id} meta={meta} />)}
  </div>
)}
```

### 7. FabJana preservando contexto

```tsx
import FabJana from './components/FabJana'

return (
  <>
    <div className="space-y-6 p-6">{/* header + grid de cards */}</div>
    <FabJana contextRoute="/copiloto/dashboard" />
  </>
)
```

`FabJana` gera `Link` pra `/copiloto?context=%2Fcopiloto%2Fdashboard`. O chat da Jana lê a query string e injeta `Tela: /copiloto/dashboard` no `ContextoNegocio` enviado ao LLM.

### 8. Build local + smoke

```bash
npm run build:inertia
grep -i "Pages/Jana/Dashboard" public/build-inertia/manifest.json
# Esperado: 1 linha com hash do bundle
```

## 4. Tokens CSS

| Token / classe | Onde aplica nesta tela | Origem |
|---|---|---|
| `text-primary` | Link "Ver detalhe", Button | shadcn semântico (R-DS-002) |
| `text-primary-foreground` | Texto sobre Button primary | shadcn semântico |
| `text-muted-foreground` | Subtítulos, "Aguardando apuração", Alvo | shadcn semântico |
| `text-foreground` | Percentual de progresso enfático | shadcn semântico |
| `bg-emerald-500` | Farol verde lateral | **Exceção R-DS-002** — status fixos OK |
| `bg-amber-400` | Farol amarelo lateral | **Exceção R-DS-002** — status fixos OK |
| `bg-rose-500` | Farol vermelho lateral | **Exceção R-DS-002** — status fixos OK |
| `bg-muted-foreground/30` | Farol cinza lateral (sem dado) | Token derivado |
| `text-emerald-500` | Ícone TrendingUp na sparkline | Status fixo |
| `text-rose-500` | Ícone TrendingDown na sparkline | Status fixo |
| `tabular-nums` | Valor R$/numérico | Tailwind utility |
| `--sb-bg`, `--sb-bg-2` | Sidebar do AppShellV2 (light por default) | _DS UI-0009 — escopados em `.cockpit` |

**Coluna direita do Cockpit:** esta tela NÃO usa (não há contexto vinculado a uma entidade externa específica — cada card já tem seu próprio Link de detalhe). LinkedAppsPanel não renderiza.

## 5. Estados visuais

| Estado | Trigger | Implementação atual | Pegadinha |
|---|---|---|---|
| `default` | — | `<Card>` shadcn | OK |
| `hover` | mouse-over Link "Ver detalhe" | `hover:underline` | Cards inteiros NÃO têm hover — só o link interno. Considerar `hover:bg-muted/30` no Card todo se Wagner aprovar |
| `focus` | tab/click | shadcn Button + Link com ring nativo | OK (Button shadcn herda focus-visible) |
| `disabled` | — | Não aplica (tela read-only) | — |
| `loading` | data fetching | **❌ NÃO IMPLEMENTADO** — Inertia entrega `metas` resolvido via SSR; spinner inicial é do shell | Ver §10 |
| `empty` | `metas.length === 0` | Icon + texto + Button "Iniciar conversa" | OK |
| `aguardando` | `meta.ultima_apuracao === null` mas existe meta | "Aguardando apuração…" muted | Sub-estado bem implementado |
| `error` | `Inertia.render()` falha (500) | **❌ NÃO IMPLEMENTADO** localmente | Trata pelo error boundary global do shell |

## 6. Responsividade

Grid breakpoints declarados em `<div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">`:

| Largura | Cols | Comportamento |
|---|---|---|
| `<640px` (default) | 1 | Cards empilhados full-width |
| `≥640px` `sm:` | 2 | 2 colunas de cards |
| `640-1279px` | 2 | Mantém 2 — `md:` e `lg:` não declarados → segue `sm:` |
| `≥1280px` `xl:` | 3 | 3 colunas |
| `≥1536px` `2xl:` | 3 | Mantém 3 |

**Pegadinha:** AppShellV2 em <1280px colapsa LinkedApps panel pra 44px (UI-0008 mitigação) — como esta tela não tem LinkedApps, sobra mais espaço pro grid; em monitor 1280px (Larissa, ROTA LIVRE) dá pra encaixar 2 colunas confortáveis.

**Padding:** `<div className="space-y-6 p-6">` — 24px em todos os lados; em mobile sobra pouco lateral. Considerar `px-4 sm:p-6` se Wagner reclamar de aperto em mobile.

## 7. Atalhos

| Tecla | Ação | Escopo | Listener |
|---|---|---|---|
| `⌘K` / `Ctrl+K` | Busca global | Shell (não a tela) | AppShellV2 |
| `J` | — | — | — |
| `K` | — | — | — |
| `E` | — | — | — |
| `A` | — | — | — |
| `N` | — | — | — |
| `/` | — | — | — |

> **Tela não implementa atalhos próprios** — navegação é por mouse/touch. Decisão consciente: master/detail está em rota separada (`/copiloto/metas/{id}`), então J/K não fazem sentido aqui. Se futuramente Wagner quiser preview lateral, considerar revisitar.

## 8. Component contract

### Props da Page

```tsx
interface DashboardProps {
  metas: Meta[]
}

interface Meta {
  id: number
  slug: string
  nome: string                    // ex: "Faturamento mensal"
  unidade: string                 // 'R$' | '%' | 'unid' | etc
  tipo_agregacao: string          // 'soma' | 'media' | 'ultimo'
  periodo_atual: Periodo | null
  ultima_apuracao: Apuracao | null
  apuracoes_recentes: Apuracao[]  // ≥2 itens pra Sparkline renderizar
}

interface Periodo {
  data_ini: string                // 'YYYY-MM-DD'
  data_fim: string                // 'YYYY-MM-DD'
  valor_alvo: number
  trajetoria: string              // 'linear' | 'sazonal' | etc
}

interface Apuracao {
  data_ref: string                // 'YYYY-MM-DD'
  valor_realizado: number
}
```

### Componentes locais (definidos no próprio arquivo)

- `calcularFarol(meta) → 'verde'|'amarelo'|'vermelho'|'cinza'` — regra R-COPI-FAROL-001
- `formatValue(value, unidade) → string` — i18n pt-BR (R$/%/numérico)
- `Sparkline({ dados: Apuracao[] })` — SVG inline 120×32 + tendência up/down/flat
- `MetaCard({ meta: Meta })` — card individual

### Componentes shared usados

- [`@/Layouts/AppShellV2`](../../../resources/js/Layouts/AppShellV2.tsx) — Persistent Layout
- [`@/Components/ui/button`](../../../resources/js/Components/ui/button.tsx) — shadcn Button (R-DS-001)
- [`@/Components/ui/card`](../../../resources/js/Components/ui/card.tsx) — shadcn Card primitives
- [`@/Components/ui/badge`](../../../resources/js/Components/ui/badge.tsx) — shadcn Badge
- [`./components/FabJana`](../../../resources/js/Pages/Jana/components/FabJana.tsx) — FAB local do módulo

### Ícones (lucide-react — R-DS-003)

`MessageSquare`, `TrendingUp`, `TrendingDown`, `Minus`, `ExternalLink`.

## 9. DoD checklist

- [x] Tela vive dentro de `AppShellV2` (Persistent Layout via `Dashboard.layout = ...`)
- [x] Tokens shadcn semânticos (`text-primary`, `text-muted-foreground`) — exceções R-DS-002 só em status fixos (emerald/amber/rose)
- [n/a] Coluna direita "Apps Vinculados" — tela não tem contexto vinculado externo (cada card já leva ao detalhe)
- [n/a] Atalhos J/K/E/A — tela read-only sem master/detail interno; todos `—`
- [n/a] Estado `localStorage oimpresso.*` — tela read-only sem filtros locais
- [x] Componentes shared reusados (Button/Card/Badge/AppShellV2)
- [x] PT-BR em todos os labels ("Dashboard de Metas", "Aguardando apuração…", "Iniciar conversa")
- [x] Dark mode validado — usa só tokens shadcn semânticos + status fixos (que são iguais em dark/light)
- [x] Responsividade `sm:grid-cols-2 xl:grid-cols-3`
- [x] Estados cobertos: default + empty + aguardando (loading/error pendentes — ver §10)
- [x] Bundle Inertia: `npm run build:inertia` + `Pages/Jana/Dashboard` no manifest
- [x] Multi-tenant: Controller filtra por `session('user.business_id')`

## 10. Pegadinhas

- ❌ **Trajetória linear hardcoded em `calcularFarol`** — `progresso = decorridoMs / totalMs` assume distribuição uniforme. Metas com sazonalidade (faturamento Dec/Black Friday) vão acender vermelho em meses fracos mesmo sendo trajetória esperada. Fix futuro: usar `periodo.trajetoria` (já existe no contract) pra calcular projetado correto.
- ❌ **Loading state ausente** — Inertia entrega tudo resolvido em SSR, mas se o Controller demora >2s a tela fica branca. Considerar `<Skeleton>` shadcn em ≥3 cards quando `usePage().props.metas === undefined` (não acontece hoje porque shared props são síncronos).
- ❌ **Error boundary local ausente** — falhas de fetch caem no error boundary global do shell. Pra tela de produção crítica, adicionar `<ErrorBoundary>` ao redor do grid com retry CTA.
- ❌ **`new Date(periodo.data_ini)` em string ISO** — em alguns navegadores antigos (não Chrome moderno) parsing pode falhar com timezone implícito. Fix: usar `parseISO` de `date-fns` ou validar formato no Controller.
- ❌ **Cards inteiros não-clicáveis** — só o Link "Ver detalhe" leva a `/copiloto/metas/{id}`. Wagner pode preferir o card inteiro clicável (UX mais comum). Decisão consciente atual: evita conflito com Sparkline hover futuro.
- ❌ **`window.location.reload()` está PROIBIDO neste projeto** (auto-mem `preference_cache_estado_preservado`) — tela não usa, OK. Mas se adicionar refresh, usar `router.reload({ only: ['metas'] })`.
- ✅ **AppShellV2 com `title` + `breadcrumbItems`** está OK — confere props em [`AppShellV2.tsx:34-58`](../../../resources/js/Layouts/AppShellV2.tsx).

Pegadinhas genéricas em [`.claude/skills/cockpit-runbook/GOTCHAS.md`](../../../.claude/skills/cockpit-runbook/GOTCHAS.md).

## 11. ADR de origem

- [ADR 0026 — Posicionamento ERP Gráfico com IA](../../decisions/0026-posicionamento-erp-grafico-com-ia.md) — por que existe Jana IA no ERP (motivação de produto)
- [ADR 0031 — MemoriaContrato + Mem0 default](../../decisions/0031-memoriacontrato-mem0-default.md) — base da memória que alimenta a apuração das metas
- [ADR 0035 — Stack AI canônica](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) — laravel/ai SDK (substitui adapters anteriores)
- [ADR 0036 — Replanejamento Meilisearch-first](../../decisions/0036-replanejamento-meilisearch-first.md) — driver de retrieval atual (afeta latência das apurações)
- [ADR 0039 — Chat Cockpit (3 colunas)](../../decisions/0039-ui-chat-cockpit-padrao.md) — layout-mãe (esta tela vive dentro)
- [_DS UI-0008 — Cockpit layout-mãe ERP](../_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md)
- [_DS UI-0023 — Sidebar preta (dark-fixo)](../_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md)

**Stories cobertas:** US-COPI-010, US-COPI-011, US-COPI-012 ([SPEC.md](SPEC.md))
**Rules:** R-COPI-002 (semáforo de metas), R-COPI-FAROL-001 (cálculo de desvio % vs trajetória) — formalmente em [SPEC.md](SPEC.md)
**Tests:** [tests/Feature/Modules/Jana/MemoriaContratoTest](../../../tests/Feature/Modules/Jana/MemoriaContratoTest.php)

---

**Última atualização:** 2026-05-05
