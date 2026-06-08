---
date: 2026-05-28
agent: como-integrar
target: framework DriftChecker (ADR 0216 proposta — generalização ADR 0215)
mode: introspectivo (só lê código/memory, ZERO web)
---

# Como integrar DriftChecker framework no oimpresso

> **Conclusão antes do detalhe:** estado **PARCIAL**, ~50% já implementado em peças isoladas. Wagner NÃO precisa criar framework do zero — precisa **refatorar 2 commands existentes pra implementar interface comum + adicionar Registry + Provider + Command master**. 5 commands daily 06:15 já colidem no schedule (gap operacional concreto). Maior risco: tocar `Modules/Governance/Console/Commands/DetectDriftCommand.php` que JÁ persiste em `mcp_alertas_eventos` com lógica idempotente sofisticada — não duplicar essa lógica em base class do framework, **extrair** pra trait/service.

---

## 1. Inventário (10 seções)

### 1.1 Checkers já existentes (catalogados)

| Comando | Path | Persistência drift | Schedule | Exit codes | Notif Centrifugo |
|---|---|---|---|---|---|
| `governance:detect-drift` | `Modules/Governance/Console/Commands/DetectDriftCommand.php` | `mcp_alertas_eventos` (tipo=`module_drift`, idempotente por dia, `business_id=NULL` repo-wide) | daily 06:15 BRT `onOneServer` `withoutOverlapping` | 0=clean / 1=drift | NÃO |
| `governance:health` | `Modules/Governance/Console/Commands/GovernanceHealthCommand.php` | Log estruturado `Log::channel('single')` | NÃO scheduled atualmente | 0/1 | NÃO |
| `secrets:scan` (ADR 0215 C1) | `app/Console/Commands/SecretsScanCommand.php` | Stdout only (sem DB persist) | weekly Mon 09:00 BRT | 0/1 com `--fail-on-drift` | NÃO |
| `secrets:audit` (ADR 0215 C2-4) | `app/Console/Commands/SecretsAuditCommand.php` | Reescreve `memory/_INDEX-SECRETS.md` in-place + commit auto via `gh CLI` | daily 06:15 BRT | 1 se drift | SIM canal `governance:secrets` |
| `jana:system-audit` (ADR 0133) | `Modules/Jana/Console/Commands/SystemAuditCommand.php` | Log estruturado | daily 06:15 BRT | 0/1 | NÃO |
| `jana:health-check` | `Modules/Jana/Console/Commands/HealthCheckCommand.php` | `mcp_alertas_eventos` | daily 06:00 BRT | 0/1 | NÃO |
| `jana:freshness-check` | `Modules/Jana/Console/Commands/FreshnessCheckCommand.php` | `mcp_alertas_eventos` (idempotente por dia) | daily 04:30 BRT | 1 se CRITICAL | NÃO |
| `jana:drift-sentinel` | `Modules/Jana/Console/Commands/JanaDriftSentinelCommand.php` | (não inspecionado — assumir similar) | (verificar) | — | — |
| `fsm:scan-drift` | `app/Console/Commands/FsmScanDriftCommand.php` (assinatura `fsm:scan-drift transactions`) | — | daily 03:00 BRT | 0/1 | NÃO |
| `kb:drift-detector` | `Modules/KB/Console/Commands/KbDriftDetectorCommand.php` | — | — | — | — |
| `whatsapp:auth-state-drift-check` | `Modules/Whatsapp/Console/Commands/WhatsappAuthStateDriftCheckCommand.php` | — | daily 03:00 BRT | — | — |
| `whatsapp:daemon-source-drift-check` | `Modules/Whatsapp/Console/Commands/DaemonSourceDriftCheckCommand.php` | — | weekly Mon 09:00 BRT | — | — |
| `whatsapp:scan-media-drift` | `Modules/Whatsapp/Console/Commands/ScanMediaDriftCommand.php` | — | daily 03:30 BRT | — | — |
| `ui:lint` | `app/Console/Commands/UiLintCommand.php` (NÃO em `app/Console/Commands/UI/`) | Baseline JSON `config/ui-lint-baseline.json` | (não scheduled — via pre-commit) | 0/1 com `--strict` | — |
| `charter:health` | `Modules/Governance/Console/Commands/CharterHealthCommand.php` | — | daily 06:30 BRT | 0/1 | — |
| `governance:scorecard-snapshot` | `Modules/Governance/Console/Commands/ScorecardSnapshotCommand.php` | `mcp_scorecard_runs` + `mcp_alertas` (drift ≥5pts) | daily 07:00 BRT `onOneServer` | — | — |
| `bin/check-scope.php` | `bin/check-scope.php` (CLI puro, NÃO Artisan) | Stdout | (via pre-commit hook) | 0=ok / 1=drift / 2=erro | — |

**Total: 14 commands "checker" detectados.** Padrão dispersos: alguns em `app/Console/Commands/`, maioria em `Modules/*/Console/Commands/`. Naming heterogêneo: `*HealthCommand`, `*Drift*Command`, `*AuditCommand`, `*ScanCommand`, `*Detector*Command`. Persistência heterogênea: `mcp_alertas_eventos` (3), `mcp_alertas` (1), reescreve MD canônico (1), só log (resto).

**Pattern dominante:** `mcp_alertas_eventos` com idempotência via `chave_idempotencia` UNIQUE (por dia) + `business_id=NULL` repo-wide quando aplicável. Schema da tabela em `Modules/Jana/Database/Migrations/2026_04_29_600001_create_mcp_alertas_eventos_table.php`.

### 1.2 ServiceProviders existentes

- `app/Providers/AppServiceProvider.php` (302 linhas) — boot registra Passport commands em `registerCommands()`. Padrão: `$this->commands([...])`.
- **`Modules/Governance/Providers/GovernanceServiceProvider.php` JÁ EXISTE** — registra 10 commands `governance:*`, singleton `ActionGate`, middleware alias, config merge `governance.actiongate_mode` + `d1_hardened`, publishes config.
- **DECISÃO ÓBVIA**: o `DriftCheckerRegistry` cabe em `GovernanceServiceProvider::register()` como singleton, NÃO em `AppServiceProvider`. Manter agrupamento por domínio (Constituição Art. 8 — Governance é meta-módulo).
- NÃO existe `bootstrap/providers.php` (projeto Laravel <11 ou estilo legacy `config/app.php`).
- Module discovery via `module.json` em `Modules/<X>/module.json` (nwidart/laravel-modules).

### 1.3 Schedule daily/weekly (`app/Console/Kernel.php`)

957 linhas, **62 schedule entries** ativas. Resumo cronológico das janelas críticas:

| Hora BRT | Comandos | Risco colisão |
|---|---|---|
| 02:00 | `customer-memory:refresh-daily`, `ads:learn-patterns`, `observability:aggregate-daily` | OK (escopos diferentes) |
| 03:00 | `whatsapp:auth-state-drift-check`, `fsm:scan-drift`, `jana:retention-purge` | OK (escopos diferentes) |
| 03:30 | `whatsapp:health-probe-channels`, `whatsapp:scan-media-drift` | OK |
| 04:30 | `jana:freshness-check --alert --reindex --limit=50` | OK |
| 06:00 | `jana:health-check --notify` | OK |
| 06:05 | `module:grade-snapshot` | OK |
| **06:15** | **`governance:detect-drift`** + **`jana:system-audit --notify`** + **`secrets:audit --auto-pr --notify`** + **`nfebrasil:dist-dfe-puxar`** | **🔴 4 comandos disputam DB + cron simultaneamente** |
| 06:20 | `mcp:tasks:health-check` | borderline |
| 06:30 | `charter:health --notify` + `arquivos:health-check --alert` + `sells:smoke-daily` | OK (2 = aceitável) |
| 06:35 | `governance:health` (NÃO scheduled atualmente — só health interno se chamarem) | seria slot ideal |
| 07:00 | `governance:scorecard-snapshot` | OK |
| 08:00 | `governance:initiative-sync` | OK |

**Achado crítico:** as 4 invocações 06:15 BRT já são problemáticas (Kernel.php linhas 213, 305, 699, 820). Plugar `governance:audit --all` substituindo 06:15 reduz risco; mas `nfebrasil:dist-dfe-puxar` (SEFAZ) NÃO é DriftChecker — não substituir.

### 1.4 Centrifugo channels já em uso

- **Wrapper canônico:** `Modules/Whatsapp/Services/Centrifugo/CentrifugoPublisher.php` — `publish(string $channel, array $data): bool` com OTel span `whatsapp.centrifugo.publish` + falha silenciosa (Log warning).
- **Config:** `config('whatsapp.centrifugo.url')` + `config('whatsapp.centrifugo.api_key')` + `request_timeout=5`.
- **Resolução:** `app(\Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher::class)` — usado em `SecretsAuditCommand::publishCentrifugoAlert` linha 225.
- **Channels já em uso identificados em código:**
  - `governance:secrets` (ADR 0215 C4 — `SecretsAuditCommand`)
  - `user:{atendente_id}` (US-WA-076 reminders)
  - Outros omnichannel/notifications (não inspecionei exaustivo)
- **Payload padrão:** `{type: string, ...domain_keys, count?: int, detected_at: ISO8601}`
- **Channel proposto pra DriftChecker:** `governance:drift` (singular generalizado de `governance:secrets`). Brief Jana consumiria ambos (ou unificar futuramente em `governance:*`).

**Pegadinha:** `CentrifugoPublisher` vive em `Modules/Whatsapp/` — semanticamente errado pra usar em comando de Governance. Mas é o wrapper canônico, mover dá quebra cascateada. Aceitar como dívida ou criar facade `app(CentrifugoPublisher::class)` neutra.

### 1.5 Pre-commit hook `.githooks/pre-commit`

105 linhas, **5 blocos `if`**:

1. **PHP discovery** (linhas 21-43) — Herd Windows .bat fallback
2. **Scope check** (linhas 45-61) — `bin/check-scope.php --staged $STRICT_FLAG`
3. **UI Lint** (linhas 63-86) — `artisan ui:lint --changed-only --baseline=...` se baseline exists
4. **Secrets governance** (linhas 88-103) — `artisan secrets:scan --diff-only --fail-on-drift`

**Convenções existentes:**
- Cada bloco lê env var `OIMPRESSO_*_STRICT` pra controlar bloqueio vs warning
- Default warning, strict via env var
- Mensagens com `echo ""` + bullets + `git commit --no-verify` instruction
- Refs ADR sempre citadas
- Exit code 1 só em strict + falha real

**Decisão pendente:** unificar 4 blocos em `artisan governance:audit --diff-only --fail-on-drift` que internamente invoca registry? OU manter 3 + adicionar 4o?

### 1.6 GH Actions workflows

32 workflows ativos. Filtrados por relevância DriftChecker:

| Workflow | Trigger | Faz |
|---|---|---|
| `secrets-governance.yml` (ADR 0215) | PR + schedule daily 09 UTC + dispatch | jobs `scan` (PR) + `audit` (schedule) |
| `scope-guard.yml` | (não inspecionado — provável check-scope.php) | — |
| `ui-lint.yml` | (provável ui:lint command) | — |
| `governance-gate.yml` | (não inspecionado) | — |
| `adr-lint.yml` | (não inspecionado) | — |
| `charter-gate.yml` | (não inspecionado) | — |
| `memory-schema-gate.yml` + `memory-schema-gate-extended.yml` | (não inspecionado) | — |
| `mwart-gate.yml` | (não inspecionado) | — |
| `module-grades-gate.yml` | (não inspecionado) | — |
| `phpstan-gate.yml` | (não inspecionado) | — |
| `eslint-gate.yml` | (não inspecionado) | — |
| `infra-contract-required.yml` | (não inspecionado) | — |

**Pattern reusável** observado em `secrets-governance.yml`:
- `actions/checkout@v4` → `shivammathur/setup-php@v2 (8.4)` → `composer install --no-progress --no-interaction --prefer-dist` → `php artisan <cmd>`
- Auto-PR usa `secrets.GITHUB_TOKEN` + git config user bot.

**Decisão pendente:** criar `.github/workflows/governance-drift.yml` master OU manter N workflows separados? Vantagem do master: 1 workflow lê registry, roda tudo; desvantagem: rebuild de composer/PHP repete-se.

### 1.7 Brief Jana ingestor

- **Módulo:** `Modules/Jana/` (commands inspecionados; serviço de brief é `Modules/Jana/Services/...`).
- **Brief comando:** `brief:generate` (Kernel.php linha 501) — cron 07/11/14/17/20/23 BRT.
- **Origem brief:** SQL aggregator em migration `2026_05_06_172445_fix_brief_procedure_real_schema.php` (consulta `mcp_audit_log`).
- **Como brief consome eventos estruturados:** ADR 0215 afirma "Brief Diário Jana (cron 06h BRT) ingere log estruturado `secrets.drift_detected` → seção '🔴 Atenção'". Mas implementação concreta de `secrets.drift_detected` ingest **não foi verificada** — pode ser TODO. `SecretsAuditCommand` apenas faz `Log::channel('single')->warning('secrets.drift_detected', [...])`, sem hook explícito no `BriefGenerateCommand`.
- **Handler genérico `governance.drift_detected`** = adicionar logger.channel structured + atualizar SQL aggregator do brief OU criar `BriefSection` plugável.

**Achado crítico:** integração brief × drift ainda é **promessa documental** sem código verificado. Plugar DriftChecker no brief pode ser sub-tarefa nova (não está pronto).

### 1.8 MCP `decisions-search`

- Lado PHP: `Modules/Jana/Console/Commands/SeedAdrsCommand.php`, `Modules/Jana/Console/Commands/JanaValidateMemoryCommand.php`, `Modules/Jana/Console/Commands/JanaBacklinksSweepCommand.php` parseiam ADRs frontmatter.
- Schema canônico: `mcp_memory_documents` tem coluna lifecycle/tags (frontmatter YAML parsed).
- **Como AdrLinksChecker plugaria:** consumir `mcp_memory_documents` via `DB::table()` filtrando `lifecycle='active'` + cross-check referências (markdown links `[ADR XXXX]`) — pattern já feito em `JanaBacklinksSweepCommand`. Reusar lógica do Backlinks command, NÃO duplicar parser de ADR.

### 1.9 `Modules/Arquivos` backbone

ADR 0214 Sprint 0.3 (commit `82c932c15` 2026-05-28) trouxe disks `arquivos-minio` + `vault-minio`. Backbone disponível pra hospedar `governance_drift_reports` se virar entidade persistida com binary attachments (ex: diff snapshots, screenshots). **NÃO necessário pro MVP** — drift reports cabem em `mcp_alertas_eventos.metadata JSON`. Plugar Arquivos só se report virar artefato com PDF/JSON >100KB.

### 1.10 Tabelas DB existentes pra audit/log

| Tabela | Origem | Schema-chave | Tier 0? |
|---|---|---|---|
| `mcp_alertas_eventos` | `Modules/Jana/Database/Migrations/2026_04_29_600001_create_mcp_alertas_eventos_table.php` | `id`, `user_id`, `business_id NULL ok`, `tipo`, `severidade`, `titulo`, `descricao`, `chave_idempotencia UNIQUE`, `metadata JSON`, `status enum(aberto,notificado,ack,arquivado)`, `criado_em`, timestamps | Sim, mas explicitamente **suporta `business_id NULL`** pra alertas repo-wide |
| `mcp_alertas` | (regras, não eventos) | usado por `governance:scorecard-snapshot --alert` | — |
| `mcp_audit_log` | (não inspecionei migration) | usado por brief + ActionGate | sim |
| `mcp_module_grades_history` | (não inspecionei) | snapshot daily | cross-tenant |
| `mcp_scorecard_runs` | (não inspecionei) | bucket-scoped | cross-tenant |
| `system_logs` | NÃO encontrado em migrations recentes | — | — |

**Pattern canônico:** `chave_idempotencia` formato `<tipo>:<id1>:<id2>:<YYYY-MM-DD>` — 1 alerta por dia idempotente, próximo dia novo. **Reusar tabela `mcp_alertas_eventos` exatamente como `DetectDriftCommand` faz** — não criar `governance_drift_reports` nova.

---

## 2. Não duplicar (peças JÁ existentes — não tocar)

| Já existe — NÃO recriar | Onde está |
|---|---|
| Comando `governance:detect-drift` com persistência idempotente | `Modules/Governance/Console/Commands/DetectDriftCommand.php` (407 linhas, sofisticado) |
| Tabela `mcp_alertas_eventos` com schema canônico drift-friendly | migration 2026_04_29_600001 — Tier 0 compliant (`business_id NULL` ok) |
| `GovernanceServiceProvider` registrando commands | `Modules/Governance/Providers/GovernanceServiceProvider.php` |
| `CentrifugoPublisher` wrapper com OTel + fail-silent | `Modules/Whatsapp/Services/Centrifugo/CentrifugoPublisher.php` |
| `DriftAlertService` (UI runtime scan) | `Modules/Governance/Services/DriftAlertService.php` (215 linhas) |
| `bin/check-scope.php` CLI standalone pra pre-commit | `bin/check-scope.php` |
| Pre-commit hook com 4 blocos + env var STRICT | `.githooks/pre-commit` |
| `secrets:scan` + `secrets:audit` (ADR 0215) | `app/Console/Commands/Secrets*.php` |
| `jana:health-check` + `jana:system-audit` (ADR 0133) | `Modules/Jana/Console/Commands/` |
| Pattern OTel span via `OtelHelper::span` em commands | exemplo `GovernanceHealthCommand` linha 52 |
| Pattern `Log::channel('single')->error/warning` pra ALERT | toda Kernel.php |
| Pattern schedule `->onOneServer()->withoutOverlapping()->environments(['live'])->onFailure(...)` | Kernel.php padrão dominante |
| GH workflow template `setup-php@v2 (8.4) + composer install --no-progress` | `.github/workflows/secrets-governance.yml` |

---

## 3. Plug-points exatos (criar + editar)

### 3.1 Arquivos NOVOS a criar

| Arquivo | Linhas estimadas | Conteúdo |
|---|---|---|
| `Modules/Governance/Contracts/DriftChecker.php` | ~30 | Interface PHP: `name(): string`, `check(): DriftCheckResult`, `severity(): string`, `description(): string`, `tags(): array` |
| `Modules/Governance/Services/DriftCheckResult.php` | ~50 | DTO: `name`, `ok bool`, `drift_count int`, `findings array`, `metadata array`, `centrifugo_payload array` |
| `Modules/Governance/Services/DriftCheckerRegistry.php` | ~80 | Singleton: `register(DriftChecker $c)`, `all(): array`, `get(string $name)`, `byTag(string $tag): array` |
| `Modules/Governance/Console/Commands/GovernanceAuditCommand.php` | ~150 | Assinatura `governance:audit {--check=} {--all} {--diff-only} {--fail-on-drift} {--auto-pr} {--notify} {--json}`. Itera registry, agrega resultados, persiste em `mcp_alertas_eventos`, opcional Centrifugo + auto-PR |
| `Modules/Governance/Services/Checkers/SecretsScanChecker.php` | ~50 | Wrapper que delega pra `SecretsScanCommand` lógica existente (extract pra Service) |
| `Modules/Governance/Services/Checkers/SecretsAuditChecker.php` | ~50 | Idem `SecretsAuditCommand` |
| `Modules/Governance/Services/Checkers/ModuleScopeChecker.php` | ~50 | Wrapper `DetectDriftCommand` lógica (extract handle() pra service) |
| `Modules/Governance/Services/Checkers/AdrLinksChecker.php` | ~80 | NOVO — usa lógica `JanaBacklinksSweepCommand` pra detectar ADR órfãs |
| `Modules/Governance/Services/Checkers/RoutesZombieChecker.php` | ~60 | NOVO — Route::getRoutes() × controller existe |
| `Modules/Governance/Services/Checkers/MultiTenantScopeChecker.php` | ~60 | NOVO — Eloquent Models sem global scope BusinessIdScope (Tier 0) |
| `Modules/Governance/Services/Concerns/PersistsDriftAlert.php` | ~80 | Trait extraído de `DetectDriftCommand::persistirAlerta()` — reusável pelos N checkers |
| `Modules/Governance/Services/Concerns/PublishesDriftToCentrifugo.php` | ~40 | Trait: usa `app(CentrifugoPublisher::class)->publish('governance:drift', ...)` |
| `Modules/Governance/Tests/Feature/GovernanceAuditCommandTest.php` | ~150 | Pest: registra fake checkers, valida agregação + idempotência |
| `Modules/Governance/Tests/Unit/DriftCheckerRegistryTest.php` | ~80 | Pest: register/get/byTag |

### 3.2 Arquivos EXISTENTES a editar

| Arquivo | Linha aproximada | Ação |
|---|---|---|
| `Modules/Governance/Providers/GovernanceServiceProvider.php` | 22 (registerCommands) + 41 (register) | Adicionar singleton `DriftCheckerRegistry`, registrar `GovernanceAuditCommand`, registrar 6 checkers default no registry |
| `Modules/Governance/Console/Commands/DetectDriftCommand.php` | 74 (handle) | Refatorar `handle()` extraindo lógica pra `ModuleScopeChecker` service; manter command como wrapper backward-compat (alias `governance:audit --check=module-scope`) |
| `app/Console/Commands/SecretsAuditCommand.php` | 57 (handle) | **DECISÃO PENDENTE:** refatorar pra implementar `DriftChecker` OU manter standalone + adapter `SecretsAuditChecker`. Skill `memory-first-secret-search` referencia `secrets:audit` — não quebrar interface CLI |
| `app/Console/Commands/SecretsScanCommand.php` | 62 (handle) | Idem |
| `app/Console/Kernel.php` | linha 305 + 699 + 712 | **Substituir** 3 schedule entries (`governance:detect-drift`, `secrets:audit --auto-pr --notify`, `secrets:scan` weekly) por **1 nova** `governance:audit --all --auto-pr --notify` daily 06:35 BRT (libera 06:15) |
| `.githooks/pre-commit` | linhas 88-103 | Substituir bloco `secrets:scan --diff-only` por `governance:audit --diff-only --fail-on-drift` (chama registry) — OU manter 3 atuais + adicionar 4o (decisão pendente) |
| `.github/workflows/secrets-governance.yml` | 1-52 | Renomear pra `governance-drift.yml`, generalizar PR trigger + schedule (mesmo template `setup-php@v2 + composer install`) |
| `memory/_INDEX-SECRETS.md` | (não tocar conteúdo) | OK manter — SecretsAuditChecker continua atualizando in-place |
| `memory/decisions/0215-secrets-governance-5-camadas-automaticas.md` | frontmatter | Adicionar `superseded_by: [0216]` quando ADR 0216 ratificada (ou `amended_by`) |

### 3.3 NÃO criar (suficiente reusar)

- ❌ `governance_drift_reports` tabela — usar `mcp_alertas_eventos` com novo `tipo='drift_<checker_name>'`
- ❌ `app/Domain/Governance/` — já existe `Modules/Governance/` com Services/Contracts
- ❌ Wrapper Centrifugo novo — usar `CentrifugoPublisher` do Whatsapp (dívida documentada)
- ❌ `app/Providers/GovernanceServiceProvider.php` — já existe em `Modules/Governance/Providers/`

---

## 4. Pegadinhas (filtradas — só aplicáveis)

1. **Multi-tenant Tier 0 (ADR 0093)** — `DriftCheckerRegistry` é **repo-wide** (igual `DetectDriftCommand`). `mcp_alertas_eventos.business_id = NULL` pra drift de infra/governance. Mas alguns checkers podem ser per-business (ex: `MultiTenantScopeChecker` rodando por tenant) — interface deve permitir `?int $businessId = null`. **Atenção:** se checker iterar businesses, sempre `Business::query()->each(fn ($b) => ...)`, nunca cross-tenant query.

2. **Schedule colisão 06:15 BRT existente** — Kernel.php linhas 213, 305, 699, 820 disputam slot. Adicionar `governance:audit --all` no mesmo minuto piora. **Decidir slot novo:** 06:35 BRT (livre, após `charter:health` 06:30) ou 06:45 BRT.

3. **DetectDriftCommand 407 linhas é sofisticado, não duplicar** — `persistirAlerta()` (linhas 295-365) tem schema `mcp_alertas_eventos` mapping completo + idempotência por dia + fallback log + descrição formatada. **Extrair pra trait `PersistsDriftAlert`**, NÃO reescrever do zero no `GovernanceAuditCommand`.

4. **CentrifugoPublisher mora em Whatsapp (Modules/Whatsapp/Services/Centrifugo/)** — semanticamente errado pra Governance usar. Mover quebra cascateado (Whatsapp consome internamente). **Aceitar dívida documentada**; refator futuro.

5. **`secrets:audit` faz `gh CLI shell_exec` direto** — `app/Console/Commands/SecretsAuditCommand.php:241-258` chama `git switch + commit + push + gh pr create`. Padrão NÃO multi-tenant safe + difícil testar (Pest precisa mock). Se DriftCheckerRegistry abstrair "auto-PR action", precisa interface `--auto-pr` separada que cada checker decide se suporta.

6. **`OtelHelper::span` é padrão obrigatório** — `GovernanceHealthCommand::handle()` usa `OtelHelper::span('governance.health.run', [...], fn () => $this->runChecks())`. `GovernanceAuditCommand` deve seguir mesmo padrão — wrap registry iteration em `OtelHelper::span('governance.audit.run', ['count' => N], fn ...)` + 1 span por checker.

7. **Pre-commit hook PHP discovery Windows-aware** — `.githooks/pre-commit:21-43` tem fallback Herd `.bat`. Bloco novo `governance:audit --diff-only` precisa reusar mesma variável `$PHP_BIN` — não duplicar discovery.

8. **`mcp_alertas_eventos.chave_idempotencia` UNIQUE = 200 chars max** — formato canônico `<tipo>:<id1>:<id2>:<YYYY-MM-DD>` cabe folgado, mas se checker gerar chave com path longo (`Modules/Whatsapp/Http/Controllers/Tres/Quatro/Cinco/AlgumController.php`), pode estourar. Trate `mb_substr($chave, 0, 200)` no trait.

9. **Skill `memory-first-secret-search` referencia `secrets:audit` Tier A bloqueante** — refatorar pra `governance:audit --check=secrets-audit` quebra skill. Manter alias backward-compat `secrets:audit` → wraps `governance:audit --check=secrets-audit` (alias Symfony Console).

10. **`Modules/Governance/Console/Commands/DetectDriftCommand` exit code 1 quando drift detectado (NÃO erro)** — cron `appendOutputTo` log file. `GovernanceAuditCommand` deve preservar semântica: exit 1 = drift / exit 0 = clean / exit >1 = erro fatal. CI gates já dependem disso.

11. **Composer.lock drift bug 2026-05 (ADR 0063)** — `composer install` em GH Actions pode falhar se composer.lock stale. Workflow `governance-drift.yml` herda mesmo problema; usar `--prefer-dist` + cache padrão.

12. **PHPStan/Pest CI gates já ativos** — qualquer Service/Trait nova precisa passar `phpstan-gate.yml`. Tipagem estrita: `declare(strict_types=1);` + `@return` doc + readonly props onde possível.

---

## 5. Checklist pré-código (15 itens)

### Antes de Edit/Write

- [ ] **Decisão pendente 1:** persistir drift reports em DB (`mcp_alertas_eventos` existente) vs criar tabela nova `governance_drift_reports`? Recomendação: **reusar `mcp_alertas_eventos`** com `tipo='drift_<checker_name>'` (não duplicar tabela)
- [ ] **Decisão pendente 2:** refatorar `SecretsAuditCommand`/`SecretsScanCommand` pra implementar interface `DriftChecker` OU criar adapter `SecretsAuditChecker` mantendo comando standalone? Recomendação: **adapter** (preserva skill Tier A `memory-first-secret-search`)
- [ ] **Decisão pendente 3:** unificar pre-commit em 1 bloco `governance:audit --diff-only` OU manter 4 blocos? Recomendação: **adicionar 4o bloco "governance:audit"** mas manter 3 atuais funcionando (3-fase migração lenta)
- [ ] **Decisão pendente 4:** business scope global? Recomendação: **DriftCheckerRegistry e MVP repo-wide** (`business_id=NULL`); checker per-business é Sprint 2
- [ ] **Decisão pendente 5:** novo schedule slot. Recomendação: **06:35 BRT** (livre após charter:health 06:30)
- [ ] **Decisão pendente 6:** ler RUNBOOK existente? **Não existe ainda** — criar `Modules/Governance/RUNBOOK-DRIFT-FRAMEWORK.md` ao final
- [ ] **Decisão pendente 7:** feature flag necessária? Recomendação: **sim** — `GOVERNANCE_DRIFT_FRAMEWORK_ENABLED=true` em `config/governance.php` (igual `d1_hardened`); permite rollback fácil
- [ ] **Decisão pendente 8:** schema migration necessária? **NÃO** — `mcp_alertas_eventos` já cobre + JSON metadata é flexível
- [ ] **Decisão pendente 9:** ADR 0216 mãe necessária? **SIM, obrigatório** — Tier 0 framework cross-cutting precisa Nygard ADR antes de PR mergeable

### Pegadinhas a respeitar (filtradas, ordem de prioridade)

- [ ] Pegadinha 1 (Multi-tenant Tier 0)
- [ ] Pegadinha 2 (slot 06:15 evitar)
- [ ] Pegadinha 3 (reusar `persistirAlerta()` via trait)
- [ ] Pegadinha 5 (auto-PR action abstrair)
- [ ] Pegadinha 6 (OtelHelper::span)
- [ ] Pegadinha 8 (chave_idempotencia 200 chars)
- [ ] Pegadinha 9 (alias backward-compat `secrets:audit`)
- [ ] Pegadinha 10 (exit code semântico)

### Pontos de plugue (ordem de execução)

1. [ ] Criar `Modules/Governance/Contracts/DriftChecker.php` + `DriftCheckResult.php`
2. [ ] Criar `Modules/Governance/Services/DriftCheckerRegistry.php`
3. [ ] Criar traits `PersistsDriftAlert` + `PublishesDriftToCentrifugo` (extrair de `DetectDriftCommand::persistirAlerta`)
4. [ ] Criar 6 checkers: ModuleScope, SecretsScan, SecretsAudit, AdrLinks, RoutesZombie, MultiTenantScope
5. [ ] Criar `Modules/Governance/Console/Commands/GovernanceAuditCommand.php` (registry orchestrator)
6. [ ] Editar `GovernanceServiceProvider::register()` + `registerCommands()` (singleton + registro)
7. [ ] Refatorar `DetectDriftCommand::handle()` → delegar pra `ModuleScopeChecker` (back-compat preservado)
8. [ ] Editar `app/Console/Kernel.php`: ADICIONAR `governance:audit --all` 06:35 BRT, marcar 3 schedules antigos deprecated em comentário, NÃO remover ainda (canary 7d)
9. [ ] Editar `.githooks/pre-commit`: adicionar bloco 4o reusando `$PHP_BIN`
10. [ ] Editar `.github/workflows/secrets-governance.yml` → renomear `governance-drift.yml` + generalizar
11. [ ] Criar Pest tests `GovernanceAuditCommandTest` + `DriftCheckerRegistryTest`
12. [ ] Criar `Modules/Governance/RUNBOOK-DRIFT-FRAMEWORK.md`
13. [ ] Criar ADR 0216 mãe (Nygard)
14. [ ] Smoke biz=1 manual em local com `php artisan governance:audit --all --json`
15. [ ] PR ≤300 linhas (skill commit-discipline) — provavelmente precisa **3 PRs**: PR1 interface+registry+1 checker, PR2 demais checkers, PR3 schedule+pre-commit+CI migração

### Smoke pós-deploy

- [ ] biz=1 (test) — `php artisan governance:audit --all --json` retorna estrutura agregada com 6 checkers
- [ ] biz=4 (ROTA LIVRE prod, canary opcional) — observar `mcp_alertas_eventos` WHERE `tipo LIKE 'drift_%'` AND `criado_em > NOW() - INTERVAL 1 DAY` — pelo menos 1 row dia
- [ ] Validar Centrifugo `governance:drift` channel publica quando `--notify` (testar localmente vs CT 100)
- [ ] Validar pre-commit hook strict: commit forçando drift bloqueia
- [ ] Validar exit code: `governance:audit --check=secrets-scan --fail-on-drift` retorna 1 quando drift

### Estimativa total (IA-pair, ADR 0106 recalibrado)

- Interfaces + Registry + Traits: **~1h** (read existing, draft, test)
- 6 Checkers (4 reuso + 2 novos): **~2h**
- GovernanceAuditCommand: **~1.5h**
- Refactor DetectDriftCommand + provider edit: **~1h**
- Schedule + pre-commit + workflow: **~1h**
- Pest tests + smoke: **~2h**
- ADR 0216 + RUNBOOK: **~1h**
- Margem 2x: **× 2 = ~19h total** (≈ 3 dias úteis com revisão Wagner) em 3 PRs

---

## 6. Decisões pendentes (resumo Wagner aprova/rejeita)

| # | Decisão | Recomendação |
|---|---|---|
| D1 | DB nova `governance_drift_reports` vs reusar `mcp_alertas_eventos` | **reusar** (não duplicar) |
| D2 | Refatorar `SecretsAuditCommand` direto vs adapter? | **adapter** (preserva skill Tier A) |
| D3 | Pre-commit: unificar em 1 bloco vs adicionar 4o? | **adicionar 4o** (back-compat 3 atuais) |
| D4 | Business scope global vs per-business? | **global MVP** (per-business Sprint 2) |
| D5 | Schedule slot novo | **06:35 BRT** (livre) |
| D6 | RUNBOOK | criar `Modules/Governance/RUNBOOK-DRIFT-FRAMEWORK.md` |
| D7 | Feature flag `GOVERNANCE_DRIFT_FRAMEWORK_ENABLED` | **sim, default true em local, false em live até canary** |
| D8 | Migration nova | **não** (mcp_alertas_eventos suficiente) |
| D9 | ADR 0216 obrigatória | **sim** — Nygard antes de qualquer Edit em Modules/Governance/ |
| D10 | Strategy de remoção 3 schedules antigos (06:15) | **canary 7d**: adicionar `governance:audit` 06:35, observar 1 semana, depois remover entries antigas em PR2 |
| D11 | Mover `CentrifugoPublisher` de Whatsapp pra Governance? | **não** (dívida documentada — refator futuro) |
| D12 | Alias `secrets:audit` → `governance:audit --check=secrets-audit` | **sim, manter alias** (não quebrar skill `memory-first-secret-search`) |

---

## Resumo executivo (final)

- **Estado de partida:** PARCIAL ~50% (`Modules/Governance/` existe + 4 commands governance: + tabela canônica `mcp_alertas_eventos` + Centrifugo wrapper). Esqueleto pronto, falta **abstração comum** (interface + registry + command master).
- **Maior risco/pegadinha:** **slot 06:15 BRT já tem 4 schedules colidindo** (jana:system-audit + secrets:audit + governance:detect-drift + nfebrasil:dist-dfe-puxar). Adicionar `governance:audit` no mesmo slot piora; mover pra 06:35 BRT.
- **PR splitting obrigatório:** ≤300 linhas força mínimo 3 PRs (commit-discipline). PR1 = interface+registry+1 checker exemplar; PR2 = restante dos checkers; PR3 = schedule/pre-commit/workflow migração + remoção entries 06:15 antigas.
- **ADR 0216 mãe pendente** — sem ela, qualquer Edit em `Modules/Governance/` viola governance própria (Tier 0).

Wagner aprova seguir o checklist com essas 12 decisões pendentes resolvidas como recomendado?
