---
slug: nfebrasil-runbook-manifestacao
title: "NfeBrasil — Runbook da tela Manifestação do Destinatário"
type: runbook
module: NfeBrasil
status: spec
date: 2026-05-09
---

# RUNBOOK — Manifestação do Destinatário (NF-e recebidas)

> **Tipo:** runbook prescritivo (especifica tela US-NFE-052 antes de codar)
> **Refs:** [ADR 0039 — Chat Cockpit](../../decisions/0039-ui-chat-cockpit-padrao.md), [_DS UI-0008](../_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md), [ADR 0093 multi-tenant](../../decisions/0093-multi-tenant-isolation-tier-0.md), [ADR 0116 caso Gold](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)
> **Validado:** pendente (US-NFE-052 — F1 PLAN do MWART [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md))

Tela master/detail dentro do `AppShellV2` que lista NF-e que terceiros emitiram contra o CNPJ do business (via `Modules/NfeBrasil/Models/NfeDfeRecebido`) e oferece os 4 eventos da NT 2014.002 (Ciência/Confirmação/Desconhecimento/Não Realizada). Persona primária: operadora (Larissa-equivalente) que bate ~50 NF-e/mês — o assassino do Mubsys é o **bulk Confirmar**. Persona secundária: contador terceiro que baixa relatório mensal. Painel direito (Apps Vinculados) mostra itens da NF-e selecionada + histórico de manifestações + dados do fornecedor emitente.

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Tela renderiza em `/nfe-brasil/manifestacao` | Login com permissão `nfe.manifestacao.view` → URL → tela aparece |
| AppShellV2 envolvendo | Inspetor: `<div class="app-shell-v2">` ao redor da Page |
| Lista escopada por business | `NfeDfeRecebido::all()` retorna apenas rows do business em sessão (HasBusinessScope) |
| 4 botões manifestar funcionais | Click em qualquer linha → 4 botões; click em Confirmar → POST `/nfe-brasil/manifestacao/{id}/confirmar` → status `confirmada` |
| Bulk Confirmar opera N linhas | Checkbox seleciona ≥2 → CTA "Confirmar selecionadas" → loop sequencial cstat 135 |
| Countdown prazo 180d colorido | Linha com `prazo_confirmacao_em` ≤7d → badge vermelho; ≤30d amarelo; >30d verde |
| Atalhos J/K/C respondem | Foco na lista + `j` desce, `k` sobe, `c` confirma linha em foco |
| Última sync NSU visível | Topbar mostra "Última atualização: hh:mm — N XMLs novos" |
| Dark mode funciona | Toggle no topbar → contrastes ≥ 4.5:1 (R-DS-005) |

## 1. Objetivo

Operadora (Larissa-equivalente do Gold/ROTA LIVRE) abre a tela de manhã, vê todas as NF-e que terceiros emitiram contra o CNPJ dela na noite anterior (puxadas automaticamente pelo `BuscarDfesRecebidosJob` 06:15 BRT), bate **bulk Confirmar** nas que efetivamente recebeu, e usa Desconhecer/Não Realizada nas raras divergências. Substitui ~11h/mês de digitação no portal SEFAZ-SP por ~2min/mês de cliques. A tela vive dentro do AppShellV2 (3-colunas), seguindo o padrão Cockpit do ADR 0039.

## 2. Pré-condições

- [ ] Módulo `NfeBrasil` instalado em `/manage-modules` (já entregue)
- [ ] Permissão `nfe.manifestacao.view` + `nfe.manifestacao.manage` registradas no boot (ADR 0011)
- [ ] Migrations US-NFE-049 aplicadas: `nfe_dfe_recebidos`, `nfe_dfe_itens`, `nfe_dfe_eventos`, `nfe_dfe_nsu_state`
- [ ] Cert A1 ativo no business (`Modules/NfeBrasil/Models/NfeCertificado`) — valida assinatura do evento
- [ ] Rotas registradas em [`Modules/NfeBrasil/Routes/web.php`](../../../Modules/NfeBrasil/Routes/web.php) sob prefix `nfe-brasil/manifestacao`
- [ ] Page Inertia em [`resources/js/Pages/NfeBrasil/Manifestacao/Index.tsx`](../../../resources/js/Pages/NfeBrasil/Manifestacao/Index.tsx) — módulo em **PascalCase** (`NfeBrasil`, não `nfebrasil`)
- [ ] Skill irmã carregada: `multi-tenant-patterns` Tier A (multi-empresa) + `mwart-comparative` (visual-comparison aprovado)
- [ ] Fixture: rodar `php artisan nfebrasil:dist-dfe-puxar --business=1 --sync` em homologação SEFAZ-SP pra popular dados

## 3. Passo-a-passo

### 1. Registrar rotas

```php
// Modules/NfeBrasil/Routes/web.php (apender ao final do arquivo)
Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('nfe-brasil/manifestacao')
    ->name('nfe-brasil.manifestacao.')
    ->group(function () {
        Route::get('/', [ManifestacaoController::class, 'index'])->name('index');
        Route::post('{id}/cienciar', [ManifestacaoController::class, 'cienciar'])
            ->whereNumber('id')->name('cienciar');
        Route::post('{id}/confirmar', [ManifestacaoController::class, 'confirmar'])
            ->whereNumber('id')->name('confirmar');
        Route::post('{id}/desconhecer', [ManifestacaoController::class, 'desconhecer'])
            ->whereNumber('id')->name('desconhecer');
        Route::post('{id}/nao-realizada', [ManifestacaoController::class, 'naoRealizada'])
            ->whereNumber('id')->name('nao-realizada');
        Route::post('bulk/confirmar', [ManifestacaoController::class, 'bulkConfirmar'])
            ->name('bulk.confirmar');
    });
```

**Validação:** `php artisan route:list | grep manifestacao` → 6 rotas listadas.

### 2. Criar `ManifestacaoController` (Inertia + dispatch service)

```php
// Modules/NfeBrasil/Http/Controllers/ManifestacaoController.php
class ManifestacaoController extends Controller
{
    public function __construct(private readonly ManifestacaoService $service) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('nfe.manifestacao.view'), 403);

        $businessId = (int) session('user.business_id');

        $itens = NfeDfeRecebido::query()
            ->when($request->status, fn($q, $s) => $q->where('status_manifestacao', $s))
            ->when($request->q, fn($q, $s) => $q->where('cnpj_emitente', 'like', "%{$s}%")
                ->orWhere('nome_emitente', 'like', "%{$s}%"))
            ->orderBy('prazo_confirmacao_em', 'asc')
            ->paginate(50)
            ->withQueryString();

        $nsuState = NfeDfeNsuState::firstWhere('business_id', $businessId);

        return Inertia::render('NfeBrasil/Manifestacao/Index', [
            'itens' => $itens,
            'filters' => $request->only(['status', 'q']),
            'nsuState' => $nsuState,
            'permissions' => [
                'canManage' => auth()->user()->can('nfe.manifestacao.manage'),
            ],
        ]);
    }
    // ... métodos cienciar/confirmar/desconhecer/naoRealizada/bulkConfirmar
}
```

**Validação:** GET `/nfe-brasil/manifestacao` retorna Inertia response com `itens` paginado.

### 3. Criar Page Inertia com layout master/detail

```tsx
// resources/js/Pages/NfeBrasil/Manifestacao/Index.tsx
// @memcofre tela=/nfe-brasil/manifestacao module=NfeBrasil
//   us: US-NFE-052 (manifestação destinatário UI)
//   adrs: ADR 0039 (cockpit), ADR 0116 (caso Gold), ADR 0093 (multi-tenant)
import AppShellV2 from '@/Layouts/AppShellV2'
import { Head, router } from '@inertiajs/react'
import { useState, useEffect } from 'react'

interface NfeDfeRecebido {
  id: number
  chave_44: string
  cnpj_emitente: string
  nome_emitente: string | null
  valor_total: number
  data_emissao: string
  status_manifestacao: 'pendente' | 'ciencia' | 'confirmada' | 'desconhecida' | 'nao_realizada'
  prazo_confirmacao_em: string | null
  manifestado_em: string | null
}

interface Props {
  itens: { data: NfeDfeRecebido[]; meta: { total: number } }
  filters: { status?: string; q?: string }
  nsuState: { last_nsu: number; ultimo_check_em: string | null } | null
  permissions: { canManage: boolean }
}

export default function Index({ itens, filters, nsuState, permissions }: Props) {
  const [foco, setFoco] = useState<NfeDfeRecebido | null>(itens.data[0] ?? null)
  const [selecionados, setSelecionados] = useState<Set<number>>(new Set())
  // ... handlers cienciar/confirmar/desconhecer/naoRealizada/bulkConfirmar
}

Index.layout = (page: React.ReactNode) => <AppShellV2>{page}</AppShellV2>
```

**Validação:** `npm run build:inertia` + `grep -i "Pages/NfeBrasil/Manifestacao" public/build-inertia/manifest.json` → bundle presente.

### 4. Implementar handlers de manifestação (Inertia router)

```tsx
const confirmar = (id: number) => {
  if (!confirm('Confirmar recebimento desta NF-e?')) return
  router.post(`/nfe-brasil/manifestacao/${id}/confirmar`, {}, {
    preserveScroll: true,
    preserveState: true,
    onError: () => toast.error('Falha ao confirmar — verificar status SEFAZ.'),
    onSuccess: () => toast.success('Confirmação registrada SEFAZ.'),
  })
}

const bulkConfirmar = () => {
  const ids = Array.from(selecionados)
  if (ids.length === 0) return
  if (!confirm(`Confirmar recebimento de ${ids.length} NF-e? Esta ação registra evento 220 SEFAZ pra cada uma.`)) return
  router.post('/nfe-brasil/manifestacao/bulk/confirmar', { ids }, {
    preserveScroll: true,
    preserveState: true,
  })
}

const desconhecer = (id: number) => {
  const just = prompt('Justificativa (≥15 chars) — exigida pela NT 2014.002:')
  if (!just || just.trim().length < 15) {
    toast.error('Justificativa precisa ter pelo menos 15 caracteres.')
    return
  }
  router.post(`/nfe-brasil/manifestacao/${id}/desconhecer`, { justificativa: just }, {
    preserveScroll: true,
  })
}
```

### 5. Coluna direita Apps Vinculados (LinkedItens + LinkedFornecedor + LinkedHistorico)

Quando `foco !== null`, AppShellV2 recebe `linkedContext={{ dfeRecebido: foco }}` e renderiza:

- **LinkedItens.tsx** — itens da NF-e em foco (lista NCM + descrição + qtd + valor)
- **LinkedFornecedor.tsx** — CNPJ + razão social + histórico de NF-e do mesmo emitente nos últimos 90d (count + valor agregado)
- **LinkedHistorico.tsx** — manifestações anteriores aplicadas a esta chave (eventos table)

Cada bloco vive em `resources/js/Components/LinkedApps/` (DESIGN.md §10) e é colapsável via `localStorage` chave `oimpresso.linked.<bloco>.collapsed`.

### 6. Atalhos teclado (J/K + C + D + R + /)

```tsx
useEffect(() => {
  const handler = (e: KeyboardEvent) => {
    if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return
    if (!foco) return
    const idx = itens.data.findIndex((i) => i.id === foco.id)
    if (e.key === 'j' && idx < itens.data.length - 1) setFoco(itens.data[idx + 1])
    if (e.key === 'k' && idx > 0) setFoco(itens.data[idx - 1])
    if (e.key === 'c' && foco.status_manifestacao === 'pendente') confirmar(foco.id)
    if (e.key === 'd' && foco.status_manifestacao === 'pendente') desconhecer(foco.id)
    if (e.key === 'r' && foco.status_manifestacao === 'pendente') naoRealizada(foco.id)
    if (e.key === '/') { e.preventDefault(); document.getElementById('busca-manifestacao')?.focus() }
  }
  window.addEventListener('keydown', handler)
  return () => window.removeEventListener('keydown', handler)
}, [foco, itens.data])
```

### 7. Sync NSU manual (opção pra operadora)

Botão no PageHeader: "Buscar agora" → POST `/nfe-brasil/manifestacao/sync-now` que dispara `BuscarDfesRecebidosJob::dispatchSync(businessId)`. Throttle do service (5min cooldown) protege contra abuso. Indicador visual: "Última: 06:15 hoje — 12 XMLs novos".

## 4. Tokens CSS

| Token | Onde aplica | Esta tela usa? |
|---|---|---|
| `--bg`, `--bg-2` | Fundo da viewport | ✅ |
| `--panel`, `--panel-2` | Cards das linhas + drawer detalhe | ✅ |
| `--border`, `--border-2` | Bordas + dividers entre lista e detalhe | ✅ |
| `--text`, `--text-mute` | Razão social emitente / CNPJ secundário | ✅ |
| `--accent`, `--accent-2`, `--accent-soft` | CTA "Confirmar" + ring focus + linha selecionada | ✅ |
| `--origin-OS-{bg,fg}` | Tag de origem OS | ❌ |
| `--origin-CRM-{bg,fg}` | Tag de origem CRM | ❌ |
| `--origin-FIN-{bg,fg}` | Tag de origem Financeiro (badge "fiscal") | ✅ |
| `--origin-PNT-{bg,fg}` | Tag de origem Ponto | ❌ |
| `--row-h`, `--card-pad`, `--card-gap` | Densidade da lista | ✅ |

**Tokens shadcn semânticos** (R-DS-002):

```tsx
// ✅ correto — adapta dark mode automaticamente
<Badge className="bg-emerald-100 text-emerald-900 dark:bg-emerald-900/30 dark:text-emerald-200">
  Confirmada
</Badge>

// ✅ countdown prazo (status fixo emerald/amber/red é exceção permitida pra KPIs)
<Badge variant={diasPrazo > 30 ? 'success' : diasPrazo > 7 ? 'warning' : 'destructive'}>
  {diasPrazo}d
</Badge>

// ❌ errado — cor crua quebra dark mode
<div className="bg-blue-500 text-gray-700" />
```

## 5. Estados visuais

| Estado | Trigger | Tokens / classes | Notas |
|---|---|---|---|
| `default` | linha pendente | `bg-panel border-border` | Estado base |
| `hover` | mouse-over linha | `hover:bg-panel-2` | Aplica em todas as linhas clicáveis |
| `focus` | tab/click/J-K | `focus-visible:ring-2 ring-accent bg-accent-soft` | Linha em foco no cockpit |
| `selecionada` | checkbox marcado | `bg-accent-soft border-accent-2` | Bulk selection |
| `disabled` | linha já manifestada | `opacity-50 pointer-events-none` | Botões cienciar/confirmar somem |
| `loading` | POST em curso | `<Spinner/>` no botão + `aria-busy="true"` | Inertia router visit indicator |
| `empty` | sem NF-e recebidas | `<EmptyState/>` shared | Microcopy + CTA "Buscar agora" |
| `error` | falha de fetch / 500 | `<ErrorBoundary/>` + retry | Logar contexto biz + chave |

```tsx
// Snippet canônico de empty state
import { EmptyState } from '@/Components/shared/EmptyState'
import { InboxIcon } from 'lucide-react'

{itens.data.length === 0 && (
  <EmptyState
    icon={<InboxIcon />}
    title="Nenhuma NF-e recebida"
    description="O job de Distribuição DFe roda diariamente às 06:15 BRT. Você pode forçar uma busca agora se há NF-e que esperava receber."
    primaryAction={{ label: 'Buscar agora', onClick: handleSyncNow }}
  />
)}
```

## 6. Responsividade

| Breakpoint | Largura | Comportamento |
|---|---|---|
| `default` | <640px | Stack vertical: lista vira tela única; detalhe abre como drawer com botão "Voltar" |
| `sm` | ≥640px | Idem default; lista densidade reduzida (2 linhas/item) |
| `md` | ≥768px | Lista 50% / Detalhe 50% lado-a-lado; coluna direita ainda oculta |
| `lg` | ≥1024px | Coluna direita Apps Vinculados colapsa pra 44px (ADR 0039 mitigação) |
| `xl` | ≥1280px | 3 colunas full Cockpit (sidebar 260 / lista+detalhe 1fr / Apps 320) |
| `2xl` | ≥1536px | Idem xl; lista pode mostrar 3 linhas/item (densidade espaçada) |

**Master/detail mobile:** abaixo de `md`, apertar uma linha abre tela de detalhe full-screen com botão `←` no topo. Atalhos J/K desabilitam (sem teclado físico). Bulk Confirmar fica disponível via menu (`MoreVertical`).

## 7. Atalhos

| Tecla | Ação | Escopo | Listener |
|---|---|---|---|
| `⌘K` / `Ctrl+K` | Busca global | Shell | Já no AppShellV2 |
| `J` | Próxima NF-e na lista | Lista quando focada | `useEffect` |
| `K` | NF-e anterior na lista | Lista quando focada | `useEffect` |
| `C` | Confirmar NF-e em foco | Linha em foco (status=pendente) | `useEffect` |
| `D` | Desconhecer NF-e em foco | Linha em foco (status=pendente) — abre modal justif | `useEffect` |
| `R` | Não Realizada NF-e em foco | Linha em foco (status=pendente) — abre modal justif | `useEffect` |
| `/` | Focar busca local (CNPJ/nome emitente) | Tela inteira | `useEffect` |
| `E`/`A`/`N` | — | — | Não aplicável (não tem concluir/adiar/criar) |

```tsx
// Snippet canônico de listener (DESIGN.md §13)
useEffect(() => {
  const handler = (e: KeyboardEvent) => {
    if (e.target instanceof HTMLInputElement) return // não interferir em inputs/busca
    if (!foco) return
    if (e.key === 'j') setFoco(proximo(foco))
    if (e.key === 'k') setFoco(anterior(foco))
    if (e.key === 'c' && foco.status_manifestacao === 'pendente') confirmar(foco.id)
    if (e.key === 'd' && foco.status_manifestacao === 'pendente') desconhecer(foco.id)
    if (e.key === 'r' && foco.status_manifestacao === 'pendente') naoRealizada(foco.id)
  }
  window.addEventListener('keydown', handler)
  return () => window.removeEventListener('keydown', handler)
}, [foco])
```

## 8. Component contract

```tsx
interface ManifestacaoIndexProps {
  itens: {
    data: Array<{
      id: number
      chave_44: string                  // 44 dígitos
      cnpj_emitente: string             // 14 dígitos
      nome_emitente: string | null
      valor_total: number               // decimal 15,2
      data_emissao: string              // ISO 8601
      status_manifestacao: 'pendente' | 'ciencia' | 'confirmada' | 'desconhecida' | 'nao_realizada'
      prazo_confirmacao_em: string | null  // YYYY-MM-DD
      manifestado_em: string | null     // ISO 8601
    }>
    meta: { total: number; current_page: number; last_page: number }
    links: { first: string; last: string; prev: string | null; next: string | null }
  }
  filters: {
    status?: string  // pendente|ciencia|confirmada|desconhecida|nao_realizada|null
    q?: string       // busca CNPJ ou nome emitente
  }
  nsuState: {
    last_nsu: number
    ultimo_check_em: string | null  // ISO 8601
    ultimo_lote_count: number
  } | null
  permissions: {
    canManage: boolean  // gate dos 4 botões manifestar
  }
}
```

**Componentes shared usados:**

- [`@/Components/shared/PageHeader`](../../../resources/js/Components/shared/PageHeader.tsx) — título + ação "Buscar agora" + breadcrumb
- [`@/Components/shared/PageFilters`](../../../resources/js/Components/shared/PageFilters.tsx) — filtros status + busca debounced
- [`@/Components/shared/EmptyState`](../../../resources/js/Components/shared/EmptyState.tsx) — vazio com CTA Buscar agora
- [`@/Components/ui/button`](../../../resources/js/Components/ui/button.tsx) — Button shadcn (R-DS-001)
- [`@/Components/ui/badge`](../../../resources/js/Components/ui/badge.tsx) — countdown prazo + status
- [`@/Components/ui/checkbox`](../../../resources/js/Components/ui/checkbox.tsx) — bulk selection
- [`@/Components/LinkedApps/LinkedItens`](../../../resources/js/Components/LinkedApps/LinkedItens.tsx) — itens da NF-e em foco *(criar)*
- [`@/Components/LinkedApps/LinkedFornecedor`](../../../resources/js/Components/LinkedApps/LinkedFornecedor.tsx) — perfil emitente *(criar)*
- [`@/Components/LinkedApps/LinkedHistorico`](../../../resources/js/Components/LinkedApps/LinkedHistorico.tsx) — eventos manifestação prévios *(criar)*

## 9. DoD checklist

- [ ] Tela vive dentro de `AppShellV2` via `Index.layout = (page) => <AppShellV2>{page}</AppShellV2>`
- [ ] Tokens CSS do shell + shadcn semânticos (sem cor crua — R-DS-002)
- [ ] Coluna direita Apps Vinculados entregue com 3 blocos (Itens + Fornecedor + Histórico) — ADR 0039 §3
- [ ] Atalhos J/K/C/D/R/`/` ativos com `removeEventListener` no cleanup
- [ ] Estado persistido em `localStorage` com prefixo `oimpresso.nfebrasil.manifestacao.*`
- [ ] Componentes shared reusados antes de criar novo (R-DS-001) — só LinkedApps são novos
- [ ] PT-BR em todo label/copy/comentário (CNPJ → "CNPJ", chave → "Chave de acesso")
- [ ] Dark mode validado (contraste ≥ 4.5:1 — R-DS-005), badges status com tokens semânticos
- [ ] Responsividade: 320px (drawer mobile), 768px (md split), 1024px (lg coluna direita), 1280px (xl 3 cols)
- [ ] Estados visuais cobertos: default/hover/focus/selecionada/disabled/loading/empty/error
- [ ] Bundle Inertia builda: `npm run build:inertia` + `grep -i "Pages/NfeBrasil/Manifestacao" public/build-inertia/manifest.json`
- [ ] Multi-tenant: `NfeDfeRecebido` query usa `HasBusinessScope` (ADR 0093 — verificar nenhum `withoutGlobalScopes` sem comentário)
- [ ] Confirma destrutivo: bulk Confirmar exige `confirm()` mostrando count + aviso "registra evento 220 SEFAZ"

## 10. Pegadinhas

- ❌ NÃO permitir bulk Confirmar sem confirma intermediário — operadora pode confirmar 50 NF-e por engano. SEFAZ aceita mas reverter exige Não Realizada (com justificativa). Sintoma: cliente liga reclamando que confirmou nota de fornecedor errado. Fix: `confirm()` mostrando count + aviso "irreversível via UI".
- ❌ NÃO emitir bulk SEFAZ em paralelo (Promise.all) — cada chave precisa nSeqEvento crescente isolado por (business, dfe, tipo). Race condition pode duplicar nSeqEvento → `cstat 573` (Duplicidade de Evento). Fix: loop `for await` sequencial no controller `bulkConfirmar`.
- ❌ NÃO ignorar prazo vencido na UI — SEFAZ aceita Confirmação após 180d (até 365d), mas cliente pode ser autuado. Sintoma: badge vermelho ignorado pela operadora distraída. Fix: linha com prazo ≤7d sobe pro topo da lista + badge animado destrutivo.
- ❌ NÃO usar `route('nfe-brasil.manifestacao.confirmar', id)` sem ler [GOTCHAS.md Inertia/React](../../../.claude/skills/cockpit-runbook/GOTCHAS.md) — Ziggy disponível desde [PR #180](https://github.com/wagnerra23/oimpresso.com/pull/180), mas confirmar slug exato. Fix: template literal `` `/nfe-brasil/manifestacao/${id}/confirmar` `` é alternativa segura.
- ❌ NÃO chamar Job síncrono no controller (`dispatchSync`) sem timeout — SEFAZ pode travar 30s+ no DistribuicaoDFe. Sintoma: tela trava esperando POST `/sync-now`. Fix: dispatch normal + retornar 202 Accepted; UI faz polling do `nsuState.ultimo_check_em` a cada 5s pra atualizar.
- ❌ NÃO esquecer cert A1 vencido — manifestação assina com mesma cert da emissão. Sintoma: todos os botões manifestar retornam erro "Certificate expired". Fix: pre-flight `CertificadoService::verificarVencimento` no `index()` + alerta amarelo no PageHeader se ≤30d.
- ❌ NÃO listar manifestadas misturadas com pendentes sem filtro default — operadora abre tela com 500 itens históricos e perde as 12 pendentes do dia. Fix: filter default `?status=pendente`; aba "Histórico" pra ver todas.
- ❌ NÃO renderizar countdown sem timezone — `prazo_confirmacao_em` é DATE (sem hora). `Carbon::diffInDays` em UTC pode dar -1 dia em São Paulo. Fix: usar `now()->startOfDay()->diffInDays($prazo)` no PHP, passar `dias_restantes` como prop pré-calculado.

## 11. ADR de origem

- [ADR 0039 — Chat Cockpit](../../decisions/0039-ui-chat-cockpit-padrao.md) — layout-mãe 3 colunas + atalhos J/K canônicos + Apps Vinculados
- [ADR 0011 — Padrão Jana](../../decisions/0011-alinhamento-padrao-jana.md) — base estrutural UltimatePOS-like (DataController + permissões + rotas Install)
- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md) — `HasBusinessScope` global + JOB recebe `$businessId`
- [ADR 0094 — Constituição V2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — princípios duros (SoC brutal §5)
- [ADR 0103 — Eventos fiscais separados](../../decisions/0103-eventos-fiscais-separados-por-modelo.md) — base pra `NfeDfeEvento` distinto de `NfeEvento`
- [ADR 0104 — Processo MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — 5 fases obrigatórias da migração Blade→Inertia
- [ADR 0107 — Visual comparison gate F3](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — Wagner aprova SCREENSHOT, não tabela
- [ADR 0116 — Caso Gold manifestação](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md) — origem desta tela; emenda ADR 0115

> Esta tela NÃO quebra padrão Cockpit — só implementa caso de uso novo (manifestação destinatário) usando peças canônicas existentes. Não exige ADR substitutiva.

---

**Última atualização:** 2026-05-09
