---
slug: nfebrasil-manifestacao-visual-comparison
title: "NfeBrasil — Comparativo visual da tela Manifestação do Destinatário"
type: visual-comparison
module: NfeBrasil
status: approved
date: 2026-05-09
canon_reference: os-page.jsx
canon_secundario: tasks.jsx
blade_source: n/a (greenfield — caso Gold ADR 0116, sem Blade legacy)
inertia_target: resources/js/Pages/NfeBrasil/Manifestacao/Index.tsx
approved_by: wagner
approved_at: 2026-05-09
---

# Comparativo visual — Manifestação do Destinatário

> **Tipo de tela:** master/detail com bulk actions + countdown prazo
> **Persona alvo:** Operadora Gold Comunicação Visual (Larissa-equivalente, monitor 1280px provável; ~50 NF-e/mês recebidas)
> **Persona secundária:** Contador terceiro (relatório mensal manifestações)
> **Refs:**
> - Blade legacy: ❌ **n/a** — tela nasce greenfield (não há legado pra portar)
> - Canon Cockpit principal: [`os-page.jsx`](../_DesignSystem/ui_kits/cowork-2026-04-27/os-page.jsx) — list+detail com bulk
> - Canon Cockpit secundário: [`tasks.jsx`](../_DesignSystem/ui_kits/cowork-2026-04-27/tasks.jsx) — inbox padrão (atalhos J/K + prazo countdown)
> - RUNBOOK: [`RUNBOOK-manifestacao.md`](RUNBOOK-manifestacao.md)
> - SPEC: [`SPEC.md` US-NFE-052](SPEC.md)
> - Backend: [PR #313](https://github.com/wagnerra23/oimpresso.com/pull/313) (US-NFE-049/050/051)

## Resumo executivo (Wagner lê em 30s)

A operadora Gold faz HOJE manifestação manual no portal SEFAZ-SP (~30min/dia × 22 dias = ~11h/mês). A tela MWART transforma isso em ~2min/mês via **bulk Confirmar 50 NFe em 1 clique**, **countdown 180d colorido** que destaca prazos críticos no topo, e **sync NSU automático 06:15 BRT** sem depender de email do fornecedor. Diferencial cego dos concorrentes verticais (Mubsys/Bling/Omie cobrem emissão; ninguém cobre manifestação automática). Canon visual: `os-page.jsx` (master/detail com bulk) + `tasks.jsx` (inbox com prazo).

## Tabela comparativa — 15 dimensões

> **Coluna "Hoje (portal SEFAZ-SP)"** substitui "Blade legacy" — não há legado. Representa o que a operadora faz manualmente fora do oimpresso.

### A. Dimensões estruturais (1-8 — V1 original)

#### 1. Layout

| Aspecto | Hoje (portal SEFAZ-SP) | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Header | Logo SEFAZ + breadcrumb governo | AppShellV2 header com avatar+empresa+toggle dark | AppShellV2 default (paridade) |
| Sidebar | Menu vertical SEFAZ não-customizado | Sidebar 260px light (Chat/Menu — ADR 0039) | AppShellV2 default (paridade) |
| Topnav módulo | Tabs: "Nota Fiscal Eletrônica > Manifestar" | NfeBrasil topnav com `Configuração / Tributação / Manifestação / Status` | Implementar item "Manifestação" no topnav NfeBrasil |
| Body grid | Tabela full-width 100% sem detalhe lado-a-lado | `os-page.jsx`: lista 1fr / detalhe 480px (canon list+detail) | Lista 1fr esquerda + detalhe 480px direita; coluna direita Apps Vinculados em xl: |
| Footer | Links governo | Ausente | Ausente (paridade canon) |
| Breakpoints | Bootstrap responsive básico | max-w-7xl + grid responsive Tailwind 4 | max-w-full (lista grande precisa de horizontal); container interno max-w-7xl |

#### 2. Hierarquia visual

| Aspecto | Hoje | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Ação primária | Botão "Manifestar" cinza pequeno | 1 `<Button>` shadcn primary com label clara | "Confirmar selecionadas (N)" — sticky no topo quando há seleção; primary `bg-emerald-600` |
| Ações secundárias | "Ciência" "Desconhecimento" "Não Realizada" todos iguais | 1-2 `variant="outline"` | "Ciência" outline + "Desconhecer" + "Não Realizada" — drawer de ações em cada linha |
| Hierarquia tipográfica | h2 título Receita Federal + h4 sub | PageHeader h1 (24px semibold) + Tabela rows 14px | h1 "Manifestação do Destinatário" + small "X pendentes · Y vencendo em 7d"; row 14px regular |
| Página título | "Manifestação do Destinatário" técnico | Título PT-BR + descrição operacional | "Notas recebidas" + sub "Confirme o recebimento das NF-e que fornecedores emitiram contra você" |

#### 3. Densidade

| Aspecto | Hoje | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Espaçamento entre seções | `<div class="row mb-3">` ~16px | `space-y-6` (24px) | `space-y-4` (16px) — densidade alta pra inbox |
| Card padding | `panel-body` ~15px | `p-6` (24px) | `p-4` (16px) — linha mais compacta |
| Row height | tabela legacy ~52px | `--row-h: 52px` | `--row-h: 48px` (densidade inbox) |
| Line-height | 1.4 | 1.5 (Tailwind) | 1.5 (paridade) |
| Gap entre cards | `gap-3` (12px) | `--card-gap: 12px` | `gap-3` (paridade) |

#### 4. Iconografia

| Aspecto | Hoje | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Sistema | Ícones bitmap SEFAZ | lucide-react 100% (R-DS-003) | lucide-react 100% |
| Ícones específicos | nenhum claro | InboxIcon, CheckCircle2, AlertTriangle, FileText | `Inbox` (empty), `CheckCircle2` (Confirmar), `XCircle` (Desconhecer), `Ban` (Não Realizada), `RefreshCw` (Buscar agora), `Clock` (countdown) |
| Cor | hardcoded amarelo/vermelho | tokens semânticos (text-foreground, text-muted-foreground) | tokens shadcn (R-DS-002); status fixo emerald/amber/red exceção pra prazo |
| Tamanho | 14-16px arbitrário | 14-16px lucide default | 14px nos botões inline / 16px nos status / 20px no PageHeader |

#### 5. Estados visuais

| Estado | Hoje | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Default | linha plana cinza | `bg-card border-border` | `bg-panel border-border` |
| Hover | sem hover | `hover:bg-muted/50` em rows clicáveis | `hover:bg-panel-2` (todas as rows clicáveis) |
| Focus | outline azul default | `focus-visible:ring-2 ring-accent bg-accent-soft` | `ring-accent bg-accent-soft` (linha em foco J/K) |
| Selecionada (bulk) | checkbox marcado sem destaque | `bg-accent-soft border-accent-2` | `bg-emerald-50 dark:bg-emerald-900/20 border-l-2 border-emerald-500` |
| Disabled (já manifestada) | row cinza muted | `opacity-50 pointer-events-none` | `opacity-60 cursor-not-allowed` (botões somem, badge "Confirmada" verde) |
| Loading | spinner gif legacy | `<Loader2 className="animate-spin">` | `<Loader2/>` no botão durante POST + `aria-busy="true"` |
| Empty | "Nenhuma nota encontrada" sem CTA | `<EmptyState icon title description primaryAction>` | `<EmptyState icon={InboxIcon} title="Nenhuma NF-e recebida" primaryAction={Buscar agora}>` |
| Error | alert vermelho server-side | `<ErrorBoundary>` + retry CTA + Sentry log | `<ErrorBoundary>` por linha (POST falha não derruba página) |

#### 6. Atalhos teclado

| Tecla | Aplicável? | Hoje | Canon | Decisão MWART |
|---|---|---|---|---|
| `⌘K` / `Ctrl+K` | Sempre (shell) | ausente | AppShellV2 busca global | shell (não desta tela) |
| `J` | Master/detail | ausente | navegar lista (`tasks.jsx`) | próxima NF-e na lista |
| `K` | Master/detail | ausente | item anterior | NF-e anterior |
| `C` | Linha em foco | ausente | n/a no canon (verbo desta tela) | **Confirmar** NF-e em foco (status=pendente) |
| `D` | Linha em foco | ausente | n/a no canon | **Desconhecer** — abre prompt justificativa |
| `R` | Linha em foco | ausente | n/a no canon | **Não Realizada** — abre prompt justificativa |
| `/` | Tela inteira | ausente | focar busca local | focar busca CNPJ/nome emitente |
| `Esc` | Sempre | ausente | fecha modal/blur input | fecha modal de justificativa |

#### 7. Persistência (localStorage)

| Chave | Hoje | Canon | Decisão MWART |
|---|---|---|---|
| Filtro status | URL only ou perde | `oimpresso.<mod>.filter` | `oimpresso.nfebrasil.manifestacao.filter` (default `pendente`) |
| Linha em foco | n/a | `oimpresso.<mod>.detail.id` | `oimpresso.nfebrasil.manifestacao.foco` (id) |
| Coluna direita colapsada | n/a | `oimpresso.linked.<bloco>.collapsed` | `oimpresso.linked.itens.collapsed`, `.fornecedor.collapsed`, `.historico.collapsed` |
| Última visita | n/a | n/a | `oimpresso.nfebrasil.manifestacao.last_visit` (mostra "X novas desde sua última visita") |

#### 8. Componentes shared

| Shared | Hoje | Canon | Decisão MWART |
|---|---|---|---|
| PageHeader | navbar custom Bootstrap | `@/Components/shared/PageHeader` | PageHeader (paridade) — título + descrição + ação "Buscar agora" |
| EmptyState | "Nenhuma nota" sem CTA | `@/Components/shared/EmptyState` | EmptyState com CTA "Buscar agora" (Q5 audit, R-DS-001) |
| KpiCard | n/a | `@/Components/shared/KpiCard` (4 cards no `os-page.jsx`) | 3 KPI: "Pendentes" / "Vencendo em 7d" / "Confirmadas no mês" |
| DataTable | Yajra DataTables jQuery | tabela inline shadcn no `os-page.jsx` | tabela inline shadcn (não DataTable shared — bulk actions custom) |
| StatusBadge | `<span class="label">` | `@/Components/shared/StatusBadge` | StatusBadge (5 status: pendente/ciencia/confirmada/desconhecida/nao_realizada) |
| LinkedApps | n/a | `LinkedOs/LinkedClient/LinkedFinanceiro` | **Criar 3 novos:** `LinkedItens`, `LinkedFornecedor`, `LinkedHistorico` |

### B. Dimensões estado-da-arte (9-15 — V2 fechando gap "feio vs bonito")

#### 9. Tipografia numérica

| Aspecto | Canon Cockpit (`os-page.jsx`) | Benchmark Linear/Vercel | Decisão MWART |
|---|---|---|---|
| KPI value (Pendentes/Vencendo) | h2 ~28px regular | 32-40px tabular-nums semibold | **40px tabular-nums semibold** + label tracking-widest text-xs uppercase muted |
| Valor monetário (R$ na lista) | mono 14px regular | 14px tabular-nums | `font-mono tabular-nums` 14px (alinhamento perfeito de R$) |
| CNPJ emitente | mono 12px muted | 12-13px mono | `font-mono` 12px text-muted-foreground (separador `12.345.678/0001-99`) |
| Countdown prazo | n/a | badge 13px tabular-nums | **15px tabular-nums semibold** dentro do badge (legível à distância) |
| Header h1 | 24px semibold | 28-32px semibold | **28px semibold** + line-height tight |

#### 10. Espaçamento numérico

| Aspecto | Canon | Benchmark | Decisão MWART |
|---|---|---|---|
| Padding linha lista | `p-3` (12px vertical) | 12-16px Linear | `py-3 px-4` (12px Y / 16px X) |
| Gap entre KPI cards | `gap-4` (16px) | `gap-6` (24px) Vercel | **`gap-4` (16px)** — cabe 3 cards em 1280px sem stretch ridículo |
| Margem topo lista após KPIs | `space-y-6` | 32-40px Linear | **`mt-6` (24px)** — separa hierarquia sem desperdício |
| Padding modal de justificativa | `p-6` | 32-40px Stripe | **`p-6` (24px)** + `max-w-md` (448px) |
| Padding coluna direita | `p-4` | 16-20px Notion | **`p-4` (16px)** + cards internos `p-3` (12px) |

#### 11. Cores semânticas warm

| Aspecto | Canon | Decisão MWART |
|---|---|---|
| Status "pendente" (badge) | `bg-amber-100 text-amber-900` | `bg-amber-50 text-amber-700` (warm sutil — não alarme) |
| Status "confirmada" | `bg-emerald-100 text-emerald-900` | `bg-emerald-50 text-emerald-700` |
| Status "desconhecida" | `bg-slate-100 text-slate-600` | `bg-slate-50 text-slate-600` |
| Status "nao_realizada" | `bg-orange-100 text-orange-900` | `bg-orange-50 text-orange-700` |
| Countdown vermelho (≤7d) | `bg-red-500 text-white` | **`bg-red-50 text-red-700 border border-red-200`** (warm urgent — não cria pânico) |
| Countdown amarelo (≤30d) | `bg-amber-500 text-white` | `bg-amber-50 text-amber-700 border border-amber-200` |
| Countdown verde (>30d) | `bg-emerald-500 text-white` | `text-muted-foreground` (sem badge — só dia restante) |
| Linha selecionada bulk | `bg-emerald-100` | **`bg-emerald-50/50` border-l-2 border-emerald-500** (warm + indicador lateral) |

> **Nota warm:** seguindo Linear/Vercel — cores `*-50` + texto `*-700` produzem destaque sem competir com texto principal. `*-500/10` (opacity) também valido mas warm é mais legível em dark mode.

#### 12. Microinterações

| Aspecto | Canon | Decisão MWART |
|---|---|---|
| Hover em row | `hover:bg-muted/50` (instantâneo) | `transition-colors duration-150 hover:bg-panel-2` |
| Focus ring | `ring-2 ring-accent` | `ring-2 ring-emerald-500/40 ring-offset-2 ring-offset-background` (suave) |
| Click confirmar | sem feedback além do POST | **Animation pulse no checkbox + toast.success "Manifestação registrada SEFAZ"** |
| Bulk select all | toggle sem animação | **Stagger animation** — checkboxes preenchem em sequência (50ms delay) — feedback visual de "muito" |
| Sombra cards | sem | `shadow-sm` em KPI cards / `shadow-md` em modal |
| Backdrop blur modal | sem | `backdrop-blur-sm bg-background/80` |
| Empty state | aparece direto | `animate-in fade-in slide-in-from-bottom-2 duration-300` |
| Sticky header bulk | aparece sem transição | `sticky top-0 z-10 backdrop-blur` quando há seleção |

#### 13. Referência visual aprovada

| Item | Status |
|---|---|
| Screenshot Wagner aprovado | ❌ **AUSENTE** — não havia tela "estado da arte" similar pra colar; tela é greenfield no domínio fiscal BR |
| Canon Cockpit aprovado via [_DS UI-0010](../_DesignSystem/adr/ui/0010-zip-cowork-2026-04-27-canon-visual.md) | ✅ `os-page.jsx` + `tasks.jsx` aprovados como canon |
| Protótipo `prototipos/manifestacao/` | ❌ **AUSENTE** — diretório `prototipo-ui/` está em SETUP (HANDOFF.md confirma) |
| **Wagner aprovação requerida** | ⏳ Wagner revisa este `visual-comparison.md` + opcionalmente cola screenshot de tela inspiradora (Linear inbox? Notion master/detail? Mailgun events?) |

> ⚠️ Sem screenshot externo aprovado, a referência canônica é o canon Cockpit (`os-page.jsx`+`tasks.jsx`). Wagner pode aprovar baseado neste documento OU pedir round 2 com screenshot Cowork.

#### 14. Benchmarks externos

| Tipo | Player benchmark | Por que é referência | Aplicável aqui? |
|---|---|---|---|
| Inbox de aprovações | **Linear** (`linear.app/inbox`) | Master/detail rápido, atalhos J/K canônicos, badges status warm | ✅ adotar atalhos + densidade |
| Master/detail bulk | **Notion** databases | Bulk actions com count visível + sticky toolbar | ✅ adotar sticky bulk toolbar |
| Countdown urgente | **Stripe Dashboard** (events) | Countdown colorido sutil, não alarmista | ✅ adotar warm cores + tabular-nums |
| Lista fiscal BR | **TecnoSpeed Plug Notas** | Concorrente com manifestação destinatário | parcial — eles fazem 1-by-1 modal cheio; nosso bulk é diferencial |
| Empty states | **Vercel dashboard** | CTA claro + ilustração simples | ✅ adotar pattern |

> Persona power-user (operadora de gráfica) prioriza **velocidade** (Linear-like) sobre **beleza** (Notion-like). Equilíbrio: estética Notion + atalhos Linear.

#### 15. Persona priorização

| Decisão | Pra Operadora Gold (1280px provável, ~50/mês) |
|---|---|
| **#1 — Bulk Confirmar dominante** | Botão sticky topo SEMPRE quando há seleção. Mostra count gigante (32px tabular-nums). Sem scroll pra encontrar. **Vence sobre estética minimalista** (Notion não destacaria tanto). |
| **#2 — Countdown vermelho no topo** | Linhas com prazo ≤7d sobem pro topo automaticamente (sort default). Linha vermelha primeira coisa que opedora vê. **Vence sobre ordem cronológica natural.** |
| **#3 — Atalho `C` (não `Enter`)** | Confirma sem confirmação adicional após `C` (operadora confia no fluxo). Modal só pra Desconhecer/Não Realizada (que precisam justificativa). **Vence sobre "sempre confirmar destrutivo"** — confirmação prévia já está no UX (ela vê a linha em foco). |
| Apps Vinculados ao lado | xl: 1280+ (visível); lg: colapsa ícones. Larissa-Gold em 1280px provável → tela limítrofe — colapsável é importante. |
| Densidade alta | 48px row height (vs 56px Notion) — cabe 12 linhas em 768px de altura útil. |

## Gaps identificados (Wagner valida)

> Concretamente o que pode ser ajustado se Wagner quiser:

- ⚠ **Sem screenshot externo aprovado** — Wagner pode pedir round 2 colando inspiração (Linear inbox? Stripe events?) pra eu calibrar mais
- ⚠ **Topnav módulo NfeBrasil** — preciso adicionar item "Manifestação" ao topnav existente (`Configuração / Tributação / Status` → adicionar "Manifestação"). Mudança no `Modules/NfeBrasil/Resources/menus/topnav.php` ou similar
- ⚠ **3 LinkedApps NOVOS** (LinkedItens / LinkedFornecedor / LinkedHistorico) — não existem em `Components/LinkedApps/`. Decisão: criar específicos pra esta tela ou genéricos pro módulo NfeBrasil?
- ⚠ **KPI cards no topo** — canon `os-page.jsx` tem 4 KPI cards. Sugiro 3 (Pendentes/Vencendo7d/Confirmadas-mês). Wagner pode preferir 4 ou 0 (deixar tela mais densa)
- ⚠ **Animação stagger bulk** — proposto mas opcional. Custo dev ~1h. Pode ficar pra v2
- ⚠ **Persona Gold ainda não validada** — assumi monitor 1280px (extrapolando ROTA LIVRE). Wagner pode confirmar/corrigir
- ⚠ **Atalho `C` sem confirmação prévia** — proposta agressiva. Concorre com proibição "confirma destrutivo" ([CHECKLIST §F.2 Q2](../../../.claude/skills/cockpit-runbook/CHECKLIST.md)). Justifico pela velocidade necessária da persona; bulk Confirmar SIM tem confirm. Wagner decide

## Sub-skills Anthropic — opcionais nesta iteração

Skill mwart-comparative V4 orquestra 6 sub-skills do Claude Design plugin. Pra economizar tempo de Wagner, GERA esta primeira versão sem invocar todas. Se quiser maior profundidade ANTES de aprovar, posso invocar:

- [ ] `design:design-handoff` — specs exatas (px, fontes, animações) por componente
- [ ] `design:ux-copy` — review de microcopy ("Confirmar selecionadas" vs "Confirmar 12 NF-e" — qual melhor?)
- [ ] `design:accessibility-review` — WCAG 2.1 AA full audit (contrast, touch targets, screen reader)
- [ ] `design:design-system` — audit consistência tokens vs canon Cockpit (já cobri parcialmente)
- [ ] `design:research-synthesis` — análise persona Gold em profundidade
- [ ] `design:design-critique` — após implementação (F3 pós-impl), critique do screenshot real

## Decisão final

> Wagner aprova / ajusta / rejeita. Após aprovação:
> 1. Mudar `status: draft → approved` no frontmatter
> 2. Assinar `approved_by: wagner` + `approved_at: <data>`
> 3. Apender linha em [`prototipo-ui/SYNC_LOG.md`](../../../prototipo-ui/SYNC_LOG.md): `2026-MM-DD HH:MM [W] approved manifestacao-visual-comparison`
> 4. Eu prossigo pra F3 IMPL (`Pages/NfeBrasil/Manifestacao/Index.tsx`)

### Opções de aprovação

- **A — Aprovar como está** → eu codo Page Inertia exatamente como descrito
- **B — Aprovar com ajustes específicos** → você lista mudanças (ex: "KPI 4 cards", "row 56px", "remover stagger") e eu re-gero ou já aplico no código
- **C — Pedir round 2 com screenshot inspiração** → você cola URL/print de tela "estado da arte" (Linear / Stripe / outro) e eu re-calibro 15 dimensões antes de codar
- **D — Invocar sub-skills design:*** → maior profundidade antes de aprovar (ux-copy + a11y + handoff)
- **E — Rejeitar e pedir abordagem diferente** → caminho alternativo (ex: "não use master/detail, use cards Pinterest-style")

---

**Última atualização:** 2026-05-09
