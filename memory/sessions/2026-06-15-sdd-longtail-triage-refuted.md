---
date: "2026-06-15"
topic: "Triagem refutada da cauda longa do floor SDD (corruptores era-sqlite): linter mente ~48%, cauda real ~55-60 arquivos; 4 afterEach Governance corrigidos (drops-floor + mantém cobertura)"
authors: [W, C]
related_adrs: ["0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0093-multi-tenant-isolation-tier-0"]
prs: []
---

# Cauda longa do floor SDD — triagem refutada (2026-06-15)

> **Pedido [W]:** "do plano sdd: Floor ~1308 (de 1928); o mecânico já foi (RC-1..31); sobra a cauda longa (~1078), triagem real por arquivo, não mecânica. **Escolha a melhor resposta, use um refutador e faça.**"
> **Método:** 4 analistas de cluster (read-only) + 1 refutador adversarial (ADR 0276) sobre `origin/main` (worktree `sdd-triage-main` @ `b23db7193`), + verificação própria do conflito refutador↔analista. Esta sessão NÃO roda Pest (worktree órfão sem vendor) — verificação = leitura de código + `php -l` + cross-check; prova final = CT100.

## TL;DR — a descoberta que reescreve a cauda

1. **O `sqlite-test-corruptors.mjs` MENTE ~48%.** Ele text-match `Schema::create/drop` e marca "quarentenado" só com a string literal `era-sqlite`. Não enxerga: (a) guarda `markTestSkipped` + `config('database.default')!=='sqlite'` com mensagem ≠ "era-sqlite"; (b) DDL dentro de `expect()->toContain('Schema::create(...')` (source-readers); (c) `DB::purge()` dos gates de browser. Dos **99 "floor-droppers abertos"** (29 S + 70 A) → **~51 corruptores reais · ~39 já-guardados/dual-mode · ~9 sem-DDL**.
2. **A cauda real ≈ 55-60 arquivos**, não 99/237 — dominada por 2 clusters batcháveis (Whatsapp-A ~19, Jana/Mcp-A ~30) + poucos droppers de tabela-CORE de alto raio.
3. **RC-1..31 quarentenou o B/C tail; o S-tier de maior raio quase não se moveu** (~30→29 não-quarentenados desde 06-13).
4. **Doutrina correta:** CONVERT (RefreshDatabase/DatabaseTransactions, mantém cobertura) > QUARANTINE (`era-sqlite`, derruba o número mas a suíte mente). Vários "alvos" do linter são Tier-0/segurança — quarentenar = perder a garantia no único lugar que ela roda (MySQL).

## ⚠️ CORREÇÃO pós-execução (2026-06-15) — CONVERT estava ERRADO; o certo é GUARD

O item 4 acima (e a tabela "CONVERT" abaixo) foi **refutado na execução** e fica
SUPERADO. Pré-flight provou:
- a lane **per-PR roda em SQLITE** (`phpunit.xml` + `modules-pest.yml` → `DB_CONNECTION=sqlite`); só o nightly CT100 é MySQL;
- as migrations são **MySQL-only** (`ci.yml`: *"RefreshDatabase esbarra na migration MySQL-only ALTER TABLE"*; não existe `sqlite-schema.sql`).

→ CONVERT→RefreshDatabase **quebraria a lane sqlite**. A cobertura real desses testes
é na lane sqlite (per-PR); no MySQL eles só **corrompiam**. O fix correto é **GUARD
sqlite-only** (`markTestSkipped` não-sqlite no beforeEach + guarda no teardown) — mantém
a cobertura sqlite e para a corrupção no nightly. CI-safe (comportamento sqlite inalterado).
Vale inclusive pros Tier-0/segurança: guardar **preserva** a garantia na lane sqlite.

**Executado (tudo mergeado):** PR #2746 Governance afterEach ×4 · PR #2749 linter v2
(comportamento-no-MySQL, ~48% FP removido) · PR #2753 Whatsapp-A guard ×20 · PR #2756
Jana/Mcp+Copiloto guard ×28 · PR linter v3 (dual-mode `if(sqlite){drop}` reconhecido).

**Estado honesto do floor (corruptor-linter v3):** de 67 → **9 corruptores reais**
(BomResolver, ReservarEstoqueBom [FSM/Inv]; ContextForTaskActiveTasks [ADS]; DetectOtelQuery,
DashboardExtension, DetectDriftCommand, ObservabilitySnapshot, ScorecardSnapshot, Wave28
[Governance-A]). Os "fantasmas" (NfeBrasil ×5 + RB RepositoryWave18 + outros dual-mode)
saíram da conta — eram falso-positivo `if(driver==='sqlite'){DDL}else{row-delete}`.
Contrato do linter travado em `tests/sqliteCorruptors.spec.ts` (2 lados, com polaridade).

## Conflito refutador↔analista — RESOLVIDO (esta é a razão de "use um refutador")
As 4 telas Governance (`CrossTenantPolicy/GovernanceGate/MultiTenantGovernance/SmokeRoutes`): o refutador disse "já pulam no MySQL → deixar"; o analista disse "corruptor via afterEach". **Vencedor: analista.** `beforeEach` é guardado (L37 etc), **mas `afterEach` dropa `mcp_*` SEM guarda**. Prova no próprio repo: RB/Repair já-corrigidos carregam o comentário *"o afterEach roda MESMO em teste pulado (PHPUnit 12: tearDown gated só por hasMetRequirements, true antes do beforeEach/markTestSkipped)"*. → afterEach sem guarda dropa tabela real no MySQL persistente. O refutador só olhou o `beforeEach`. **Lição: refutar o refutador também.**

## Classificação corrigida (por cluster)

| Cluster | n linter | corruptor REAL | DECISÃO | floor-impact | esforço |
|---|--:|--:|---|---|---|
| RecurringBilling S | 14 | **0** | nenhuma (beforeEach+afterEach já guardados) | nenhum | 0 |
| Governance S (4) | 4 | **4** | CONVERT mínimo: guarda no afterEach (✅ FEITO aqui) | drops-floor + mantém cobertura | ~20min (feito) |
| Inventory/FSM S | 3 | **2** (BomResolver, ReservarEstoqueBom) | CONVERT→RefreshDatabase | drops-floor | ~1-2h par |
| Jana ClarifyCascade S | 1 | **1** | CONVERT | drops-floor | ~30-45min |
| Whatsapp S (WhatsmeowWebhookAuth) | 1 | **1** (dropa `business`!) | CONVERT (NÃO quarentenar — segurança) | drops-floor (pior raio) | ~30min · URGENTE |
| Whatsapp A | ~20 | **~18** | CONVERT em lote (recipe único) | drops-floor | ~1 sessão |
| Jana/Mcp/Gov/Copiloto A | ~32 | **~30** | CONVERT em lote (DatabaseTransactions) | drops-floor (7 ERROM já "table exists") | ~1 dia |
| ADS ContextForTaskActiveTasks | 1 | **1** (dropa `mcp_tasks`!) | CONVERT | drops-floor (core) | ~30min |
| Vestuario Wave28Devolucao | 1 | **1** | CONVERT | drops-floor | ~30min |
| NfeBrasil A (6) + Financeiro A (3) + Cliente charter + Browser gates (4) + Essentials/Repair-Device | ~20 | **0** | DON'T-TOUCH / refutado | nenhum | 0 |

## DON'T-TOUCH register (quarentenar = cegar uma garantia, e a suíte mente)
- **Browser/CoreScreens** (`A11yAxe/AuthBridge/ConformanceProbes/PixelBaseline`) — SÃO os gates Q1/Q4. `DB::purge` é cross-process intencional, zero DDL. Quarentenar desliga o gate.
- **Tier-0 multi-tenant:** Whatsapp `MultiTenantIsolationTest` (R-WA-005, ADR 0093), Vestuario cenário-7. → CONVERT, nunca quarentenar.
- **Segurança:** `WhatsmeowWebhookAuthTest` (anti-spoof 06-14), `WebhookSignatureTest`, `WebhookReplayProtectionTest`. → CONVERT.
- **Source-readers (NÃO são corruptores):** Financeiro `OndaCommentsAuditBridge`/`UnificadoCommentsAudit`, `ClienteIndexDrawer760CharterTest` (confirma "NÃO tocar" anterior), `FluxoCaixaServiceTest` (sem `Schema::`).
- **Migração-própria:** `AutomationRegistryMigrationTest` — RDB é incompatível (chama `up()` em tabela ausente). → única QUARANTINE-para-sqlite legítima.

## Batches priorizados (para fatiar entre as sessões paralelas)
1. **Quick wins core-table (maior raio, faça já):** `WhatsmeowWebhookAuth` (business), `ADS/ContextForTaskActiveTasks` (mcp_tasks), Inventory ×2 (products/variations). 4 arquivos, CONVERT.
2. **Governance afterEach ×4** — ✅ FEITO nesta sessão (PR abaixo).
3. **Lote Whatsapp-A (~18)** — recipe: `uses(RefreshDatabase::class)` + apagar bloco manual `Schema::dropIfExists/create` de `whatsapp_*`. Ressalvas: `PhonesMigrationDataTest` (replica migração inline → rework), `SendMessageRequest` (precisa 1 row conversation).
4. **Lote Jana/Mcp-A (~30)** — recipe: `DatabaseTransactions` + apagar DDL manual. Ressalvas: `HandoffDraftTool` precisa NOVA migração `mcp_handoff_drafts`; `KbAnswerTool` mantém macro FULLTEXT→LIKE; `ScorecardSnapshotCommand` só o teste graceful-fail.

## O que ESTA sessão shipou
- **4 afterEach Governance guardados** (CrossTenantPolicy/GovernanceGate/MultiTenantGovernance/SmokeRoutes) — `php -l` ok. CONVERT mínimo: para a corrupção no MySQL (drops-floor downstream) **mantendo** a cobertura cross-tenant na lane sqlite. Padrão idêntico ao RB/Repair já mergeado. Prova final = CT100.
- Este doc (registro de honestidade da triagem).

## Follow-up recomendado (NÃO shipado de propósito)
- **Ensinar o linter** a reconhecer (a) `markTestSkipped`+driver-guard como quarentena-equivalente, (b) DDL em `toContain` como não-DDL, (c) `DB::purge` como não-destrutivo — **mas só com meta-teste 2 lados** (provar que um corruptor real como `WhatsmeowWebhookAuth` CONTINUA flagado e que um guardado vira limpo). Não shipei agora porque um regex apressado pode **sub-contar** (mascarar corruptor real = a suíte mente no sentido perigoso). Spec pronto; precisa de [W]/sessão dona do floor.

## Coordenação (anti-colisão)
- Trabalho isolado em worktree `sdd-triage-main` (branch `sdd/governance-aftereach-guard`), off `origin/main` fresco. Nenhum PR aberto toca os 4 Governance. Sessões paralelas ("KL-E2/E3", "turbo stages") devem pegar os batches 1/3/4 — este doc é o mapa para fatiar sem refazer.
