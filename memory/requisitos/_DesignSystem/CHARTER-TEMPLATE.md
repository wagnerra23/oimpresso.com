# CHARTER-TEMPLATE.md — Template canônico de Page Charter (Inertia .tsx)

> **Append-only** — novos blocos podem ser adicionados; blocos existentes NUNCA removidos sem ADR mãe ([ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md) + [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §princípio #3 Charter > Spec).
>
> **Onde vive:** `resources/js/Pages/<Mod>/<Tela>.charter.md` (irmão do `.tsx`).
> **Skill criadora:** `charter-write` (Tier C) gera draft inicial via leitura do `.tsx` + Controller.
> **Skill leitora:** `charter-first` (Tier A always-on) — Claude lê via `mcp__Oimpresso_MCP___Wagner__charter-fetch` ANTES de Edit/Write em `.tsx` com charter irmão.
> **Hook validador:** `charter-validate.{ps1,sh}` PreToolUse (warning-mode → `CHARTER_VALIDATE_STRICT=1` bloqueante quando ROI provado).
>
> **Workflow ativação:** `draft` → Wagner revisa Non-Goals + Anti-hooks → `live`. Detalhes em [RUNBOOK-charters-s4-ativacao.md](RUNBOOK-charters-s4-ativacao.md).

---

## Frontmatter canônico (YAML)

```yaml
---
page: <Mod>/<Tela>                                # ex: Admin/GovernanceV4
controller: Modules\<Mod>\Http\Controllers\<X>Controller@<metodo>
route: <route.name.canon>                         # ex: admin.governance.v4
status: draft                                     # draft | live | deprecated
owner: [W] Wagner                                 # responsável final
persona_principal: <Quem usa primariamente + viewport>
persona_secundaria: <Quem usa secundariamente>
charter_version: 1.0
charter_at: YYYY-MM-DD
last_validated: YYYY-MM-DD                        # data última validação (live only)
related_adrs:
  - NNNN-slug-da-adr
related_briefing: ../../../memory/requisitos/<Mod>/BRIEFING.md
related_predecessor_visual: <path .tsx blueprint>  # se reuso pattern MWART

# === BLOCO NOVO (W30 — ADR 0164 Screen Review PDCA) ===
smoke_pos_merge: required                         # required | optional | skip — skill `tela-smoke-pos-merge` (Tier B) dispara
ux_targets:
  first_paint_ms: 800                             # meta first-paint (browser MCP)
  fcp_ms: 1200                                    # First Contentful Paint
  no_console_errors: true                         # zero erros JS console
  responsive_1440_no_scroll_horizontal: true      # Wagner monitor primário
  responsive_1280_no_scroll_horizontal: true      # Larissa monitor (ROTA LIVRE)
review_history: <Tela>.review.md                  # append-only rounds — auto-gerado pela skill
smoke_log: <Tela>.smoke-log.md                    # append-only browser MCP runs
# === FIM BLOCO NOVO ===

mwart_pattern_reuse:                              # opcional — preencher se Pattern Reuse MWART
  blueprint_canonical: <path .tsx blueprint validado>
  blueprint_screenshot_approval: SKIP | <data>
  derived_screens: [<lista telas derivadas>]
  divergence_from_blueprint: "<texto curto descrevendo desvios>"
---
```

---

## Seções obrigatórias do markdown

### 1. Header + tagline (1 linha)
Tela X faz Y pra persona Z em contexto W.

### 2. Mission (2-4 linhas)
Resposta crua: pra que serve essa tela? Qual outcome ela entrega?

### 3. Goals (faz) — lista numerada
Cada item é uma capacidade que a tela DEVE entregar. Pareia com `it()` Pest GUARD.

### 4. Non-Goals (NÃO faz) — lista
**Parte sensível anti-alucinação — Wagner aprova manualmente antes flip `draft` → `live`.**
Cada item vira Pest GUARD (`it('does NOT do X')`).

### 5. UX Targets — lista
Métricas vivas mensuráveis (first-paint, sparkline render, ⌘K abre, responsividade).
**A partir do W30 espelhar `ux_targets` do frontmatter** — fonte única de truth.

### 6. UX Anti-patterns — lista
Padrões visuais proibidos (modal pra detalhe, tabs `border-b-2`, `sessionStorage`, cores cruas).

### 7. Automation Hooks — lista
Rotas + middlewares + jobs/eventos disparados pela tela.

### 8. Automation Anti-hooks — lista
**Parte sensível anti-alucinação — Wagner aprova manualmente.**
"NÃO envia emails/SMS no render", "NÃO dispara LLM no render", "NÃO escreve DB no GET".
Cada item vira Pest GUARD.

### 9. Sub-components — lista
Componentes em `_components/<Tela>/*.tsx` (se aplicável).

### 10. Métricas vivas (Pest GUARD)
Bloco `php` com `it()` derivados dos Goals/Non-Goals/Anti-hooks.

### 11. Comparáveis canônicos (`mwart-comparative` V4)
Linear / Cortex / Datadog / Notion (incluir/excluir + razão curta).

### 12. Refs
Links pra ADRs, BRIEFING, RUNBOOKs, predecessor visual.

### 13. Histórico
Tabela append-only (data | autor | mudança).

---

## Screen Review PDCA (BLOCO NOVO W30 — ADR 0164)

> **Por quê:** charter declara o contrato. PDCA mensura aderência via rounds iterativos (Plan-Do-Check-Act).
>
> **Quando:** após cada merge que toque a tela. Skill `tela-smoke-pos-merge` (Tier B) auto-dispara browser MCP + popula `<Tela>.smoke-log.md` + cria/atualiza `<Tela>.review.md` round novo.

### Como funciona

1. **Round 1 — criação** (após primeiro merge):
   - Skill auto-cria `<Tela>.review.md` com frontmatter `current_round: 1, status: pending-wagner`
   - Lista entregue + smoke metrics (se browser MCP disponível) OU "smoke pendente" (se Wagner ainda não aprovou bypass/Tailscale)
   - Wagner decide: `approved` / `rejected` / `needs-iteration` no rodapé do round

2. **Round N — iteração** (após próximo merge que toque a tela):
   - Skill **append** round novo ao `.review.md` (NUNCA edita rounds anteriores)
   - Frontmatter `current_round: N+1, status: pending-wagner`
   - Compara UX targets atuais vs charter — destaca desvios

3. **Aprovação Wagner**:
   - Edita rodapé do round atual: "**Decisão Wagner:** APROVADO" ou "REJEITADO: <razão>"
   - Flip `status: approved` no frontmatter quando round atende todos `ux_targets`
   - Charter `status: live` permanece — review é histórico iterativo

### Pattern review.md (append-only)

```markdown
---
tela: <Mod>/<Tela>
controller: <FQCN>@<metodo>
charter: ./<Tela>.charter.md
current_round: N
status: pending-wagner | approved | rejected | needs-iteration
created_at: YYYY-MM-DD
approved_at: YYYY-MM-DD                  # preenchido quando approved
ux_targets:
  first_paint_ms: 800
  no_console_errors: true
---

## Round N — YYYY-MM-DD

**Status:** pending-wagner

**Entregue:**
- <lista mudanças desde round anterior>

**Smoke browser MCP:**
- first_paint: 720ms ✓ (meta 800)
- console errors: 0 ✓
- 1440 + 1280 sem scroll horizontal ✓

**Desvios charter:**
- <lista vazia se aderente>

**Decisão Wagner:** [pendente]

---

## Round N-1 — YYYY-MM-DD (anterior — NÃO EDITAR)
[histórico append-only]
```

### Catálogo UI por módulo (auto-gerado)

Command `php artisan admin:ui-catalog-generate <modulo>` varre `resources/js/Pages/<Mod>/**/*.tsx` + lê irmãos `.charter.md` + `.review.md` e gera `memory/requisitos/<Mod>/UI-CATALOG.md` (tabela + pendências + cross-ref).

Schedule daily 09:30 BRT (depois cron smoke 09:00). Auditável via `cycles-active` + Wagner monitor.

---

## Refs

- [ADR 0094 Constituição V2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — princípio #3 Charter > Spec
- [ADR 0101 Sistema Charter-Capterra](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md) — template canônico origem
- [ADR 0104 Processo MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0114 Prototipo-UI Cowork loop](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [ADR 0164 Screen Review PDCA] (pendente W30-A) — bloco novo `smoke_pos_merge` + review.md append-only
- [RUNBOOK-charters-s4-ativacao.md](RUNBOOK-charters-s4-ativacao.md) — workflow draft→live
- Skill `charter-first` (Tier A) · `charter-write` (Tier C) · `tela-smoke-pos-merge` (Tier B — W30)
- Tool MCP `charter-fetch` ([Modules/Jana/Mcp/Tools/CharterFetchTool.php](../../../Modules/Jana/Mcp/Tools/CharterFetchTool.php))
- Command `admin:ui-catalog-generate` ([Modules/Admin/Console/Commands/ScreenCatalogGenerateCommand.php](../../../Modules/Admin/Console/Commands/ScreenCatalogGenerateCommand.php))

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-17 | W30 Agent D | Template canônico criado consolidando 26 charters existentes. Bloco novo `smoke_pos_merge` + `ux_targets` + `review_history` + `smoke_log` no frontmatter. Seção "Screen Review PDCA" adicionada documentando rounds append-only + skill `tela-smoke-pos-merge` + command `admin:ui-catalog-generate`. |
