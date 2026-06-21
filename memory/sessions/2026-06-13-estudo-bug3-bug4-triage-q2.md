---
date: "2026-06-13"
topic: "Estudo de causa-raiz dos 2 bugs §3 da triage Q2 que NÃO foram consertados (LC-bug-3 Sells strpos + LC-bug-4 FSM fail-secure) — ambos mal diagnosticados pela triage; código de produto está correto nos dois"
authors: [W, C]
related_adrs: ["0129-fsm-pipeline-canonico", "0265-fio-usavel-permission-module-level", "0101-testes-mysql-real-nao-sqlite"]
prs: ["2668", "2669"]
---

# Estudo — LC-bug-3 e LC-bug-4 da triage Q2 (os 2 que não consertei)

> Continuação da varredura §3 da [triage Q2](2026-06-13-sdd-f2b-triage-q2.md). LC-bug-1 (GLOB_BRACE) e LC-bug-2 (`$i_`) já mergeados (#2668/#2669). Wagner: "pode estudar" — ir fundo nos 2 parados, sem mexer em código.

## TL;DR

Os dois "bugs de produto confirmados" da §3 **não são bug de produto**. A triage (parser de stacktrace JUnit) errou a natureza de ambos. Código de produto está correto nos dois. As falhas são **teste defasado** — em um caso por refactor (método movido), no outro por mensagem reescrita.

---

## LC-bug-4 — FSM fail-secure: código CORRETO, falha é mensagem stale no teste

**Triage dizia:** "FSM transição crítica sem role NÃO bloqueia (UnauthorizedActionException not thrown — fail-secure quebrado) → 2 fails — prioridade de segurança."

**Realidade (traçado caminho a caminho):**

1. **`is_critical` persiste no teste.** Migration `2026_05_12_010001_add_is_critical_to_sale_stage_actions.php` cria `boolean('is_critical')->default(false)` (idempotente, sqlite-safe — `->after()` é ignorado no sqlite, não quebra). Model `SaleStageAction`: `$guarded = ['id']` (fillable) + `$casts['is_critical'] = 'bool'`. ✓
2. **`StageActionPolicy::grantsByPermission` é fail-SECURE pra subject desconhecido.** O allowlist `SUBJECT_UPDATE_PERMISSION` só tem `ServiceOrder`. O subject do teste é `FsmCriticalTestSubject` → `$permission === null` → **retorna `false`**. Não há fail-open. ✓
3. **`ExecuteStageActionService::execute()` linha 93:** `! $grantedByPermission (true) && empty($roleNames) (true) && ($action->is_critical ?? false) (true)` → **lança `UnauthorizedActionException`.** O fail-secure FUNCIONA. ✓

**Por que o spec #1 falha então:** a asserção é `->toThrow(UnauthorizedActionException::class, 'crítica e exige role explícita')`. A exception É lançada, mas a mensagem real é `"Action crítica '...' exige role configurada em sale_stage_action_roles..."`. O substring esperado (`'crítica e exige role explícita'`) é de uma versão ANTERIOR da mensagem — o teste predata a reescrita (US-SELL-031) e o short-circuit `grantsByPermission` (ADR 0265). **Teste stale, não furo.**

**Specs que passam (verificado por leitura):** #2 (não-crítico, não lança), #3 (crítico+role, user sem role → lança no check de role), #4 (happy path), #5 (mensagem cita `cancelar_venda`/`crítica`/`sale_stage_action_roles` — todos presentes), #6 (cross-tenant biz=99 → lança 'Cross-tenant').

**O "2 fails" da triage:** minha leitura fecha só **1 fail certo** (spec #1, mensagem). O 2º provavelmente é artefato de env (uma das migrations globbed `2026_05_11_12*_create_sale_*.php` com DDL MySQL-only no sqlite vazio) — **confirmar no re-run limpo**, não é segurança.

**Resolução segura (1 linha, quando alguém puder rodar):** alinhar o substring do spec #1 ao texto atual — trocar `'crítica e exige role explícita'` por `'exige role configurada'` (ou um trecho menos frágil tipo `'fail-secure US-SELL-031'`). NÃO tocar código de produto — ele está certo.

**Por que não fiz agora:** não dá pra confirmar sem rodar se é só a mensagem ou também env; e mexer às cegas em arquivo de teste de segurança sem a suíte é arriscar trocar um vermelho por outro. Vai junto do re-run.

---

## LC-bug-3 — Sells `strpos`: teste de snapshot estrutural defasado por refactor

**Triage dizia:** "strpos() offset=false em SellsOnda5PolishTest:65 — helper buildCoworkAggregates (Tier 0) → 1 fail. Bug de produto."

**Realidade:** `strpos($source, 'protected function buildCoworkAggregates(')` retorna `false` porque o método foi **extraído** de `SellController` pro `app/Services/Sells/SellsCockpitAggregator.php` (o próprio `SellController.php:837` tem o comentário "buildCoworkAggregates() extraído para App\Services\Sells\SellsCockpitAggregator"). O `tests/Feature/Sells/SellsOnda5PolishTest.php` ainda testa a localização ANTIGA (linhas 44-77) via string-match no source do controller. `strpos→false` é SINTOMA do método ter mudado de casa, não bug. **Não é bug de produto** — o wiring real existe (`SellController.php:678` faz `$cockpitAggregator->buildCoworkAggregates($business_id)` via `Inertia::defer`).

**Natureza:** é teste de snapshot estrutural via `file_get_contents` + `assertString` — exatamente o anti-padrão que a triage mandou QUARENTENAR no batch Q-A (130 testes Sells superseded). Só não estava na lista nominal do Q-A.

**Resolução (2 opções, decisão do time):**
- **(a) Quarentena** no batch Q-A coordenado — quando o mecanismo `legacy-quarantine` existir (hoje **0 testes usam** e nenhum script lê o grupo; o batch Q-A ainda não landou). Não fazer solo agora = não inventar convenção.
- **(b) Rewrite** re-apontando as assertions pro `SellsCockpitAggregator` (`public function buildCoworkAggregates(int $businessId): array`) + ajustar a asserção do `index()` defer. Restaura cobertura real, mas exige rodar a suíte pra não deixar vermelho.

**Recomendação:** (a) via Q-A — é teste estrutural de baixo valor; rewrite só se quiserem preservar a checagem Tier 0, e com a suíte rodando.

---

## Meta-lição

Dos 5 "bugs de produto confirmados" da §3 da triage Q2:
- **2 eram reais e simples** → consertados (#2668 GLOB_BRACE platform, #2669 `$i_` typo).
- **3 eram health-command registration** → já em PR pelo time (#2649/#2647/#2646).
- **2 NÃO eram bug de produto** → teste defasado (LC-bug-3 refactor, LC-bug-4 mensagem) — código de produto correto nos dois.

A triage por stacktrace JUnit é boa pra AGRUPAR e PRIORIZAR, mas erra a NATUREZA do problema com frequência (sugeriu `\GLOB_BRACE` que não conserta; chamou de "fail-secure quebrado" código que está certo). **Todo "bug confirmado" da triage precisa de leitura do código antes do fix** — senão troca vermelho por vermelho, ou pior, mexe em código de segurança que está correto.
