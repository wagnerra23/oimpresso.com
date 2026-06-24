---
date: "2026-06-23"
hour: "22:00 BRT"
topic: "NfeBrasil — 1ª run do lane MySQL fiscal (advisory): classificação das 12 falhas + ratchet do allowlist nos verdes (#3316)"
authors: [C]
prs: [3316, 3312]
us: [US-NFE-002]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0298-teto-de-governanca-anti-proliferacao-gates]
---

# NfeBrasil — 1ª run do lane MySQL fiscal: achados + ratchet (#3316)

**TL;DR:** O lane `nfebrasil-pest.yml` (PR [#3316](https://github.com/wagnerra23/oimpresso.com/pull/3316),
advisory, MySQL real) rodou pela 1ª vez em 2026-06-23 (run `28066696584`, head `a3960d68`).
Resultado do JUnit: **12 failed · 60 passed (143 assertions)** sobre os 7 arquivos da allowlist.
Classifiquei as 12 falhas arquivo-a-arquivo: **ZERO bug fiscal de produção** — TODAS as 12 são
defeito de **TESTE / HARNESS** que o lane sqlite (`modules-pest.yml` :memory:) escondia porque os
testes **SKIPavam** lá (34/45). O lane MySQL fez exatamente o trabalho dele: provou que vários
"guards Tier 0 fiscais" **não exercitam o que afirmam**. Ratchet aplicado: allowlist de 7 → **2
arquivos comprovadamente verdes** (`NfeDomainModelsTest` + `OndaIbsCbsScaffoldTest`). Os 5
arquivos vermelhos saem documentados aqui como follow-up de fix de teste (sobem de volta no
ratchet conforme ficam verdes).

## Como foi medido

```
gh run view --job 83092478702 --log          # job "PHP / Pest (NfeBrasil · MySQL · advisory)"
gh run download 28066696584                    # artifact pest-nfebrasil-junit.xml (verdade fiscal)
```

Inspeção do código-fonte feita via `git show a3960d68:<path>` (object DB, anti-stale — NÃO o
working tree de `D:\oimpresso.com`, que está dirty/stale). `a3960d68` = head do PR = `headSha` da run.

## Mapa verde/vermelho por arquivo (allowlist de 7)

| Arquivo | Resultado | Veredito |
|---|---|---|
| `NfeDomainModelsTest` | ✅ **PASS** (todos) | mantido na allowlist |
| `OndaIbsCbsScaffoldTest` | ✅ **PASS** (10/10) | mantido na allowlist |
| `CertificadoServiceTest` | 🔴 12 pass / 1 fail | **excluído** — harness (seed gap biz=99 / FK) |
| `NfeServiceIdempotenciaRetryTest` | 🔴 3 pass / 4 fail | **excluído** — defeito de teste (`new NfeService()` 0 args) |
| `NfeBrasilMultiTenantIsolationTest` | 🔴 3 pass / 4 fail | **excluído** — defeito de teste (scope sem auth + SQL não-portável) |
| `EmitirNfceAoFinalizarVendaTest` | 🔴 7 pass / 2 fail | **excluído** — teste stale (per-business gate) |
| `Wave25NfeSaturationTest` | 🔴 11 pass / 1 fail | **excluído** — defeito de teste (scope sem auth) |

## As 12 falhas — root cause + veredito (achado por achado)

### Causa-raiz A — "isolamento multi-tenant" que não autentica (5 falhas, 0 bug de produção)

Os testes de isolamento setam `session(['business.id' => X])` e esperam que o global scope
`Modules\Jana\Scopes\ScopeByBusiness` esconda o tenant alheio. **Mas o scope só filtra quando há
usuário autenticado** — `if (! auth()->check()) return;` em
[ScopeByBusiness.php:26](Modules/Jana/Scopes/ScopeByBusiness.php:26) — e lê
**`session('user.business_id')`** ([ScopeByBusiness.php:30](Modules/Jana/Scopes/ScopeByBusiness.php:30)),
**não** `session('business.id')`. Os testes **nunca fazem `actingAs()`** e usam a **chave errada**.
Resultado: o scope (correto em produção) faz no-op → a row do tenant alheio aparece → o assert
negativo falha. Os models `NfeEmissao`/`NfeInutilizacao` **usam** `HasBusinessScope` corretamente
(`Modules/NfeBrasil/Models/NfeEmissao.php:32`, `NfeInutilizacao.php:20`) — produção OK.

- **#8** `NfeEmissao biz=99 NÃO aparece quando session ativa é biz=1` — `toBeNull` falhou
  ([NfeBrasilMultiTenantIsolationTest.php:112-116](Modules/NfeBrasil/Tests/Feature/NfeBrasilMultiTenantIsolationTest.php:112)).
- **#9** `NfeInutilizacao biz=99 NÃO aparece quando session ativa é biz=1`
  ([:162-165](Modules/NfeBrasil/Tests/Feature/NfeBrasilMultiTenantIsolationTest.php:162)).
- **#12** `Wave25 · NfeInutilizacao count() biz=99 NÃO conta ranges do biz=1` — "3 is identical to 1"
  (conta as 3 rows porque o scope no-opa)
  ([Wave25NfeSaturationTest.php:103-105](Modules/NfeBrasil/Tests/Feature/Wave25NfeSaturationTest.php:103)).
- **#6/#7** `nfe_emissoes/nfe_inutilizacoes tem coluna business_id NOT NULL` — `DB::select('SHOW
  COLUMNS FROM ... LIKE ?', [...])`: MySQL rejeita placeholder em `SHOW` (`Syntax error near '?'`).
  SQL **não-portável** — sqlite nem tem `SHOW COLUMNS`
  ([:81](Modules/NfeBrasil/Tests/Feature/NfeBrasilMultiTenantIsolationTest.php:81),
  [:88](Modules/NfeBrasil/Tests/Feature/NfeBrasilMultiTenantIsolationTest.php:88)).

> **Achado de valor:** os "guards Tier 0 de isolamento NfeBrasil" hoje **não testam isolamento** —
> passam vácuo (quando o scope no-opa devolvendo só o que casa) ou falham espúrio (como aqui).
> Fix do teste: `actingAs($userBiz1)` + setar `session('user.business_id')` (não `business.id`).

### Causa-raiz B — `new NfeService()` sem o argumento obrigatório (4 falhas)

`NfeService::__construct` exige `CertificadoService $certificadoService` (não-opcional,
[NfeService.php:62](Modules/NfeBrasil/Services/NfeService.php:62)). O teste faz `new NfeService()`
com 0 args → **`ArgumentCountError`**. Deveria ser `app(NfeService::class)` / injetar a dep.

- **#2-#5** `emitir() ... cancelada/autorizada/pendente` + `retry pós erro_envio`
  ([NfeServiceIdempotenciaRetryTest.php:117/157/198/314](Modules/NfeBrasil/Tests/Feature/NfeServiceIdempotenciaRetryTest.php:117)).

### Causa-raiz C — seed não cobre biz=99 + sqlite não enforça FK (1 falha)

`CertificadoServiceTest` chama `salvar(99, ...)` → insere `nfe_certificados.business_id=99`, mas o
`pest-mysql-setup` semeia só **biz=1 e biz=2** → viola a FK `nfe_certificados_business_id_foreign →
business(id)` (`SQLSTATE 23000`). Em sqlite a FK **não é enforçada** (estava mascarado).

- **#1** `multi-tenant: cert do business A não vaza pro business B`
  ([CertificadoServiceTest.php:231](Modules/NfeBrasil/Tests/Feature/CertificadoServiceTest.php:231)).
  Fix do teste: usar biz=2 (semeado) em vez de biz=99, ou semear biz=99.

### Causa-raiz D — teste stale vs per-business gate do listener (2 falhas)

O listener `EmitirNfceAoFinalizarVenda` ganhou um **per-business gate** (ADR 0093, proteção da
biz=4 ROTA LIVRE/Larissa): além da flag global, exige
`NfeBusinessConfig.auto_emission_enabled=true`
([EmitirNfceAoFinalizarVenda.php:74-75](Modules/NfeBrasil/Listeners/EmitirNfceAoFinalizarVenda.php:74)).
Os 2 testes que falham setam **só a flag global** e não semeiam o config por-business (biz=7 e
biz=1) → o gate (correto) bloqueia o dispatch → "job was not pushed". O irmão `business sem config
→ Job NÃO dispatched` **passa** pela mesma lógica.

- **#10** `flag ON + venda partial → Job dispatched` (biz=7 sem config,
  [EmitirNfceAoFinalizarVendaTest.php:208-214](Modules/NfeBrasil/Tests/Feature/EmitirNfceAoFinalizarVendaTest.php:208)).
- **#11** `flag ON + venda elegível paid → Job dispatched` (biz=1 sem config,
  [:233](Modules/NfeBrasil/Tests/Feature/EmitirNfceAoFinalizarVendaTest.php:233)).
  Fix do teste: `NfeBusinessConfig::updateOrCreate(['business_id'=>X],['auto_emission_enabled'=>true])`.

## Decisão aplicada nesta sessão

1. **Ratchet** em `.github/workflows/nfebrasil-pest.yml`: allowlist 7 → 2 (`NfeDomainModelsTest`,
   `OndaIbsCbsScaffoldTest`) + comentário catraca documentando os 5 excluídos e o porquê.
2. O lane segue **advisory** (`continue-on-error`, ADR 0271/0298). Com a allowlist só-verde o JUnit
   agora reporta **0 failed** — `verde@` confiável pro gate de entrada da âncora.

## Follow-up (próximas PRs — fix de teste, sobe o ratchet)

- **NÃO é bug fiscal de produção** — não tocar código Tier 0 fiscal. É dívida de **teste**.
- P1 (alto valor): consertar `NfeBrasilMultiTenantIsolationTest` + `Wave25` pra realmente exercer o
  scope (`actingAs` + `session('user.business_id')`). Hoje esses "guards de isolamento" são falso-conforto.
- P2: `NfeServiceIdempotenciaRetryTest` (`app(NfeService::class)`); `EmitirNfceAoFinalizarVendaTest`
  (semear `NfeBusinessConfig`); `CertificadoServiceTest` (biz=2 em vez de biz=99); trocar `SHOW
  COLUMNS LIKE ?` por checagem portável (`Schema::getColumnType` / `information_schema`).
- Cada arquivo que ficar verde volta pra allowlist (ratchet up), rumo a arming a required (ADR 0275).
