---
name: tela-smoke-pos-merge
description: ATIVAR após PR mergeado que toca resources/js/Pages/**/*.tsx OU quando Wagner pedir "smoke a tela X", "validar tela X visualmente", "ver como ficou tela Y", "/tela-smoke <rota>", "valida visual <Modulo>/<Tela>", "rodar smoke pos-merge". Roda browser MCP automático contra prod, screenshot 1440 + 1280, console errors, perf metrics, cria <Tela>.review.md round N + notifica Wagner via mcp_alertas. Inclui cron daily 09:00 BRT pra telas live ≥7d sem refresh. Tier B auto-trigger por description.
trust_level: L1
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: ""
tier: B
parent_adr: 0164
related_adrs: [0164, 0104, 0107, 0114, 0094, 0093, 0062]
---

# tela-smoke-pos-merge — fase C (Check) do MWART PDCA

> **Origem:** [ADR 0164](../../../memory/decisions/0164-screen-review-pdca-tela-smoke-pos-merge.md) — emenda ao [ADR 0104](../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) MWART introduzindo PDCA explícito (Plan/Do/**Check**/Act).

## Quando ativar

Esta skill ativa automaticamente em 3 cenários:

1. **Pós-merge PR automático** — workflow `.github/workflows/screen-smoke-after-merge.yml` (dispara via `workflow_run` após o `deploy.yml` concluir) detecta que o deploy tocou telas `resources/js/Pages/**/*.tsx` **OU navegação** (`resources/js/Components/cockpit/Sidebar.tsx` · `app/Http/Middleware/AdminSidebarMenu.php` · `app/Services/LegacyMenuAdapter.php` — gap do PR #3945: mudança de item de menu não é uma tela) → invoca Claude Code remote no CT 100 → ativa esta skill
2. **Pedido explícito Wagner** — frases-gatilho catalogadas no `description` (matcher Tier B):
   - "smoke a tela X" / "validar tela X visualmente" / "ver como ficou tela Y"
   - `/tela-smoke <rota>` (slash command convention)
   - "valida visual <Modulo>/<Tela>"
   - "rodar smoke pos-merge"
3. **Cron daily 09:00 BRT** — re-smoke telas com `status: live` há ≥7d sem refresh (catch regressão silenciosa via deploy não-coberto)

## Pré-flight (OBRIGATÓRIO ler ANTES de executar)

Antes de qualquer chamada `mcp__claude-in-chrome__*`:

1. **Ler charter** `resources/js/Pages/<Mod>/<Tela>.charter.md` se existir — captura UX targets (latência alvo, breakpoints suportados, estados visíveis)
2. **Ler review anterior** `resources/js/Pages/<Mod>/<Tela>.review.md` se existir — captura baseline último round (status atual, comentários Wagner pendentes, métricas de referência)
3. **Ler UI-CATALOG** `resources/js/Pages/<Mod>/UI-CATALOG.md` se existir — entender contexto módulo
4. **Verificar Vaultwarden** item `screen-smoke/wagner-prod-readonly` existe (credentials prod read-only). Se não existir, abortar com alerta `mcp_alertas` "Vaultwarden credentials missing — Wagner provisionar"

## Execução (7 passos — sequencial)

### Passo 1 — Abrir browser e autenticar

```
mcp__claude-in-chrome__open_url url=https://oimpresso.com/login
# Login via Vaultwarden item screen-smoke/wagner-prod-readonly
# Conta dedicada read-only biz=99 (fake) quando possível; biz=4 ROTA LIVRE só se charter exige dados reais
```

### Passo 2 — Navegar até a tela

```
mcp__claude-in-chrome__open_url url=https://oimpresso.com/<rota>
# Esperar load complete (networkidle)
```

### Passo 3 — Capturar screenshot 1440px (desktop padrão)

```
mcp__claude-in-chrome__resize_viewport width=1440 height=900
mcp__claude-in-chrome__screenshot path=storage/screen-smoke/<modulo>/<tela>/<ts>-1440.png
```

### Passo 4 — Capturar screenshot 1280px (ROTA LIVRE Larissa monitor)

```
mcp__claude-in-chrome__resize_viewport width=1280 height=720
mcp__claude-in-chrome__screenshot path=storage/screen-smoke/<modulo>/<tela>/<ts>-1280.png
```

### Passo 5 — Coletar console errors + perf metrics

```
mcp__claude-in-chrome__get_console_logs  # filtrar level: error, warning
mcp__claude-in-chrome__get_performance_metrics  # FCP, LCP, TTI, TBT
```

### Passo 6 — Auto-mask PII nos screenshots (Tier 0)

Pipeline regex post-capture ANTES de attach:
- CPF `\d{3}\.\d{3}\.\d{3}-\d{2}` → `[CPF-REDACTED]`
- CNPJ `\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}` → `[CNPJ-REDACTED]`
- Email `[\w.+-]+@[\w-]+\.[\w.-]+` → `[EMAIL-REDACTED]`
- Telefone `\(?\d{2}\)?\s?\d{4,5}-?\d{4}` → `[FONE-REDACTED]`

Usar `PiiRedactor` ([ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) reaproveitado.

### Passo 7 — Append review.md round N + smoke-log.md + regenerar UI-CATALOG

#### 7a. Append `<Tela>.smoke-log.md`

```markdown
## <ISO timestamp BRT> — run #<N>

- **trigger:** pós-merge PR #<num> | pedido-wagner | cron-daily
- **viewport 1440:** [screenshot link]
- **viewport 1280:** [screenshot link]
- **console errors:** <count> (detalhes inline se ≤5; link se >5)
- **perf:** FCP <ms> · LCP <ms> · TTI <ms> · TBT <ms>
- **vs charter targets:** <ok|degraded> (detalhes)
- **status run:** ok | failed-load | failed-auth | failed-screenshot
```

#### 7b. Append `<Tela>.review.md` round N (status pending-wagner)

```markdown
## Round <N> — <ISO timestamp BRT>

- **status:** pending-wagner
- **trigger:** <pós-merge PR #X | pedido-wagner | cron-daily>
- **smoke-log entry:** [link]
- **resumo diff vs round anterior:** <auto-summary>
- **comentário Wagner:** _aguardando_
- **decisão Wagner:** _aguardando — editar este round adicionando `decisão: approved | rejected | iterate`_
```

#### 7c. Regenerar `<Modulo>/UI-CATALOG.md`

Índice de TODAS telas do módulo + status agregado atual.

### Passo 8 — Notificar Wagner via mcp_alertas

```php
mcp_alertas->push([
    'tipo' => 'screen-review-pending',
    'destinatario' => 'wagner',
    'severidade' => 'info',
    'titulo' => "Tela <modulo>/<tela> round <N> aguarda decisão",
    'payload' => [
        'review_path' => 'resources/js/Pages/<Mod>/<Tela>.review.md',
        'screenshot_1440' => '<link>',
        'screenshot_1280' => '<link>',
        'console_errors_count' => <N>,
        'diff_vs_baseline' => '<summary>',
    ],
]);
```

## Anti-patterns proibidos

- ⛔ **Testar com biz=4 ROTA LIVRE sem justificativa explícita** no charter — usar biz=99 fake por padrão ([ADR 0101](../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md))
- ⛔ **Armazenar PII** sem passar por auto-mask Passo 6 — viola Tier 0 ([ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- ⛔ **Modificar DB prod** (INSERT/UPDATE/DELETE) — skill é READ-ONLY hard enforce; qualquer verbo write é abortado e logado
- ⛔ **Commitar review.md / smoke-log.md sem status `approved`** — só commita após Wagner editar `decisão: approved` (rejected/iterate spawnam novo round, não commitam status)
- ⛔ **Hardcoded credentials** em SKILL.md / .env público / código — Vaultwarden API único caminho
- ⛔ **Browser MCP no Hostinger** — execução exclusiva CT 100 Proxmox via `mcp.oimpresso.com` ([ADR 0062](../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md))
- ⛔ **Pular pré-flight (charter + review anterior)** — sintoma de degradação (skill perde contexto, gera review N sem baseline)
- ⛔ **Round >5 sem `approved`** — escalar pra ADR feature-wish governance ([ADR 0105](../../../memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) bloqueando novos rounds (charter mal calibrado é sinal qualificado)

## Output esperado

3 artefatos atualizados/criados (append-only `<Tela>.review.md` + `<Tela>.smoke-log.md` ; regenerável `<Modulo>/UI-CATALOG.md`) + 1 PR comment automático + 1 entry `mcp_alertas`.

## Mecanismo Wagner aprova/rejeita/itera (fase A — Act)

1. Wagner vê alerta `mcp_alertas` (UI ou CLI `my-inbox`)
2. Abre `<Tela>.review.md`, examina screenshots + console errors
3. Edita round N adicionando uma das 3 decisões:
   - `decisão: approved` + comentário opcional → skill detecta e marca `<Tela>.charter.md` `status: live`
   - `decisão: rejected` + comentário obrigatório → skill cria Initiative `mcp_initiatives` automática + spawn agent paralelo round N+1
   - `decisão: iterate` + TODOs comentário → skill cria task `mcp_tasks` pro agente designado

## Custo + Latência

- **1 tela / 1 round:** ~30-60s; ~$0.02 (browser MCP gratuito + LLM tokens análise)
- **50 telas × 2 rounds/mês:** ~$2/mês
- **+ cron daily refresh telas live ≥7d:** ~$1/mês incremental
- **Total estimado:** < R$ [redacted Tier 0]/mês ([ADR 0164](../../../memory/decisions/0164-screen-review-pdca-tela-smoke-pos-merge.md) §6)

## Telemetria

Service `tela-smoke-pos-merge` reporta `mcp_observability_spans` ([ADR 0162](../../../memory/decisions/0162-otel-collector-prod-observability.md)): latência, sucesso/falha por run, contagem PII auto-mask, console errors agregados, distribuição rounds-até-approved.

## Cross-refs

- [ADR 0164](../../../memory/decisions/0164-screen-review-pdca-tela-smoke-pos-merge.md) — ADR mãe desta skill (PDCA fase C)
- [ADR 0104](../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) — MWART original (emendado pela ADR 0164)
- [ADR 0114](../../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — loop Cowork ↔ Claude Code (fase P — Plan)
- [ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 + PiiRedactor
- [memory/requisitos/_DesignSystem/SCREEN-REVIEW-PDCA.md](../../../memory/requisitos/_DesignSystem/SCREEN-REVIEW-PDCA.md) — pattern doc canon (templates + exemplo round)
