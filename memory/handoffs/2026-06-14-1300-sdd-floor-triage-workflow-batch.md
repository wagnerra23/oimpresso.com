---
date: "2026-06-14"
slug: sdd-floor-triage-workflow-batch
tldr: "Workflow multi-agente (11 clusters + verificação adversarial) triou a cauda do floor; aplicados RC-23..31 (8 PRs test-only, ~190 falhas endereçadas). Descoberto loader-blocker que silenciava NfeEmissaoServiceTest desde RC-16 (TestCase duplicado em tests/Feature) + bug no próprio RC-17 (givePermissionTo exige permission pré-existente). 2 bugs de produto reais viraram tasks. Medição limpa pendente (nightly 02:00)."
hour: "13:00 BRT"
topic: "SDD floor — triagem adversarial via Workflow + batch mecânico RC-23..31. Continuação do RC-track. Floor pré-batch 1308; impacto a medir no próximo nightly."
duration: "~3h (continuação)"
authors: [W, C]
related_adrs: ["0101-testes-mysql-real-nao-sqlite", "0093-multi-tenant-isolation-tier-0", "0135-whatsapp-channels"]
---

# Handoff — SDD floor: triagem por workflow + batch RC-23..31

> **TL;DR:** Disparei um **Workflow multi-agente** (11 agentes diagnóstico + verificação adversarial cética + síntese) sobre a cauda do floor (run `20260614-020001`, 1308 falhas). Ele confirmou causa-raiz + edit exato por cluster e separou **mecânico** de **bug-real** de **quarentena**. Apliquei **RC-23..31 (8 PRs test-only)** endereçando ~190 falhas. Dois achados de fundo: (1) **loader-blocker** — meu RC-16 pôs `uses(Tests\TestCase::class, ...)` num arquivo de `tests/Feature/` onde o `Pest.php` já aplica TestCase via `->in('Feature')` → "already uses the test case" → o harness movia o arquivo de lado TODO run desde que mergeou (16 testes nunca rodaram); (2) **RC-17 estava incompleto** — `givePermissionTo('x')` com STRING em Spatie v6 exige a permission pré-existente (lança PermissionDoesNotExist). 2 bugs de PRODUÇÃO reais viraram tasks (não tacked em batch de teste).

## PRs desta rodada (todos auto-merge squash)
| PR | Cluster | ~falhas | Tipo |
|---|---|---:|---|
| #2724 | RC-21 PaymentGateway DT (28 arq) | ~150 | commit-poison |
| #2725 | RC-22 NfeBrasil DT (4 arq) | ~17 | commit-poison |
| #2732 | RC-23 ADS Unit `uses(TestCase)` | ~26 | boot container |
| #2733 | RC-24 Financeiro `App\Role`→Spatie | ~15 | import errado |
| #2734 | RC-25 Cliente `Permission::findOrCreate` | ~28 | completa RC-17 |
| #2735 | RC-26 ProjectMgmt nome de permission | ~25 | typo permission |
| #2736 | RC-27 NFSe loader-blocker (TestCase dup) | (desbloqueia 16 + exit2) | corrige RC-16 |
| #2737 | RC-28 Sells `test()->skip()`→markTestSkipped | ~6 | API errada |
| #2738 | RC-29 KB `handle()`→app()->call | ~7 | DI container |
| #2739 | RC-30 quarentena cirúrgica (Whatsapp+Brief+Financeiro) | ~32 | snapshot superseded |
| #2740 | RC-31 PhoneResolution `updated_at` schema sintético | ~5 | test-drift |

Anterior (mesma sessão): RC-7/8 #2712, RC-10/11 #2714, RC-15 #2716, RC-16 #2717, RC-17 #2718, RC-18 #2719, RC-19 #2722, RC-20 #2723.

## Padrões de causa-raiz catalogados (novos)
1. **TestCase duplicado = loader-blocker.** Em `tests/Feature/**`, NÃO declarar `uses(Tests\TestCase::class)` — o `Pest.php` raiz já aplica via `uses(TestCase::class)->in('Feature')`. Só anexar traits extras: `uses(DatabaseTransactions::class)`. Declarar de novo → "already uses the test case" → fatal de coleção → harness move o arquivo de lado (testes somem) + `pest exit 2`. **Em `Modules/*/Tests/` o TestCase explícito É obrigatório** (fora do `->in('Feature')`) — RC-21/22 corretos.
2. **Spatie v6 `givePermissionTo('x')`/`hasPermissionTo('x')` com STRING exige a permission pré-existente** — senão `PermissionDoesNotExist`. Em teste sem o seeder, usar `Permission::findOrCreate('x','web')` antes (RC-25), ou apontar pra permission realmente semeada (RC-26).
3. **Unit tests (`Modules/*/Tests/Unit/`) que tocam `auth()`/`app()` precisam `uses(Tests\TestCase::class)`** pra bootar o container (RC-23) — `Pest.php` não auto-aplica lá.
4. **`test()->skip()` não existe** — `skip()` só encadeia no `it()`. In-body é `test()->markTestSkipped()` / `$this->markTestSkipped()` (RC-28, e o cético pegou a mesma armadilha no RC-30 Whatsapp).
5. **Job `->handle()` com deps tipadas** → `app()->call([$job,'handle'])` (method-injection), não `->handle()` cru (RC-29).

## Quarentena cirúrgica > file-wide (lição do cético)
O verificador adversarial REPROVOU 2 edits file-wide do diagnóstico porque mascaravam testes vivos:
- **Brief**: file-wide silenciaria 18 it() de FormRequest (anti-DoS/redact_pii/whitelist) que PASSAM. Solução: `beforeEach` que pula só por `str_contains($this->name(), 'generateWithFallback'|'serveCachedWithStaleFlag')`.
- **Financeiro cowork**: file-wide silenciaria 3 describe backend Tier-0 vivos. Solução: `->skip()` só nos 3 describe de UI morta (bridges JS + JSX removidos pós-Inertia F3).

## Bugs de PRODUÇÃO reais (viraram tasks, NÃO quarentenar)
- **task_016f5892 — McpToken sem SoftDeletes**: `RotateTokenCommand::rotateAllForUser` faz `whereNull('deleted_at')` → "Unknown column" em MySQL prod + `$token->delete()` hard-deleta (apaga audit LGPD ADR 0081). Fix: trait SoftDeletes + migration `deleted_at` (mcp_tokens = segredo Tier-0, precisa aprovação). ~8 falhas.
- **task_09f78735 — BridgeExpenseToTitulosCommand**: `->select('t.total_remaining_amount')` (coluna que nunca existiu) → "Unknown column" em prod. Fix: derivar `final_total - total_paid` (decisão de lógica). ~5 falhas.

## Medição — PENDENTE (gate)
- Run manual `20260614-092405` foi **truncado** (junit 0 bytes, ~2974/10452 testes — bug FV-F1 "processo morto antes do flush"; trigger manual às 09:24 morreu, transiente). NÃO é regressão de código.
- Floor limpo pré-batch = **1308** (run 020001). **Esperar o nightly automático 02:00** (cron) pra medir RC-21..31 juntos. Re-trigger manual arrisca repetir o truncamento FV-F1.
- Se cair proporcional → mecanismo confirmado. Buckets ainda abertos pós-batch: php-error residual, outros (mock/expectations), schema-col residual, + os 2 bugs de produto (tasks).

## Retomar
1. Ler floor do próximo nightly limpo (junit não-zero). Comparar vs 1308.
2. Pegar as 2 tasks de bug real (task_016f5892, task_09f78735).
3. Re-triar o que sobrar (bucket "outros" 345 + php-error residual) — provável mais quarentena + alguns bugs reais.
4. Workflow reusável: `sdd-floor-cluster-triage` (script salvo na sessão) — re-rodar com os novos clusters.
