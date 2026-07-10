---
name: mwart-comparative
description: Use SEMPRE antes de codar Page Inertia em migração MWART (Blade→React) no oimpresso. Skill Tier B auto-trigger V4 que ORQUESTRA o Claude Design plugin Anthropic (design:design-critique + design:design-handoff + design:design-system + design:ux-copy + design:accessibility-review + design:research-synthesis) **e** o loop Cowork ↔ Claude Code formalizado em `prototipo-ui/` (ADR 0114). Gera artefato OBRIGATÓRIO `memory/requisitos/<Mod>/<tela>-visual-comparison.md` com 15 dimensões + framework Anthropic completo + sincroniza com `prototipo-ui/SYNC_LOG.md`. Gate visual do draft = CI (PR UI Judge + visual-regression — ADR 0241 emenda 0107; ratificado ADR 0282), NÃO aprovação síncrona de screenshot; travas objetivas mantidas (critique ≥80 + WCAG AA; <70 escala revisão dedicada); merge de `.tsx` segue humano-no-loop (ADR 0283) e F5 CUTOVER segue humano (ADR 0104). Ativa quando user pede "migrar tela X pra MWART", "comparativo visual", "/mwart-comparative <tela>", OU em qualquer Edit/Write em Page Inertia que não tenha visual-comparison.md ao lado.
tier: B
auto_trigger: path
resumo: gate visual F1.5 + loop Cowork ↔ Code (V4, [`prototipo-ui/PROTOCOL.md`](prototipo-ui/PROTOCOL.md)). Orquestra Claude Design plugin (design-critique + design-system + design-handoff + ux-copy + accessibility-review + research-synthesis). 15 dimensões + gate visual via CI ([ADR 0241](memory/decisions/0241-loop-design-cowork-code-autonomo-zero-humano.md) emenda 0107; Protocolo v2 [ADR 0282](memory/decisions/0282-protocolo-v2-colapso-ratificacao.md)); merge de `.tsx` segue humano ([ADR 0283](memory/decisions/0283-handoff-loop-zero-paste.md)) — [ADR 0114](memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) + [ADR 0107](memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) + [ADR 0109](memory/decisions/0109-claude-design-plugin-integrado-processo-mwart.md)
status: active
version: 4.1
authority: canonical
parent_adr: 0114
related_adrs: [0107, 0109, 0114, 0241, 0282, 0283]
---

# Skill: mwart-comparative V4 — Loop design supervisionado + Cowork integrado (Tier B auto-trigger)

> **🔁 Reconciliação v2 (V4.1 · 2026-07-02):** o gate "Wagner aprova SCREENSHOT síncrono" desta skill foi **emendado pela [ADR 0241](../../memory/decisions/0241-loop-design-cowork-code-autonomo-zero-humano.md)** (`amends: [0107]` — F2 screenshot síncrono → CI: PR UI Judge + visual-regression) e **ratificado pela [ADR 0282](../../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md)** (Protocolo v2). **O que NÃO mudou:** artefato `<tela>-visual-comparison.md` continua OBRIGATÓRIO (15 dimensões); travas objetivas critique **≥80** + **WCAG AA** mantidas (<70 → escala revisão dedicada); **merge de `.tsx` = PR + review humano, nunca auto-merge** ([ADR 0283](../../memory/decisions/0283-handoff-loop-zero-paste.md)); **F5 CUTOVER** do MWART (canary 7d + aviso cliente) segue humano ([ADR 0104](../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md), intocada). Wagner pode SEMPRE pedir revisão síncrona — ela deixou de ser bloqueio default, não deixou de existir.

> **Documentos mãe:**
> - [ADR 0114](../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) (loop Cowork ↔ Claude Code formalizado) — **mãe direta**
> - [ADR 0109](../../memory/decisions/0109-claude-design-plugin-integrado-processo-mwart.md) (orquestração Claude Design plugin Anthropic) — emendada por 0114
> - [ADR 0107](../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) (gate F1.5) — **emendada por 0241** (gate síncrono → CI)
> **Skills irmãs (oimpresso):** [`mwart-process`](../mwart-process/SKILL.md) (Tier A canon), [`cockpit-runbook`](../cockpit-runbook/SKILL.md) (F1+audit), [`mwart-quality`](../mwart-quality/SKILL.md) (F2+F3 técnico).
> **Sub-skills Anthropic orquestradas (Claude Design plugin):**
>   - `design:design-critique` ⭐ — crítica estruturada 5 categorias + comparação benchmarks
>   - `design:design-system` — audit consistência tokens/components
>   - `design:design-handoff` — specs exatas pré-impl (layout, props, estados, responsividade)
>   - `design:ux-copy` — review microcopy (labels, placeholders, CTAs, errors)
>   - `design:accessibility-review` — WCAG 2.1 AA audit
>   - `design:research-synthesis` — análise persona + padrões uso

## Por que esta skill existe

Migração `/sells/create` (PRs #240-#248, 2026-05-08) entregou tela **tecnicamente correta** (audit 92/100, Pest 31, build OK) mas **esteticamente comum** — Wagner reclamou que era inferior à era Repair S2.5. Investigação git mostrou que **3 práticas sumiram**:

1. **Sessão design Cowork síncrona** Wagner-Claude (origem do canon `os-page.jsx`)
2. **Loop "tela branca em prod → fix" como aprendizado** (3 PRs corretivos viraram Checks 1-9)
3. **Tabela comparativa Blade vs Cockpit vs Canon** — sempre IMPLÍCITA, nunca formalizada

Esta skill **codifica o que estava na cabeça do Wagner** como artefato + gate. Wagner volta ao loop em F1.5 (5min síncrono) **antes** de codar — evita PRs corretivos depois.

## Quando ativa (Tier B auto-trigger)

| Gatilho | Output |
|---|---|
| User pede *"migrar tela X pra MWART"*, *"comparativo visual de X"*, *"/mwart-comparative X"* | Gera draft `<tela>-visual-comparison.md` |
| Edit/Write em `resources/js/Pages/<Mod>/<Tela>.tsx` SEM `<tela>-visual-comparison.md` ao lado | **Recusa** com mensagem PT-BR explicando + comando |
| RUNBOOK existe (skill `cockpit-runbook` rodou em F1) mas visual-comparison ausente | Roda automático ao detectar inconsistência |

**Regra de ouro:** F1 (RUNBOOK) → F1.5 (visual-comparison) → F2 (BACKEND BASELINE) → F3 (FRONTEND). NÃO pular F1.5.

## Workflow V4 obrigatório (orquestra Claude Design plugin + loop `prototipo-ui/`)

```
═══ F0 SINCRONIZAR LOOP ═════════════════════════════════════════════════
0. Ler prototipo-ui/HANDOFF.md → identificar tela em qual fase
   - Se já há protótipo Cowork em prototipos/<tela>/page.tsx, consumir como
     fonte de verdade visual no passo 5
   - Se não há protótipo, este é fluxo MWART direto (sem Cowork)

═══ F1.5 PRE-IMPL ═══════════════════════════════════════════════════════
1. Receber: tela alvo + módulo + URL Blade legacy
2. EXIGIR REFERÊNCIA VISUAL APROVADA:
   - Wagner cola screenshot/URL de tela que considera "estado da arte"
   - OU: prototipos/<tela>/page.tsx do Cowork (já é referência aprovada)
   - SE referência ausente: PARAR e pedir antes de prosseguir

3. Read paralelo (1 round):
   - Blade legacy view + Controller
   - Canon Cockpit (os-page.jsx / tasks.jsx / chat.jsx / viewers.jsx)
   - DESIGN.md §6-§15
   - RUNBOOK existente

4. Invocar `design:research-synthesis` se persona/padrão de uso for novo
   (ex: 1ª tela do módulo) — economiza re-análise depois

5. Invocar `design:design-system` pra audit consistência:
   - Tokens shadcn vs canon Cockpit (var --bubble-me, --row-h, etc)
   - Componentes shared vs custom inline
   - Padrões de cor warm vs neutral

6. Invocar `design:design-handoff` pra clarificar specs:
   - Layout exato (header, sidebar, topnav, body)
   - Props contract TypeScript
   - Estados (default/hover/focus/active/disabled/loading/empty/error)
   - Responsividade breakpoints
   - Animações + microinterações

7. Invocar `design:ux-copy` pra review microcopy:
   - Labels ("Cliente" vs "Comprador")
   - Placeholders ("Buscar produto…" vs "Digite SKU")
   - Empty states (CTA convite vs informa só)
   - Error messages
   - Botões CTA

8. Identificar TIPO de tela (form / list / master-detail / inbox / chat / dashboard)
   e benchmark externo correspondente (Stripe Checkout / Linear / Notion / Vercel)

9. Gerar tabela 15 dimensões com NÚMEROS CONCRETOS (vide §dimensões)

10. Capturar SCREENSHOT proposta:
    - Se tela similar existe: Chrome MCP screenshot dela como referência
    - Se não: mockup textual estruturado com px concretos

11. Salvar em memory/requisitos/<Mod>/<tela-kebab>-visual-comparison.md
    com frontmatter status: draft + sub-skills outputs anexados

12. Apresentar visual-comparison COMPLETO no chat/PR (incluindo screenshots)
    — informativo, NÃO bloqueio síncrono (ADR 0241/0282: gate visual = CI).
    Se Wagner PEDIR revisão síncrona, aguardar — o direito dele é permanente.

12.5. Se há protótipo Cowork em prototipos/<tela>/, gravar critique score
      em prototipos/<tela>/critique-score.json com formato:
      { "score": NN, "first_impression": "...", "strengths": [...],
        "weaknesses_priority": [...], "benchmark_comparable": "..." }

13. Trava objetiva ANTES de codar (substitui a espera síncrona — ADR 0241):
    critique score ≥80 prossegue · 70-79 = 1 round refator + re-critique ·
    <70 = escala revisão dedicada (não prossegue sozinho).

14. Atualizar frontmatter status: approved + assinar approved_by (gate CI
    "PR UI Judge + visual-regression" OU "[W]" se revisão síncrona ocorreu)
    + approved_at.

═══ F2-F3 IMPL ══════════════════════════════════════════════════════════
15. Desbloquear `mwart-quality` Tier B + começar implementação.

═══ F3 PÓS-IMPL ═════════════════════════════════════════════════════════
16. Screenshot REAL via Chrome MCP da tela implementada em prod (biz=1).

17. Invocar `design:design-critique` ⭐ sobre screenshot real:
    - First impression (2 segundos)
    - Usability (severity table)
    - Visual hierarchy (reading flow)
    - Consistency (design system audit)
    - Accessibility (WCAG)
    - What works well
    - Priority recommendations (3 ações)
    - **Comparação benchmark externo:** "essa tela parece feita pela equipe
      de Linear / Vercel / Stripe?"

18. Anexar critique no visual-comparison.md (seção B).

19. Se score critique ≥80 → PR pronto pra merge (checks required verdes;
    merge de `.tsx` = review humano, nunca auto-merge — ADR 0283).
    Se score 70-79 → 1 round refator + re-critique.
    Se score <70 → discussão Wagner (rebuild ou aceita exceção).

═══ F3.5 ACCESSIBILITY ══════════════════════════════════════════════════
20. Invocar `design:accessibility-review` (WCAG 2.1 AA):
    - Color contrast (todos os pares de cor)
    - Touch targets (≥44×44px)
    - Keyboard navigation (Tab order, Esc, focus-visible)
    - ARIA labels + roles
    - Screen reader test

21. Anexar accessibility report no visual-comparison.md (seção F).

22. Se WCAG falha CRITICAL → bloqueia merge até fix.

═══ F4 QA + TIER 2 ══════════════════════════════════════════════════════
23. Pest 4 Browser snapshot baseline (ADR 0108) — locked.

24. Próximas PRs que mexerem nesta tela: gate visual diff + design-critique
    automático em mudanças >threshold.

═══ F5 SYNC LOOP ════════════════════════════════════════════════════════
25. Append em prototipo-ui/SYNC_LOG.md uma linha registrando o evento:
    `YYYY-MM-DD HH:MM [CL] PR #NNN merged <tela> score=NN`
    (Necessário pra métrica `design_loop_stuck` em jana:health-check.)

26. Atualizar prototipo-ui/HANDOFF.md (sobrescrever):
    - Remover tela da seção "Em voo agora"
    - Mover próxima tela da TELAS_REVIEW_QUEUE.md pra "Em voo agora"
```

## 15 dimensões obrigatórias do `<tela>-visual-comparison.md`

### A. Estrutura (8 dimensões originais — V1)

| # | Dimensão | Pergunta-chave |
|---|---|---|
| 1 | **Layout** | Header? Sidebar? Topnav módulo (inline com breadcrumb? linha separada?)? Footer sticky? Grid breakpoints? |
| 2 | **Hierarquia visual** | 1 ação primária? 2 secundárias? Hierarquia tipográfica (h1 > h2 > body)? |
| 3 | **Densidade** | Espaçamento (Tailwind `space-y-X`)? Line-height? Card-pad? |
| 4 | **Iconografia** | lucide-react? Emoji? SVG? Ausente? Coerente entre seções? |
| 5 | **Estados visuais** | hover/focus/active/disabled/loading/empty/error — quais relevantes? |
| 6 | **Atalhos teclado** | J/K/E/A (master/detail)? `/` busca? Esc fecha? ⌘+Enter submit? |
| 7 | **Persistência** | localStorage prefixo `oimpresso.<mod>.<tela>.*`? URL only? sessionStorage (proibido)? |
| 8 | **Componentes shared** | PageHeader, EmptyState, KpiCard, DataTable, StatusBadge — quais reusar |

### B. Estado da arte (7 dimensões V2 — gap-fix Wagner 2026-05-08)

> Wagner observou que skill V1 cobria estrutura mas não capturava "feio vs bonito". Estas 7 dimensões fecham o gap.

| # | Dimensão | Pergunta-chave |
|---|---|---|
| 9 | **Tipografia numérica** | KPI value px exatos (22px≠40px muda percepção)? Label tracking-widest? Pesos variando (regular/semibold/bold)? Line-height generoso? |
| 10 | **Espaçamento numérico** | Padding (p-4≠p-6≠p-8)? Gap entre cards (gap-3≠gap-6)? Margem vertical (space-y-4≠space-y-8)? |
| 11 | **Cores semânticas warm** | bg-amber-50 vs bg-amber-500/10 (warm > /opacity)? text-emerald-700 vs text-emerald-600? Cor de destaque sutil em status? |
| 12 | **Microinterações** | hover transition? backdrop-blur? Sombras sutis (shadow-sm vs shadow-none)? Animação de focus ring? |
| 13 | **Referência visual aprovada** | Wagner colou screenshot/URL de tela "estado da arte"? Salvo em arquivo? |
| 14 | **Benchmarks externos** | Quais 1-2 SaaS estado-da-arte do tipo de tela? (form: Stripe Checkout · list: Linear · dashboard: Vercel · inbox: Notion) |
| 15 | **Persona priorização** | Pra Larissa 1280px ROTA LIVRE: KPIs gigantes > sidebar elegante. Decisões mudam por persona — listar top 3. |

**Cada dimensão tem 3 colunas:**
- **Blade legacy** — como está hoje (px concretos quando possível)
- **Canon Cockpit / Referência aprovada** — números do canon ou screenshot Wagner
- **Decisão MWART** — paridade / melhoria / exceção justificada (com px concretos)

**Cada dimensão tem 3 colunas:**
- **Blade legacy** — como está hoje
- **Canon Cockpit** — como o `<canon>.jsx` faz
- **Decisão MWART** — paridade / melhoria / exceção justificada

Detalhes + exemplo end-to-end em [TEMPLATE.md](TEMPLATE.md).

## Output esperado

**Caminho:** `memory/requisitos/<Mod>/<tela-kebab>-visual-comparison.md`
**Exemplo:** `memory/requisitos/Sells/sells-create-visual-comparison.md`

**Frontmatter obrigatório:**

```yaml
---
slug: <mod-lower>-<tela-kebab>-visual-comparison
title: "<Mod> — Comparativo visual da tela <Nome legível>"
type: visual-comparison
module: <Mod>
status: draft   # mudará pra "approved" via gate CI (UI Judge + visual-regression) ou revisão [W] quando solicitada
date: <YYYY-MM-DD>
canon_reference: <os-page.jsx | tasks.jsx | chat.jsx | viewers.jsx>
blade_source: resources/views/<modulo>/<tela>.blade.php
inertia_target: resources/js/Pages/<Mod>/<Tela>.tsx
---
```

## Anti-padrões (NUNCA fazer)

- ❌ **Pular F1.5** (codar Page sem visual-comparison) — hook bloqueia em runtime; CI bloqueia no merge
- ❌ **Marcar status=approved sem o gate visual ter passado** (CI UI Judge + visual-regression, ou revisão [W] quando ele pediu) — quebra confiança do loop. Aprovação inventada é pior que draft honesto
- ❌ **Inventar comparação sem ler Blade real** — leitura paralela é obrigatória (workflow §2)
- ❌ **Inventar canon** — só usar `.jsx` que existe em `ui_kits/cowork-2026-04-27/`
- ❌ **<6 dimensões preenchidas** — CI bloqueia. Marcar `N/A — justificativa` em vez de pular
- ❌ **Coluna "Decisão MWART" com `TODO` ou `???`** — todas as decisões precisam estar resolvidas antes de F3

## Override autorizado

Wagner pode autorizar exceção via comentário PR: `/mwart-override <razão>`. Exceção registrada em ADR per-tela `memory/decisions/<NNNN>-mwart-excecao-<mod>-<tela>.md` (lifecycle `historical`).

Sem `/mwart-override`, gates não cedem. Iniciante (`[L]`), esposa (`[E]`), Maíra, Felipe, Wagner — todos passam pelo MESMO caminho.

## Refs

- [ADR 0114 — Loop Cowork ↔ Claude Code formalizado](../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — **mãe direta** (V4)
- [ADR 0109 — Claude Design plugin integrado](../../memory/decisions/0109-claude-design-plugin-integrado-processo-mwart.md) — emendada por 0114
- [ADR 0107 — Emendation 0104 visual gate F3](../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — documento mãe original
- [ADR 0104 — Processo MWART canônico](../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) — emendado
- [prototipo-ui/PROTOCOL.md](../../prototipo-ui/PROTOCOL.md) — protocolo formal do loop (V4)
- [prototipo-ui/HANDOFF.md](../../prototipo-ui/HANDOFF.md) — estado vivo (Passo 0 lê este)
- [prototipo-ui/SYNC_LOG.md](../../prototipo-ui/SYNC_LOG.md) — timeline (Passo 25 escreve aqui)
- [TEMPLATE.md](TEMPLATE.md) — template completo `<tela>-visual-comparison.md`
- [Canon `os-page.jsx`](../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27/os-page.jsx) — referência list+detail
- [Skill `cockpit-runbook`](../cockpit-runbook/SKILL.md) — F1 RUNBOOK + F3 audit
- [Skill `mwart-quality`](../mwart-quality/SKILL.md) — F2/F3 pré-flight checks técnicos
- [Skill `mwart-process`](../mwart-process/SKILL.md) — processo MWART canônico 5 fases

---

**Última atualização:** 2026-05-09 — V3 → V4 (ADR 0114 loop Cowork formalizado)
