---
name: mwart-comparative
description: Use SEMPRE antes de codar Page Inertia em migração MWART (Blade→React) no oimpresso. Skill Tier A always-on que gera artefato OBRIGATÓRIO `memory/requisitos/<Mod>/<tela>-visual-comparison.md` — tabela 3 colunas (Blade legacy / Canon Cockpit / Decisão MWART) cobrindo 8 dimensões visuais. Skill PARA após gerar draft e aguarda Wagner aprovar (~5min síncrono) ANTES de qualquer Edit/Write em `resources/js/Pages/<Mod>/<Tela>.tsx`. Restaura o loop "design supervisionado" que existia em Repair S2.5 (PRs #138-145) e foi diluído na Constituição V2. Ativa quando user pede "migrar tela X pra MWART", "comparativo visual", "/mwart-comparative <tela>", OU em qualquer Edit/Write em Page Inertia que não tenha visual-comparison.md ao lado.
tier: A
status: active
version: 1.0
authority: canonical
parent_adr: 0107
---

# Skill: mwart-comparative — Loop design supervisionado em F1.5 (Tier A always-on)

> **Documento mãe:** [ADR 0107](../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) (emenda ADR 0104). Esta skill implementa o gate visual obrigatório de F3 FRONTEND INCREMENTAL.
> **Skills irmãs:** [`mwart-process`](../mwart-process/SKILL.md) (Tier A canon), [`cockpit-runbook`](../cockpit-runbook/SKILL.md) (F1 PLAN + F3/F4 audit), [`mwart-quality`](../mwart-quality/SKILL.md) (F2/F3 pré-flight).

## Por que esta skill existe

Migração `/sells/create` (PRs #240-#248, 2026-05-08) entregou tela **tecnicamente correta** (audit 92/100, Pest 31, build OK) mas **esteticamente comum** — Wagner reclamou que era inferior à era Repair S2.5. Investigação git mostrou que **3 práticas sumiram**:

1. **Sessão design Cowork síncrona** Wagner-Claude (origem do canon `os-page.jsx`)
2. **Loop "tela branca em prod → fix" como aprendizado** (3 PRs corretivos viraram Checks 1-9)
3. **Tabela comparativa Blade vs Cockpit vs Canon** — sempre IMPLÍCITA, nunca formalizada

Esta skill **codifica o que estava na cabeça do Wagner** como artefato + gate. Wagner volta ao loop em F1.5 (5min síncrono) **antes** de codar — evita PRs corretivos depois.

## Quando ativa (Tier A)

| Gatilho | Output |
|---|---|
| User pede *"migrar tela X pra MWART"*, *"comparativo visual de X"*, *"/mwart-comparative X"* | Gera draft `<tela>-visual-comparison.md` |
| Edit/Write em `resources/js/Pages/<Mod>/<Tela>.tsx` SEM `<tela>-visual-comparison.md` ao lado | **Recusa** com mensagem PT-BR explicando + comando |
| RUNBOOK existe (skill `cockpit-runbook` rodou em F1) mas visual-comparison ausente | Roda automático ao detectar inconsistência |

**Regra de ouro:** F1 (RUNBOOK) → F1.5 (visual-comparison) → F2 (BACKEND BASELINE) → F3 (FRONTEND). NÃO pular F1.5.

## Workflow obrigatório

```
1. Receber: tela alvo + módulo + URL Blade legacy
2. Read paralelo (1 round):
   - Blade legacy view: resources/views/<modulo>/<tela>.blade.php
   - Controller: app/Http/Controllers/<X>Controller.php (ação @<tela>)
   - Canon Cockpit relevante: ui_kits/cowork-2026-04-27/<padrão>.jsx
     · list+detail → os-page.jsx
     · inbox/triage → tasks.jsx
     · chat/threads → chat.jsx
     · master-detail → viewers.jsx
   - DESIGN.md §6-§15 (padrões técnicos)
   - RUNBOOK existente: memory/requisitos/<Mod>/RUNBOOK-<tela>.md
3. Identificar TIPO de tela (form / list / master-detail / inbox / chat / dashboard)
4. Gerar tabela 8 dimensões via TEMPLATE.md
5. Salvar em memory/requisitos/<Mod>/<tela-kebab>-visual-comparison.md com frontmatter status: draft
6. PARAR. Apresentar tabela ao Wagner em chat.
7. Aguardar Wagner aprovar / ajustar (~5min síncrono)
8. Atualizar arquivo com frontmatter status: approved
9. SOMENTE ENTÃO desbloquear F2/F3 (skill irmã `mwart-quality` ativa)
```

## 8 dimensões obrigatórias do `<tela>-visual-comparison.md`

| # | Dimensão | Pergunta-chave |
|---|---|---|
| 1 | **Layout** | Header? Sidebar? Topnav módulo? Footer sticky? Grid breakpoints? |
| 2 | **Hierarquia visual** | 1 ação primária? 2 secundárias? Hierarquia tipográfica (h1 > h2 > body)? |
| 3 | **Densidade** | Espaçamento (Tailwind `space-y-X`)? Line-height? Card-pad? |
| 4 | **Iconografia** | lucide-react? Emoji? SVG? Ausente? Coerente entre seções? |
| 5 | **Estados visuais** | hover/focus/active/disabled/loading/empty/error — quais relevantes? |
| 6 | **Atalhos teclado** | J/K/E/A (master/detail)? `/` busca? Esc fecha? ⌘+Enter submit? |
| 7 | **Persistência** | localStorage prefixo `oimpresso.<mod>.<tela>.*`? URL only? sessionStorage (proibido)? |
| 8 | **Componentes shared** | PageHeader, EmptyState, KpiCard, DataTable, StatusBadge — quais reusar |

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
status: draft   # mudará pra "approved" após Wagner revisar
date: <YYYY-MM-DD>
canon_reference: <os-page.jsx | tasks.jsx | chat.jsx | viewers.jsx>
blade_source: resources/views/<modulo>/<tela>.blade.php
inertia_target: resources/js/Pages/<Mod>/<Tela>.tsx
---
```

## Anti-padrões (NUNCA fazer)

- ❌ **Pular F1.5** (codar Page sem visual-comparison) — hook bloqueia em runtime; CI bloqueia no merge
- ❌ **Marcar status=approved sem Wagner ver** — quebra confiança do loop. Sempre PARAR e esperar
- ❌ **Inventar comparação sem ler Blade real** — leitura paralela é obrigatória (workflow §2)
- ❌ **Inventar canon** — só usar `.jsx` que existe em `ui_kits/cowork-2026-04-27/`
- ❌ **<6 dimensões preenchidas** — CI bloqueia. Marcar `N/A — justificativa` em vez de pular
- ❌ **Coluna "Decisão MWART" com `TODO` ou `???`** — todas as decisões precisam estar resolvidas antes de F3

## Override autorizado

Wagner pode autorizar exceção via comentário PR: `/mwart-override <razão>`. Exceção registrada em ADR per-tela `memory/decisions/<NNNN>-mwart-excecao-<mod>-<tela>.md` (lifecycle `historical`).

Sem `/mwart-override`, gates não cedem. Iniciante (`[L]`), esposa (`[E]`), Maíra, Felipe, Wagner — todos passam pelo MESMO caminho.

## Refs

- [ADR 0107 — Emendation 0104 visual gate F3](../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — documento mãe
- [ADR 0104 — Processo MWART canônico](../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) — emendado
- [TEMPLATE.md](TEMPLATE.md) — template completo `<tela>-visual-comparison.md`
- [Canon `os-page.jsx`](../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27/os-page.jsx) — referência list+detail
- [Skill `cockpit-runbook`](../cockpit-runbook/SKILL.md) — F1 RUNBOOK + F3 audit
- [Skill `mwart-quality`](../mwart-quality/SKILL.md) — F2/F3 pré-flight checks técnicos
- [Skill `mwart-process`](../mwart-process/SKILL.md) — processo MWART canônico 5 fases

---

**Última atualização:** 2026-05-08
