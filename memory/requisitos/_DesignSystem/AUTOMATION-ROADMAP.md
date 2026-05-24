---
doc: AUTOMATION-ROADMAP
camada: meta-protocolo
status: planejado
created: 2026-05-24
parent_adr: UI-0013
related_adrs: [UI-0013, UI-0014, 0187]
target: subir enforcement de 30% → 90% automatizado
estimate_total: 17-25h em 4 ondas
quando_executar: "sob demanda · ondas independentes · cada onda vira PR"
---

# AUTOMATION-ROADMAP · Constituição UI v2

> **Status:** planejado · nenhuma onda executada.
> **Objetivo:** subir enforcement da [Constituição UI v2](adr/ui/0013-constituicao-ui-v2-camadas.md) de **~30% automatizado** (CI Module Grades + skills Tier A + CLAUDE.md SessionStart) pra **~90% automatizado** (CI lint + visual regression + LLM-judge). 10-20% restante é palpite estético — sempre humano.

## Por que existe

Wagner aprovou Constituição UI v2 em 2026-05-24 (PR #1438). Pergunta dele: *"isso vai pra minha rede MCP? minha equipe vai saber como operar. ou ainda depende de humano para não errar?"*. Resposta honesta: **70% disciplina humana**. Este roadmap é o caminho pra subir.

Não é compromisso de execução. É **plano executável** — cada onda pode ser pegada em sessão dedicada, isolada, vira PR próprio.

---

## Estado atual (~30% automatizado)

### ✅ O que JÁ enforça automaticamente

| Mecanismo | Camada coberta | Como |
|---|---|---|
| **CLAUDE.md raiz** carrega no SessionStart | meta | Todo Claude novo lê passo 4 protocolo UI + seção Hierarquia UI v2 |
| **Skill `mwart-process` Tier A v1.2** | 3-PT | Description-match em "migrar tela / Edit em Pages/" — cita PT-01 + PRE-MERGE-UI |
| **Skill `multi-tenant-patterns` Tier A** | 4-Módulo | Força `business_id` global scope (ADR 0093) |
| **Skill `commit-discipline` Tier A** | meta | 1 PR = 1 intent · ≤300 linhas · conventional commits |
| **Skill `preflight-modulo`** (hook bloqueador) | 4-Módulo | Edit em `Modules/<X>/` exige leitura prévia SPEC/RUNBOOK |
| **Module Grades Gate CI** | 4-Módulo | Bloqueia PR se nota módulo baixar vs baseline |
| **Pest tests** | 4-Módulo | Quebram se mudança breaking detectada |
| **ADR 0187 indexação MCP** | meta | `decisions-search "constituição"` retorna · `brief-fetch` lista decisão 24h |

### ❌ O que NÃO enforça (depende de disciplina)

- "Sidebar light, não dark" → só docs ([UI-0009](adr/ui/0009-cockpit-sidebar-light-padrao.md) + [UI-0014](adr/ui/0014-sidebar-light-mantida-v2-parcial.md))
- "PT-01 tem 6 slots fixos" → só doc, não tem AST validando
- "Não introduzir 6ª origin badge" → só doc
- "Pedido vago, pergunte" → depende de agente seguir regra-mestre
- "Hierarquia camadas — Módulo não toca Fundação" → só doc
- "Cor hardcoded em Pages" → não tem grep CI ativo

---

## 🌊 Onda 1 — Fundação automação (~1h30) · sobe 30→50%

### Item 1.1 · Skill `constituicao-ui-aware` Tier A

**O que faz:** description-match em "Edit/Write em `resources/js/Pages/`", "criar tela nova", "tocar Components/shared/UI/". Carrega no contexto **antes do agente codar**:
- UI-0013 chave (2 parágrafos: hierarquia 4 camadas + regra-mestre pedido vago)
- PT aplicável conforme tipo de tela (Index = PT-01)
- 8 anti-padrões PRE-MERGE-UI (AP1-AP8) em lista curta

**Resultado:** próxima sessão Maiara/Felipe/qualquer Claude tocando Pages/ **não consegue mais esquecer** de aplicar a Constituição. Não substitui leitura completa — é gancho de atenção.

**Esforço:** 30min · arquivo único `.claude/skills/constituicao-ui-aware/SKILL.md`

**Onde:** branch `feat/skill-constituicao-ui-aware` · PR isolado

### Item 1.2 · Artisan command `php artisan ui:lint` (esqueleto + 3 regras críticas)

**O que faz:** scan de `resources/js/Pages/` + `resources/js/Components/shared/` aplicando regras:

```php
// app/Console/Commands/UiLint.php (NOVO)
- Regra R1 (cor crua):
  rg "(#[0-9a-fA-F]{3,8}|bg-(blue|red|green|amber|violet|orange)-\d{3})" Pages/
  → falha se >0 hits (exceto #fff/#000 em comments)
- Regra R2 (FontAwesome):
  rg "from ['\"]@fortawesome" Pages/
  → falha se >0 hits (lucide-only · UI-0003)
- Regra R3 (emoji em UI):
  rg "[😀-🙏🌀-🗿✨🇧🇷]" Pages/ Components/shared/
  → falha se >0 hits (lucide icon, não emoji)
```

**Output:** lista de violações com path + linha + regra · exit code 1 se hit > 0

**Esforço:** 1h · arquivo único PHP + integração `composer test`

**Onde:** branch `feat/artisan-ui-lint` · PR isolado

---

## 🌊 Onda 2 — CI + pre-commit + PT-XX (~2-3h) · sobe 50→75%

### Item 2.1 · GitHub Actions workflow `ui-lint.yml`

**O que faz:** roda `php artisan ui:lint --strict` em PRs que tocam Pages/ ou Components/shared/. Falha CI se hit > 0. Comenta inline no PR com link pra cada violação.

**Esforço:** 30min · `.github/workflows/ui-lint.yml` + reuso composer setup existente

### Item 2.2 · Hook `.git/hooks/pre-commit`

**O que faz:** roda `ui:lint` só em arquivos staged via `git diff --staged --name-only`. Pega regressão antes de chegar CI. Alerta amarelo (warning) por padrão · vermelho (block) se Wagner ativar via env.

**Esforço:** 1h · script bash + `.husky/` ou direct `.git/hooks/` + doc em CLAUDE.md

### Item 2.3 · Regras PT-01 lint

**O que faz:** `php artisan ui:lint --pt=01` checa todo `Pages/<X>/Index.tsx`:
- ✓ Importa `PageHeader` ou `<PageHeader>` em JSX (Slot 1)
- ✓ Importa `DataTable` ou `<DataTable>` em JSX (Slot 5) — ou `<table>` se justificado
- ⚠ Warning se não tem ModuleTopNav + BulkActionBar (Slots 2/4 opcionais)
- ✗ Erro se tem `<h1>` hardcoded sem `<PageHeader>` (violação Slot 1)

**Output:** "PT-01 violação: Pages/X/Index.tsx · Slot 1 (PageHeader) missing"

**Esforço:** 1h · extensão do `ui:lint` command

### Item 2.4 · Regra "não introduzir 6ª origin badge"

**O que faz:** `php artisan ui:lint --origins` checa `resources/css/cockpit.css` + `inertia.css`:

```bash
# Token canon = exatamente 5 origins
EXPECTED="OS|CRM|FIN|PNT|MFG"
ACTUAL=$(rg -o "\-\-origin-(\w+)-bg" cockpit.css | sort -u)
# Hit nova origin = falha
```

**Esforço:** 30min · regra trivial

**Onde Ondas 2.x:** branch única `feat/ui-lint-ci-and-rules` · PR consolidado

---

## 🌊 Onda 3 — Descoberta MCP + visual regression (~4-6h) · sobe 75→85%

### Item 3.1 · Webhook GitHub → MCP-notif

**O que faz:** quando PR mergeia tocando `memory/requisitos/_DesignSystem/`, `Pages/` ou `Components/shared/`, dispara notificação para o time MCP (Felipe/Maiara/Eliana/Luiz) via tool MCP `team-notify` ou Slack canal #design-system.

**Mensagem:**
```
🎨 UI canônica atualizada · PR #NNNN
Tocou: <arquivos>
ADR aplicável: <UI-XXXX>
Próxima brief-fetch reflete · ou acesse: <link PR>
```

**Esforço:** 4h · `.github/workflows/notify-mcp.yml` + endpoint MCP-notif (precisa existir ou criar) + template mensagem

**Dependência externa:** endpoint MCP `team-notify` precisa existir no mcp-server. Hoje provavelmente **não existe** — precisa scope MCP-server primeiro.

### Item 3.2 · Visual regression (Playwright trace OU Percy/Chromatic free tier)

**O que faz:** snapshot pixel-diff de telas críticas em PR. Rejeita drift visual silencioso. Implementação:
- Playwright trace built-in (gratuito, runs em CI) — screenshots de 5-10 telas-âncora pré e pós-merge
- OU Percy/Chromatic free tier (até 5k snapshots/mês free) — mais maduro, integração GitHub nativa

**Telas-âncora:** Sells/Index, Cliente/Index, Financeiro, Fiscal (4 telas-lista representativas) + Cliente drawer aberto (PT-02 quando existir)

**Esforço:** 6-8h · CI infrastructure + baseline screenshots + auto-update workflow

**Dependência:** Playwright já instalado? Verificar `package.json`. Se não, scope = adicionar dep + setup primeiro.

**Onde Ondas 3.x:** branches separadas — `feat/webhook-mcp-notif` e `feat/visual-regression-playwright`

---

## 🌊 Onda 4 — LLM-as-judge (~1-2 dias) · sobe 85→90%

### Item 4.1 · Agente CI `pr-ui-judge`

**O que faz:** GitHub Action dispara em PR que toca UI. Carrega no contexto LLM (Brain B Sonnet):
- Constituição UI v2 completa (UI-0013 + UI-0014 + PT-01-Lista.md ~30k tokens)
- Diff do PR
- 9 dimensões de scoring (slot adherence, hierarquia respeitada, anti-padrões grep-invisíveis, copy PT-BR, acessibilidade básica)

**Output:** comentário inline no PR:
```
[PR-UI-JUDGE · score 87/100]

✅ PT-01 6 slots respeitados
✅ Token canon aplicado (sem cor crua)
⚠ Slot 4 (BulkBar) ausente — opcional, mas Sells aplica
✗ Drawer aberto sem `<Sheet>` shadcn — usa <div> custom (PRE-MERGE-UI AP2)

Sugestões: ...
```

**Esforço:** 1-2 dias · agent implementation (Laravel command + LLM client existente `App\Services\Copiloto\ClaudeClient`) + workflow CI + tuning prompt

**Custo:** Brain B Sonnet ~$0.03/PR (média 10k tokens input + 1k output) · ~$3/mês a 100 PRs/mês

### Item 4.2 · Skill `pr-ui-judge-manual` (Wagner-invoke)

**O que faz:** Wagner em qualquer sessão pode rodar `/pr-ui-judge <PR#>` pra forçar judge LLM sem esperar CI.

**Esforço:** 2h · skill description + integração API GitHub + reuso pr-ui-judge

**Onde:** branch `feat/llm-as-judge-pr-ui` · PR isolado · provavelmente sub-PRs (CI + skill)

---

## O que NÃO automatiza (10-20% sempre humano)

| Categoria | Por quê fica humano |
|---|---|
| "Tipo Stripe / deixa bonito" | Palpite estético — regra-mestre força pergunta mas não substitui Wagner |
| Desempate entre 2 ADRs em conflito | Caso sidebar dark vs light · Wagner explícito |
| Quando criar PT-XX novo (PT-02, PT-03...) | Depende ≥2 módulos pedirem · sinal humano |
| Aposentar padrão / ADR | Decisão estratégica · Wagner aprova |
| Voice & tone (copy criativa) | Carioca-startup direto · gosto humano |
| Larissa-fit (cliente real) | Smoke test biz=4 ROTA LIVRE · Wagner valida |

---

## Critério de start por onda

Quando começar cada onda? Sinal claro, não cronograma:

| Onda | Critério "vale start" |
|---|---|
| **1** | **Sempre.** Custo baixo · valor alto · faz sozinha. Roda em qualquer sessão de 1h30 |
| **2** | Quando primeira regressão real aparecer (Maiara/Felipe/qualquer Claude introduzir cor crua em PR) · OU 1 sprint depois de Onda 1 estabilizar |
| **3** | Quando time MCP tiver ≥3 ativos (hoje Wagner trabalha sozinho com Claude — notif pra "ninguém" não vale) · OU visual drift detectado manualmente em 2+ módulos |
| **4** | Quando custo Brain B já estiver mapeado em `copiloto.admin.custos` (existe?) · OU Onda 2 não pegar 5+ tipos de regressão semântica em 30 dias |

---

## Ordem recomendada de execução

```
hoje ──→ Onda 1 (skill + ui:lint esqueleto)        1h30
         ↓
sprint+1 → Onda 2 (CI + hook + PT-01 + origins)    2-3h
         ↓
quando ≥2 sinais ──→ Onda 3.1 (webhook MCP)        4h
         ↓
quando módulos UI maduros ──→ Onda 3.2 (visual)    6-8h
         ↓
último (alto investimento) ──→ Onda 4 (LLM judge)  1-2d
```

## Custos cumulativos

| Pós-onda | Esforço total | % Automatizado | Custo running mensal |
|---|---|---|---|
| Onda 1 | 1h30 | 50% | $0 (skill + lint local) |
| + Onda 2 | 4h | 75% | $0 (CI já existe) |
| + Onda 3 | 10h | 85% | $0 (Playwright trace) ou ~$0 (Percy free tier) |
| + Onda 4 | 1-2d | 90% | ~$3/mês (LLM judge 100 PRs) |

---

## Quando esta ADR vira obsoleta

Quando 90% automatizado virar realidade — este doc move pra `historical/` com nota *"superseded by automation real"*. ADRs operacionais individuais (skill, ui:lint command, CI workflow) ficam vivas.

---

## Refs

- **ADR-mãe Constituição UI v2:** [`adr/ui/0013-constituicao-ui-v2-camadas.md`](adr/ui/0013-constituicao-ui-v2-camadas.md)
- **Ponteiro MCP:** [`memory/decisions/0187-constituicao-ui-v2-ponteiro-canon.md`](../../decisions/0187-constituicao-ui-v2-ponteiro-canon.md)
- **PR onde foi proposto:** [#1438](https://github.com/wagnerra23/oimpresso.com/pull/1438)
- **Sessão de origem:** worktree `frosty-greider-83ab2f` · 2026-05-24
- **PRE-MERGE-UI checklist atual:** [`PRE-MERGE-UI.md`](PRE-MERGE-UI.md)
- **PT-01 (lint target Onda 2):** [`padroes-tela/PT-01-Lista.md`](padroes-tela/PT-01-Lista.md)
- **Skills correlatas existentes:** `mwart-process` v1.2 · `preflight-modulo` · `commit-discipline` · `multi-tenant-patterns`

---

**Última revisão:** 2026-05-24 · proposta inicial · zero ondas executadas.
**Próxima revisão:** quando Wagner executar Onda 1 (atualizar com timestamps reais + resultados).
