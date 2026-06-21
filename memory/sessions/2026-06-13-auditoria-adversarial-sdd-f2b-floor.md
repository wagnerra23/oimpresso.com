---
date: "2026-06-13"
topic: "Auditoria adversarial do burn-down SDD Fase 2b — veredito sobre os fixes mergeados, mapa do que falta (US-GOV-019/020, Lanes A/B/C/D), e caracterização cirúrgica do lever do floor (<1928). 4 agentes paralelos read-only + resolução do #2664."
authors: [W, C]
related_adrs:
  - "0276-decisao-pelo-fluxo-classes-pares-adversariais"
  - "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"
prs: ["2664"]
---

# Auditoria adversarial SDD F2b + floor — 2026-06-13

> 4 agentes paralelos (3 read-only + 1 fix isolado). Base: `origin/main @ ~f3c21a49c`.
> Disparada por Wagner: "mostrar e investigar do plano o que ainda falta e colocar um adversário pra saber se foi bem implementado", + resolver #2664 e floor<1928.

## Resolvido nesta sessão

- **4 quick-wins US-GOV-018 re-triage mergeados** (sequencial): #2652 (Tier 0 — purga `business_id=4` cliente real dos fixtures), #2646 (`macro_variant_id` fillable), #2647 (`superadmin:health`), #2649 (`ads:health`).
- **#2664 mergeado** (charters SDD Semana-0). Causa-raiz do vermelho **não eram os charters** — era o PR #2642 que mergeou `Modules/OficinaAuto` (vertical automotivo legítimo) e fez o guard cross-module "automotive vocabulary" pegar `plate`/`vehicle`/`km` dele. Fix: `--exclude-dir='OficinaAuto'` no `.github/workflows/repair-shared-vocab.yml` + correção de bug onde `--exclude-dir='Modules/Repair'` era no-op (casa basename). Mergear #2664 **também destravou o vermelho do `main`** nesse guard.

## 1. Adversário — os fixes mergeados estão bem feitos?

| Fix | Veredito | Evidência / risco |
|---|---|---|
| #2646 macro_variant_id fillable | ✅ real | `Message.php:86-95`; teste `MacroVariantPickerTest.php:296` faz read-back real do DB; `HasBusinessScope` intacto |
| #2647 superadmin:health | ✅ real | `SuperadminServiceProvider.php:49-51`; registro incondicional (difere do ADS mas espelha Connector) |
| #2649 ads:health | ✅ real | `AdsServiceProvider.php:42-48` dentro de `runningInConsole()` — o mais correto |
| #2668 GLOB_BRACE | ✅ real (melhor) | `ScopedScorecardEvaluator.php:407` `defined()` + polyfill musl; zero bareword no repo |
| #2669 typo `$i_` | ✅ real | `AuthStateDriftCheckTest.php:71`; guard AUTH-DRIFT volta a valer |
| #2652 purga biz=4 | 🟡 **parcial** | 4 arquivos certos, mas `BusinessIdGuardTest` tem blind spots: sobraram `business_id=4` em `OtlpHttpHandlerTest:42`, `MetricasApuradorTest:202`, `McpAuthHealthTest:101` (`$business_id`), `TransactionBuilder:29` (`->business(4)`). Telemetria/mocks, risco operacional baixo — mas garantia "zero" é escopada ao regex, não literal (Tier 0). **Follow-up: endurecer regex do guard.** |
| Harness #2640/32/38 | 🟡 **parcial** | nightly roda MySQL real (provado); `phpunit.xml:72-73` sqlite não tocado ✅ — mas ver §2 |

## 2. Dois achados: `main` está pior do que a doc afirma

1. **Frente C nunca entrou no repo (p0 · US-GOV-020).** #2657 **fechado sem merge**. Grants (`log_bin_trust_function_creators` + `SET_USER_ID`) só **live no CT100** (resetam em restart). Branch `sdd/fc-trigger-definer-privilege` ainda existe (2 commits: `98259e50f` grants + `7371db9ea` revert A.2), **sem PR aberto**. → **floor não reproduzível** de clone limpo (triggers DEFINER de prod + binlog ON → ERROR 1419/1227). SPEC US-GOV-020 stale (diz "Fix em #2657 / floor 1870" como resolvido).
2. **A.2 (`FULLSUITE_FK_OFF`) continua VIVO em `main`** (`ct100-fullsuite.sh:~191` + `tests/TestCase.php:30-35`) apesar do handoff declarar "anti-padrão net-harmful, revertido". O revert estava no mesmo #2657 fechado. **Código versionado roda o que o time concluiu ser pior.**

## 3. Floor < 1928 — o lever real

Não é harness — é **isolamento de teste, e a suíte não tem estratégia de rollback por padrão**: de 1227 testes, **0 RefreshDatabase, 0 DatabaseMigrations, só 39 DatabaseTransactions**; `tests/Pest.php` não aplica trait de rollback → maioria roda contra **MySQL persistente compartilhado** com `executionOrder=random`.

- **Movedor #1: ~19 testes "era-sqlite" que dropam/recriam tabelas CORE sem guarda** → as ~530 `Base-table-not-found` (business 200×, activity_log 73×, users 46×).
- **Classes de ofensor:** O1 (drop CORE unguarded) · O2 (sem rollback default, raiz) · O3 (`Carbon::setTestNow`/`Cache`/`Config`/`fake` sem teardown) · O4 (`Business::first()` em setup) · O5 (`DB::commit()` em `SellPosControllerStoreInvariantsTest`).
- **Plano:** Fase 0 baseline medido → **Fase 1 piloto 5-8 corruptores disjuntos (1 PR cada, MEDIR queda)** → escalar O1 → O4 `Business::first()`→`seededTenant()` → O5/O3 → **Fase 5 `DatabaseTransactions` default no `Pest.php`** (trava o floor, mas toca harness = `decide()`+Wagner). Ordem deliberada: O1 antes de O2 (DDL dentro de transação quebra rollback).
- **🚧 Colisão:** sessão paralela edita `tests/TestCase.php` (mexido 14:39) + cluster Whatsapp/ChannelUserAccess. Harness compartilhado (`ct100-fullsuite.sh`, `phpunit.xml`, `Pest.php`, `TestCase.php`) = não tocar sem coordenar. Disjuntos seguros: Jana, PaymentGateway, KB.
- **Piloto sugerido (low-risk, disjuntos):** `Jana/{DsrServiceTest,LgpdEsquecerTitularToolTest}`, `PaymentGateway/{WebhookEndpointsTest,WebhookSignatureValidationTest}`, `KB/MultiTenantTraitTest`. Evitar no piloto: `ContactObserverCacheInvalidationTest` (corruptor E zona quente), `NfeInutilizacaoServiceTest`/FSM (médio risco).

## 4. O que falta no plano

- **US-GOV-019** (p1): bug #1 ChannelUserAccess **done** (#2648). **6 abertos:** CSAT (`InboxController::updateStatus:1042-71` não dispara `DispatchCsatJob`), Vestuario `DataController` (ADR 0024), WithoutGlobalScopes-comments (`KbCorpusBuilder`/`TituloAutoService`/`NfeService`), NFSe `spanBiz` (`NfseEmissaoService:198`), DESIGN.md link quebrado, PhpunitTestAnnotation (`@test`→`#[Test]`). **91 quarentena**: mecanismo `legacy-quarantine` existe e morde (`foundation-ratchet.mjs:31/115`), mas **0 testes aplicados**. **11 unclear** = perguntas Wagner.
- **US-GOV-020** (p0): decisão re-landar Frente C+revert A.2 vs aceitar grants como provisionamento documentado.
- **Lane C**: só falta bug C (`SellsOnda5PolishTest:65` strpos — teste stale, apontar pro `SellsCockpitAggregator`, não bug de produto).
- **Lane D**: bloqueada até re-run limpo pós-isolamento (último run mediu 1928 com grants Frente C live — floor não caiu, confirma harness ≠ lever).

## Notas de precisão (anti-engano)

- Migration não-commitada `2026_06_13_120000_enforce_single_active_channel_user_access.php` no working tree **NÃO é duplicata** — é a worktree `frosty-greider-83ab2f` atrás de main; #2648 já landou idêntica.
- `ADR 0101` citado em vários frontmatters como "testes MySQL real não sqlite" **não existe com esse conteúdo** — o 0101 real é "biz=1 nunca cliente". Política MySQL-real vive em `.github/actions/pest-mysql-setup` + `ct100-fullsuite.sh`, não num ADR.
- `foundation-ratchet` (FV-Q1) penaliza `RefreshDatabase` (que seria o padrão correto pós-fix) — revisar se Fase 5 escolher `RefreshDatabase`.

## Sequência recomendada

1. **Decidir US-GOV-020** (re-landar branch `sdd/fc-trigger-definer-privilege` — grants + revert A.2; mínimo: tirar A.2 do main). Atualizar SPEC stale.
2. **Isolamento era-sqlite** (lever real): piloto vs thread-all sobre os ~19 corruptores; medir.
3. **Aplicar Lanes A/B** (quarentena ~178+91, separando test-FIX rápido de quarentena-cega; NÃO tocar guards de incidente WhatsApp).
4. **Lane C bug C** (strpos stale).
5. **Re-rodar nightly** (gate Wagner) + medir novo floor (interseção ≥2 runs).
6. **Lane D** (fan-out) só após baseline limpo.
7. **6 bugs abertos US-GOV-019** (1 PR cada) + 11 unclear; marcar checkbox bug #1 no SPEC (gap doc↔main).

## Bugs de produto US-GOV-019 entregues (a)

- **CSAT** → #2672 **MERGED** (17:58). `InboxController::updateStatus` não enfileirava `DispatchCsatJob` em open→resolved; +21 linhas com guarda de transição + idempotência. Tier 0 ok (job carrega tenant explícito).
- **Vestuario DataController** → #2673 **MERGED** (18:01, via override admin com `Pest Vestuario` vermelho). Cria `Modules/Vestuario/Http/Controllers/DataController.php` (entrada sidebar "Vestuário" grupo `vender`, ADR 0180/0024).

## Achado sistêmico: redação Tier-0 corrompeu assertions de teste

Investigando por que `Pest Vestuario` falhava no #2673, descobrimos que um processo de redação Tier-0 trocou **literais monetários reais de fixture pelo token `[redacted Tier 0]` dentro de arquivos `.php`** → assertion garantidamente vermelha. Confirmado em `W27EtiquetaGradeTest:109` (`R$ 89,90`→token) e `UsVest020...:228` (`R$ 49,90`→token), ambos com `business_id=4` (cliente real) de brinde.

- **Mecanismo:** `fd96258ae` ("restaura codebase #2413") restaurou 14.969 arquivos do tree de #2412 **já redactado** → re-propagou os tokens. Raiz ainda anterior (#2279 já trazia). **Não é hook vivo — é restore de snapshot redactado.** 0 versões limpas em qualquer ref (redação na working tree antes do 1º commit).
- **Raio real pequeno:** ~115 ocorrências / ~42 arquivos, mas **maioria cosmética** (comentário, título de `it()`, msg de assert, round-trip self-consistente) → ficam VERDES. Só quebram floor as que estão em **posição de assertion vs fixture intacta**.
- **Corrupção 🔴 real fora Vestuario = só 3 arquivos:** `ChatCopilotoAgentContextoNegocioTest` (6) + `RecurringBilling/InvoiceGeneratorServiceTest` (1) → **#2675** (auto-merge armado). `NumUfHeuristicPtBRTest` (4) → **2 linhas ambíguas, decisão Wagner** — **é exatamente o "unclear #11" do US-GOV-019** (agora explicado: corrupção de redação).
- **Impacto no floor:** modesto (~7 assertions / 3-4 testes). **Confirma que o lever grande do floor continua sendo os ~19 corruptores era-sqlite** (isolamento), não isto.
- Whatsapp/Tests: **0 ocorrências** → sem colisão.
- **Cosmético pendente (opcional):** token `[redacted Tier 0]` em comentários de produto (`app/Utils/Util.php`, `config/financeiro.php`, templates NfeBrasil, `EtiquetaTagService:263` docblock) — suja a base, cleanup separado.

## PRs desta sessão

| PR | O quê | Estado |
|---|---|---|
| #2646/47/49/52 | quick-wins US-GOV-018 re-triage | MERGED |
| #2664 | charters SDD Semana-0 + fix guard vocab OficinaAuto | MERGED |
| #2672 | CSAT DispatchCsatJob | MERGED |
| #2673 | Vestuario DataController | MERGED |
| #2674 | restaura W27/UsVest020 corrompidos + biz4→1 | MERGED |
| #2675 | restaura Copiloto + RecurringBilling corrompidos (7 assertions) | auto-merge armado |

## Backlog US-GOV-019 — 4 bugs de produto + higiene Tier-0 (rodada 2)

| PR | O quê | Estado |
|---|---|---|
| #2677 | DESIGN.md link quebrado (`preference_persistent_layouts.md` deletado no #2383) → repoint AppShellV2 | MERGED |
| #2678 | NFSe `OtelHelper::spanBiz('nfse.cancelar')` (Wave28) — módulo é `NFSe` não NfeBrasil; fix PHPStan (`$extras` array, não int) | auto-merge armado |
| #2679 | WithoutGlobalScopes `// SUPERADMIN:` nos 3 arquivos (KbCorpusBuilder, TituloAutoService, NfeService) | MERGED |
| #2680 | `@test`→`#[Test]` em 8 arquivos + `// pii-allowlist` nos vetores CPF/CNPJ do CpfCnpjTest | MERGED |
| #2681 | hardening regex do BusinessIdGuard (chave prefixada + `$business_id=` + builder) + biz4→1 em OtlpHttp/Metricas/McpAuthHealth | MERGED |

## Achados novos (decisão/escopo futuro)

- ⚠️ **`WithoutGlobalScopesCommentGuardTest` tem ~89 violações pré-existentes** além dos 3 arquivos do #2679 (PaymentGateway ~25, Whatsapp ~16, OficinaAuto ~10, KB ~12, NfeBrasil ~8, Superadmin ~6, ComVis ~6, Jana). US-GOV-019 bug #4 é MUITO maior que a triage dizia. **E o guard NÃO está plugado em nenhum workflow CI** ("armado mas não plugado") — vale fatiar por módulo + plugar no CI depois.
- ⚠️ **`BusinessIdGuardTest` (Tier-0) também não roda em CI** (nenhum workflow toca `tests/Unit/Guards`). #2681 endureceu o guard, mas a enforcement real depende de plugá-lo.
- **`TransactionBuilder.php:29` `->business(4)`** = falso-positivo (está em docblock; entry points usam biz=1). Não é violação. Opcional: normalizar exemplo da docstring.
- **Classe extra de biz=4 deliberadamente fora do regex** (pra não quebrar golden-sets de eval Jana): SQL-string + arg posicional em `MeilisearchDriverHybridTest:89`, `MeilisearchDriverPhase2Test:298/302`, `MemoriaContratoTest:60` (com inconsistências input-biz1 vs assert-biz4). PR separado se quiser higienizar.
- **`NumUfHeuristicPtBRTest` L83/L85** (unclear #11 US-GOV-019): 2 valores de dataset com formato ambíguo — não recuperados (não chutar). Decisão Wagner: arqueologia git mais funda OU quarentena.

## (c) este doc.
<!-- schema-allowlist: salvo de feat/governance-ds-rollout-ledger (branch shallow-orfanada 2026-06-20); output de subagente/legacy, schema estrito de secao nao se aplica -->
