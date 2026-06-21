---
date: "2026-06-13"
topic: "Prompts paralelos do burn-down Fase 2b SDD — corrigidos pela triage Q2 (75% das 1.521 falhas é 1 bug de env). Lane 0 serial (harness C1) + Lanes A/B/C paralelas agora + Lane D após re-run"
authors: [W, C]
related_adrs: ["0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0101-testes-mysql-real-nao-sqlite", "0062-separacao-runtime-hostinger-ct100"]
prs: []
---

# Burn-down Fase 2b — prompts paralelos pós-triage Q2

> Base: [triage Q2](2026-06-13-sdd-f2b-triage-q2.md) (run nightly `50db892`, 1.521 falhas, ~88% não-bug-de-produto). **Corrige** o "B1/B2/B3 por módulo" do [plano-mãe](2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md) §4 — agora que se sabe que ~75% é 1 bug de env, paralelizar por módulo seria caçar fantasma.

## DAG corrigido (por que NÃO é "5 agents por módulo")

```
Lane 0 (SERIAL, 1 dono) ── C1+C2+C3 harness ──┐
                                               ├──► Wagner re-roda nightly ──► Lane D (fan-out grande)
Lane A (paralela já) ── Quarentena Q-A sells ──┤
Lane B (paralela já) ── Quarentena Q-B canon ──┤
Lane C (paralela já) ── 4 bugs de produto ─────┘
```

- **Lane 0 zera ~1.050 falhas (61%) com 3-4 commits de harness** — é o maior ROI; NÃO paralelizável (mexe no env compartilhado). Tem que landar antes do re-run.
- **Lanes A/B/C rodam AGORA em paralelo com a Lane 0** (arquivos disjuntos: A=testes Sells, B=testes canon/DS, C=código de produto, 0=`ct100-fullsuite.sh`).
- **Lane D (CONVERT 37 + seeds + loader-blockers + re-triage INVESTIGAR) ESPERA o re-run** — senão caça fantasma de env. É aí que entram os "até 5 Sonnet, áreas disjuntas".

Regra comum a todas: re-derive os números de `origin/main` antes de afirmar · 1 PR = 1 intent ≤300 linhas · conventional commit `Refs: SDD F2b <LANE>` · PT-BR · testes só no CT 100 (não local) · não tocar arquivo de outra lane.

---

## Lane 0 — Harness C1+C2+C3 (SERIAL · 1 dono · modelo: Opus — estado compartilhado)

```
Você é o ÚNICO dono da Lane 0 do burn-down Fase 2b SDD do oimpresso (estado compartilhado — NÃO há
sessão irmã nesta lane). Base: memory/sessions/2026-06-13-sdd-f2b-triage-q2.md.

PROBLEMA-MÃE (maior ROI da fase): o nightly "MySQL real" do CT 100 (script scripts/tests/ct100-fullsuite.sh)
na verdade roda contra SQLite :memory: VAZIO, porque phpunit.xml linhas 72-73 forçam DB_CONNECTION=sqlite +
DB_DATABASE=:memory:, e o <env> do PHPUnit é aplicado ANTES do Laravel ler o .env que o script grava
(Dotenv não sobrescreve var já setada). Isso gera ~880 falhas "no such table/column" (C1) + ~100 da DDL
MySQL-only `ALTER TABLE transactions MODIFY COLUMN type ENUM(...)` que aborta as migrations no sqlite (C2).

OBJETIVO:
- C1: fazer o NIGHTLY rodar MySQL real SEM tocar o phpunit.xml global (sqlite é correto no CI rápido Unit).
  Neutralizar o override sqlite SÓ no nightly — ex: phpunit override file dedicado, ou `--no-configuration`/`-d`,
  ou passar DB_* como env real ao `docker run` de forma que vença o <env>. ~2 linhas no ct100-fullsuite.sh.
- C2: confirmar que com MySQL real a DDL ENUM/SHOW COLUMNS deixa de abortar (mesmo fix do C1).
- C3: bootar a app nos testes Unit tocados (~70) que falham por facade/session/db.schema sem container —
  `uses(Tests\TestCase::class)` nos Unit afetados OU bootar providers no harness Unit.

PODE TOCAR (só esses): scripts/tests/ct100-fullsuite.sh · um phpunit override file novo do nightly (NÃO o
phpunit.xml global) · a config do workflow do nightly · os arquivos Unit do C3 (só adicionar uses(TestCase)).
PROIBIDO: phpunit.xml global (não mude as linhas 72-73 — sqlite é legítimo no CI Unit rápido), qualquer
teste de negócio, e arquivos das Lanes A/B/C. NÃO rode a suíte localmente (CT 100 only).

Ao terminar: abra o PR e SINALIZE que o re-run do nightly é o gate (Wagner dispara). Esperado pós-fix:
de ~1.521 para ~150-300. Refs: SDD F2b L0-harness.
```

---

## Lane A — Quarentena Q-A · Snapshot UI Sells superseded (130 fails · modelo: Sonnet)

```
Você está na Lane A (paralela) do burn-down Fase 2b SDD do oimpresso. Base:
memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A.

CONTEXTO: ~130 falhas são testes que leem arquivos .tsx por STRING de UI já refatorada (componentes
deletados/fundidos em SellsTabelaUnificada/SellsTabsVisao; alguns usam path /workspace/ que nem existe no
CT 100). São snapshot estrutural superseded, não bug — devem ser quarentenados, não "consertados".

OBJETIVO: marcar `@group legacy-quarantine` (com RAZÃO no docblock + link pra esta triage) nestes testes:
SellsGroupByTest, SellsBulkActionsTest, SellsTabsViewModeTest, SellsTotalsTest, SellsIndexDateFieldTest,
SellsStatusProducaoTest, SellsAgrupadaTest, SellsEditCoworkTest, SellsShowCoworkTest,
SellsCommissionColumnTest, SaleSheetComponentTest, Modules/Sells/GradeAvancadaToggleTest (TDD US-SELL-016/017
não implementada). RE-CONFIRME no repo quais ainda falham por esse motivo antes de quarentenar.

PODE TOCAR (só esses): os arquivos de teste Sells listados acima (só a anotação @group + docblock de razão) ·
o baseline do foundation-ratchet de quarentena SE o gate bloquear o PR (n_quarantine vai SUBIR nesta janela —
é esperado; a catraca "só diminui" vira required só depois do R1).
PROIBIDO: código de produto Sells, arquivos das Lanes 0/B/C. Não "conserte" a asserção — quarentena com razão.
Refs: SDD F2b LA-quarentena-sells.
```

---

## Lane B — Quarentena Q-B · Canon-source / Design System (48 fails · modelo: Sonnet)

```
Você está na Lane B (paralela) do burn-down Fase 2b SDD do oimpresso. Base:
memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-B.

CONTEXTO: ~48 falhas são asserts estáticos de Design System / canon-source contra uma fonte-da-verdade
MÓVEL (ratchets R-DS-*, frontmatter ADR/skill, links DESIGN.md). Quarentenar com razão.

OBJETIVO: marcar `@group legacy-quarantine` (razão + link pra triage) nestes: DesignSystemAuditTest,
Design/CockpitPattern/Typography/EntryPointConformanceTest, Memory/AdrFrontmatterTest,
NfeBrasil/EmissaoManualTest, Crm/Wave18SaturationTest, Officeimpresso/Wave27Polish+LgpdComplianceTest,
Cliente/{SefazInvariantes,ClienteDrawerTabs,ClienteDrawerRows,DocumentsTab,Wave1Index}Test,
Whatsapp/{IncidentCrossContact20260514,ContactObserverCacheInvalidation}Test.

⚠️ NÃO QUARENTENAR os regression-guards de incidente WhatsApp recentes com dono: R-WA-INCIDENT-CONV-*,
AUTH-DRIFT-CONV-*. Eles ficam em INVESTIGAR. RE-CONFIRME cada arquivo antes.

PODE TOCAR (só esses): os arquivos de teste listados acima (só @group + docblock) · o baseline do
foundation-ratchet SE bloquear (quarantine sobe nesta janela — esperado).
PROIBIDO: os guards de incidente WhatsApp, qualquer código de produto, arquivos das Lanes 0/A/C.
Refs: SDD F2b LB-quarentena-canon.
```

---

## Lane C — Bugs de produto CONFIRMADOS (modelo: Sonnet · 1 PR por bug)

```
Você está na Lane C (paralela) do burn-down Fase 2b SDD do oimpresso. Base:
memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §3. São bugs de produto REAIS confirmados (não fantasma de
env) — seguros de consertar agora. 1 PR por bug.

ATENÇÃO: ads:health (#2649), superadmin:health (#2647), macro_variant_id fillable (#2646) JÁ TÊM PR ABERTO —
NÃO refaça. Os 4 abaixo ainda não:
1. GLOB_BRACE sem leading-backslash em Modules/Governance/Services/ScopedScorecardEvaluator.php:466/548
   (constante inexistente nesse namespace → 6 fails do Module Grade v4). Usar \GLOB_BRACE ou import correto.
2. $i_ Undefined variable + Undefined property em AuthStateDriftCheck command (NullDriver em Services\Drivers)
   → 11 fails.
3. strpos() com offset=false em SellsOnda5PolishTest:65 — helper buildCoworkAggregates (Tier 0) → 1 fail.
4. 🔒 SEGURANÇA: FSM — transição crítica sem role NÃO bloqueia (UnauthorizedActionException não é lançado;
   fail-secure quebrado) → 2 fails. PRIORIDADE; PR isolado e bem descrito pra revisão cuidadosa.

PODE TOCAR: o código de produto de CADA bug + o teste que o cobre (1 PR cada, área disjunta).
PROIBIDO: harness/phpunit (Lane 0), testes de quarentena (Lanes A/B), e os bugs que já têm PR.
RE-CONFIRME cada um no repo (pode já ter sido pego). Refs: SDD F2b LC-bug-<n>. Não rode suíte local (CT 100).
```

---

## Lane D — ESPERA o re-run (NÃO disparar ainda)

Só depois da Lane 0 landar + Wagner re-rodar o nightly (esperado ~150-300 falhas reais). Aí sim abre o
fan-out "até 5 Sonnet, áreas disjuntas":
- **CONVERT batch (37):** KB `handle()` via container + `toThrow` concreto · `@dataProvider`→`#[DataProvider]` ·
  `pest()->extend(TestCase)` · Sells sqlite→DatabaseTransactions.
- **Seeds residuais FIX-SETUP (<50):** PaymentGateway `config_json` CNAB + APP_KEY + re-seed · KB
  `Schema::create('activity_log')` no kbBootstrapSchema + user 99/42.
- **Loader-blockers (4):** ConsumirEstoqueAuditTest, SaleJourneyGatingTest, SellsCreateVehicleGatingTest,
  VeiculoNaVendaSchemaTest.
- **Re-triar os INVESTIGAR (~206) contra baseline limpo** — só aí sobram os ~80-100 bugs de produto reais.

Disparar a Lane D antes do re-run = caçar fantasma de env. Por isso ela espera.
<!-- schema-allowlist: salvo de feat/governance-ds-rollout-ledger (branch shallow-orfanada 2026-06-20); output de subagente/legacy, schema estrito de secao nao se aplica -->
