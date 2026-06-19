---
date: "2026-06-13"
topic: "Triage Q2 da Fase 2b SDD — as 1.521 falhas do nightly CT 100 (run 50db892) são ~75% UM bug de ambiente (phpunit.xml força sqlite:memory), não 1.521 bugs. 10 threads classificaram por módulo; bugs de produto reais estimados em ~80-100."
authors: [W, C]
related_adrs: ["0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0062-separacao-runtime-hostinger-ct100", "0101-testes-mysql-real-nao-sqlite"]
prs: []
---

# Triage Q2 — Fase 2b SDD · Consolidação Executável

> **Origem:** "vai em tread" (Wagner 2026-06-13). Workflow `sdd-f2b-triage-q2`, 10 threads `audit`/Opus paralelas + 1 consolidação, READ-ONLY sobre o `junit.xml` do nightly FV-F3 no CT 100 (SSH key-based, sem senha). Plano-mãe: [2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md](2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md).
> **Descoberta que reframe a Fase 2b:** o "1º número real" que o plano dizia inexistir **já existia** — o nightly rodou 2026-06-12 10h. E o vermelho dele é majoritariamente **um defeito de harness**, não dívida de teste.

**Run:** nightly CT 100 · sha `50db892` · 10.382 testcases · **1.521 falhando** (357 fail + 1.164 errors) · 7.018 pass · 1.843 skip. Cobertura desta triage: **1.357 falhas medidas** arquivo-a-arquivo (10 clusters); resíduo ~164 é skip-overlap/não-classificado.

---

## 1. Descoberta-mãe (contradiz a premissa do run)

O nightly é anunciado como "MySQL real", mas **`phpunit.xml` linhas 72-73 forçam `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:`**. O `<env>` do PHPUnit é aplicado ANTES do Laravel carregar o `.env` que o `ct100-fullsuite.sh` grava (`DB_CONNECTION=mysql` + `mysql-schema.sql` + `migrate` + seed biz=1/biz=2) — e o Dotenv não sobrescreve var já setada. **Resultado: a suíte inteira roda contra um SQLite vazio sem schema nem seed.** Carimbado `Connection: sqlite, Database: :memory:` em **9 dos 10 clusters**.

**Smoking-gun (C2):** a migration MySQL-only `ALTER TABLE transactions MODIFY COLUMN type ENUM(...)` aborta a run de migrations no sqlite (`near "MODIFY": syntax error`), deixando o schema in-memory incompleto em cascata → `no such table: activity_log / business / users / payment_gateway_credentials / mcp_actors / ...`.

---

## 2. Quadro real (medido, não estimado)

| Cluster | Total | FIX-SETUP | CONVERT | QUARENTENA | INVESTIGAR |
|---|---:|---:|---:|---:|---:|
| whatsapp | 445 | 392 | 0 | 0 | 53 |
| longtail | 332 | 246 | 0 | 48 | 38 |
| paymentgateway | 185 | 184 | 0 | 0 | 1 |
| sells | 162 | 7 | 15 | 130 | 10 |
| feature-modules | 156 | 131 | 0 | 0 | 25 |
| financeiro | 151 | 141 | 0 | 0 | 10 |
| domain-governance | 88 | 56 | 3 | 0 | 29 |
| jana | 78 | 72 | 0 | 0 | 6 |
| ponto-ads | 65 | 53 | 3 | 0 | 9 |
| kb | 50 | 9 | 16 | 0 | 25 |
| **TOTAL agregado\*** | **1.712** | **1.291 (75%)** | **37 (2%)** | **178 (10%)** | **206 (12%)** |

\* Soma > 1.521 porque clusters se sobrepõem (ex `GradeAvancadaToggleTest` em sells+feature-modules; `SellsCobrancaChipTest` em sells+paymentgateway). Universo único ~1.357 medidas. **~88% das falhas não são bug de produto.**

---

## 3. Top causas-raiz cross-módulo

### 🔥 ROI máximo — 1 conserto de ENV resolve em massa

| # | Causa-raiz | n | Conserto |
|---|---|---:|---|
| **C1** | `phpunit.xml:72-73` força sqlite `:memory:` sobrescrevendo o MySQL real do runner. Manifesta `no such table`/`no such column` em todos os clusters. | **~880+** | **No NIGHTLY, não no phpunit.xml global** (sqlite é legítimo no CI rápido Unit). Fazer o `ct100-fullsuite.sh` passar `DB_*` como env real ao `docker run` E neutralizar o `<env>` sqlite (ex phpunit override file / `--no-configuration` + `-d` / `force`-aware). |
| **C2** | DDL MySQL-only (`ALTER…MODIFY ENUM`, `SHOW COLUMNS`) aborta migrations no sqlite. | **~100** | Mesmo fix do C1 (em MySQL real nem erra). |
| **C3** | App/container não bootado em Unit (`session`/`db.schema`/`Cache\Factory`/facade `DB`): testsuite Unit boota só `vendor/autoload.php`. | **~70** | `uses(Tests\TestCase::class)` nos Unit tocados OU bootar providers no harness. |

> **C1+C2+C3 = ~3-4 commits de harness, nenhum toca teste de negócio, zeram ~1.050 falhas (~61%).** Os 7 clusters whatsapp/paymentgateway/financeiro/feature-modules/jana/domain-governance/ponto-ads são quase-inteiramente ENV.

### Causas menores acionáveis

- **C4 (130, QUARENTENA, sells):** snapshot estrutural de UI Sells superseded (`file_get_contents` + `assertString` sobre `.tsx` deletados/markup pré-refactor KB-9.75; path `/workspace/` nem existe no CT 100).
- **C5 (48, QUARENTENA, longtail):** asserts estáticos de Design System / canon-source contra fonte-da-verdade móvel (ratchets R-DS-*, frontmatter ADR/skill, links DESIGN.md).
- **C6 (18, INVESTIGAR, kb):** 403 perm-mismatch — controllers no gate legado `copiloto.mcp.memory.manage`, testes concedem `kb.*`. Decisão de contrato (Agent A).
- **C7 (16, CONVERT, kb):** `(new $job(1))->handle()` sem DI + `toThrow(\Throwable::class)` desatualizado.
- **C8 (6, CONVERT, ponto-ads/domain-governance):** `@dataProvider` docblock ignorado no PHPUnit 11 + falta `pest()->extend(TestCase)`.

### 🐛 Bugs de produto CONFIRMADOS (conserto trivial, alto valor)

1. **`GLOB_BRACE` sem leading-backslash** em `ScopedScorecardEvaluator.php:466/548` → constante inexistente no namespace `Modules\Governance\Services`. **6 fails.** (código vivo do Module Grade v4)
2. **`ads:health` não registrado** em `AdsServiceProvider::commands([...])` — classe existe, ficou fora da lista. **3 fails.**
3. **`Undefined variable $i_`** em `AuthStateDriftCheck` command + `Undefined property …Services\Drivers` (NullDriver). **11 fails.**
4. **`strpos()` offset=false** em `SellsOnda5PolishTest:65` (helper `buildCoworkAggregates`, Tier 0). **1 fail.**
5. **FSM transição crítica sem role NÃO bloqueia** (`UnauthorizedActionException` not thrown — fail-secure quebrado). **2 fails — prioridade de segurança.**

---

## 4. Batch de QUARENTENA recomendado (~178 fails / ~28 arquivos · `@group legacy-quarantine`)

**Q-A — Snapshot UI Sells superseded (130):** testes leem `.tsx` por string de UI refatorada; 7 componentes deletados/fundidos em `SellsTabelaUnificada`/`SellsTabsVisao`. Arquivos: `SellsGroupByTest`, `SellsBulkActionsTest`, `SellsTabsViewModeTest`, `SellsTotalsTest`, `SellsIndexDateFieldTest`, `SellsStatusProducaoTest`, `SellsAgrupadaTest`, `SellsEditCoworkTest`, `SellsShowCoworkTest`, `SellsCommissionColumnTest`, `SaleSheetComponentTest`, `Modules/Sells/GradeAvancadaToggleTest` (TDD US-SELL-016/017 não implementada).

**Q-B — Canon-source / Design System contra fonte móvel (48):** `DesignSystemAuditTest`, `Design/CockpitPattern/Typography/EntryPointConformanceTest`, `Memory/AdrFrontmatterTest`, `NfeBrasil/EmissaoManualTest`, `Crm/Wave18SaturationTest`, `Officeimpresso/Wave27Polish+LgpdComplianceTest`, `Cliente/{SefazInvariantes,ClienteDrawerTabs,ClienteDrawerRows,DocumentsTab,Wave1Index}Test`, `Whatsapp/{IncidentCrossContact20260514,ContactObserverCacheInvalidation}Test`.

**Q-C — Bridges cowork nunca landados (CONFIRMAR com dev):** `Financeiro/{OndaCommentsAuditBridge,CoworkBundleIntegral,DrawerCoworkV2}Test` dependem de `public/cowork-preview/_oimpresso-bridge-{comments,audit}.js` inexistentes no checkout.

> ⚠️ **NÃO quarentenar** os regression-guards de incidente WhatsApp (`R-WA-INCIDENT-CONV-*`, `AUTH-DRIFT-CONV-*`) — recentes (mai/2026), com dono. Mantidos em INVESTIGAR.

---

## 5. Próximos passos (impacto × esforço)

### 🥇 PRIMEIRO (alto fan-out, sem dev)
1. **Fix C1** (`ct100-fullsuite.sh` neutraliza override sqlite do nightly) — ~2 linhas, zera ~880+ e resolve C2 (~100). **Maior ROI da Fase 2b.** É harness do nightly, NÃO o `phpunit.xml` global (sqlite é correto no CI rápido Unit).
2. **Fix C3** — bootar app nos Unit tocados (~70).
3. **Quarentena em massa** Q-A + Q-B (`@group legacy-quarantine`, 178 / 28 arquivos) → nightly quase-verde imediato. Confirmar Q-C.
4. **Re-rodar nightly** após 1-3. Esperado: de ~1.521 para **~150-300**. Re-triar INVESTIGAR contra baseline limpo.

### 🥈 PARALELIZA (após L0 verde, áreas disjuntas)
5. Seeds residuais FIX-SETUP (PaymentGateway `config_json` CNAB + APP_KEY + re-seed; KB `Schema::create('activity_log')` no `kbBootstrapSchema` + user_id 99/42). <50, mecânico.
6. CONVERT batch (37): KB `handle()` via container + `toThrow` concreto; `@dataProvider`→`#[DataProvider]`; `pest()->extend`; Sells sqlite→DatabaseTransactions.
7. Loader-blockers (4): `ConsumirEstoqueAuditTest`, `SaleJourneyGatingTest`, `SellsCreateVehicleGatingTest`, `VeiculoNaVendaSchemaTest`.

### 🥉 ESPERA DEV (só após nightly limpo, pra não caçar fantasma)
8. Bugs de produto confirmados (§3): `GLOB_BRACE`, `ads:health`, `$i_`/NullDriver, `strpos`, **FSM fail-secure**.
9. Decisões de contrato: KB 403 perm-mismatch (`kb.*`) · rename Copiloto→Jana.
10. Divergências numéricas (bug vs expectativa stale): câmbio 0.22 vs 0.23, InvoiceGenerator idempotência, BrasilApiService null em 404, ItauCnab nosso_numero, CharterHealthChecker 6 vs 5.

---

**TL;DR:** o nightly "vermelho" é ~75% **um bug de ambiente** (`phpunit.xml` força sqlite). **3 fixes de harness + 1 batch de quarentena (178)** levam de 1.521 falhando para a casa das centenas e revelam que os **bugs de produto reais são ~80-100, não 1.521.** Quarentena e ENV-fix primeiro; re-rodar; só então despachar dev nos INVESTIGAR sobreviventes.

**Segurança/PII:** READ-ONLY — nenhum teste/seed/CT 100 editado. Nenhum CPF/CNPJ/email/telefone/nome extraído; só estrutura (SQLSTATE, nomes de tabela/coluna, FQCN, assinaturas). Redator PII ativo nos parsers.
