---
id: requisitos-auditoria-sdd-trilha-por-registro-v1-0
slug: auditoria-sdd
title: "SDD — Trilha por-registro: leitura, investigação e reversão (domínio Auditoria)"
type: sdd
module: Auditoria
status: ativo
owner: W
version: 1.1.0
last_updated: "2026-11-09"
related_docs:
  - SPEC.md
  - BRIEFING.md
  - SUPERFICIE.md
  - UI-CATALOG.md
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
---

# SDD — Auditoria: trilha por-registro

> **Por que este doc existe:** em 2026-07-30 o módulo foi eleito *"o delete mais barato do conjunto"* e a remoção começou. [W] cortou (*"acho que auditoria deve ficar — ele registra as alterações em cada registro é super importante"* · *"não pode apagar"*) e mandou o oposto: **definir todo o escopo, todo o SDD**. A causa do erro foi documental — o `SCOPE.md` declarava o núcleo do módulo como fora de escopo. Registro do episódio: [lápide §5 em `proibicoes.md`](../../proibicoes.md).

---

## §0 — Base empírica 🖐 curado (foto datada — 2026-07-30)

Medição em produção (`u906587222_oimpresso`, via container `oimpresso-mcp` do CT 100, que aponta pro banco do Hostinger):

| Tabela | Linhas | Última escrita |
|---|---:|---|
| **`activity_log`** (fonte real) | **117.510** | **2026-07-30 11:22:14** |
| `auditoria_audit_notes` (tabela própria) | **não existe** — nem prod, nem staging, nem CT 100 | — |

**Leitura obrigatória deste número:** a tabela própria estar vazia **não** mede o módulo. Ele é **consumidor** do dado de outro dono. Medir a tabela errada foi exatamente o que quase o apagou.

Sem `CAPTERRA-FICHA.md` para este módulo — benchmark de mercado ⬜ **não-verificado**.

---

## §1 — Visão geral ⚙️ derivado

Módulo de **1 família de telas** (2 telas) que serve como interface humana da trilha por-registro do ERP inteiro.

| Rota | Método | Tela | O que responde |
|---|---|---|---|
| `/auditoria` | `AuditoriaController@index` | `Auditoria/Index` | *"o que mudou no sistema, por quem, quando"* — paginado sobre `activity_log` |
| `/auditoria/{activityId}` | `@show` | `Auditoria/Detail` | *"o que exatamente mudou neste registro"* — diff `properties.old` × `properties.attributes` |
| `/auditoria/{activityId}/revert` | `@revert` (POST) | — | Em 2026-11-09 respondia **501**: o `RevertService` não era chamado. Decisão D1 pendente ([SPEC §Estado real](SPEC.md#estado-real-medido-em-2026-11-09)) |
| `/reports/activity-log` | closure do módulo | — | redirect 301 para `/auditoria`, mantendo a querystring. A rota do módulo sobrepõe a do núcleo com a mesma URI |

**Vale para todos os verticais** — a trilha é do núcleo, não de um CNAE. O sidebar aponta via `Route::has('auditoria.index') ? … : legacy` (`AdminSidebarMenu`), padrão condicional canônico que degrada sozinho.

---

## §2 — Público-alvo e personas 🖐 curado

⬜ **Não-verificado** — não há `memory/clientes/*/personas/` para este módulo. O que se sabe por uso, não por pesquisa: o consumidor é **operacional/administrativo** (quem investiga "por que esse valor mudou") e **[W] como dono** (accountability). Persona formal é decisão [W], não derivável.

---

## §3 — Governança aplicável ⚙️ derivado

| Tier 0 que morde aqui | Como |
|---|---|
| **Multi-tenant** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) | `canRevert()` compara `activity.business_id` × `user.business_id` e **nega** na divergência. Sem isso, o undo seria vazamento cross-tenant com cara de feature. Defendido por `MultiTenantIsolationTest`. |
| **Append-only por lei** (Portaria MTP 671/2021) | O `unrevertibleRegistry()` pretende bloquear `Marcacao`. Em 2026-11-09 a chave apontava para `Modules\PontoWr2\Models\Marcacao`, classe inexistente (a real é `Modules/Ponto/Entities/Marcacao.php`), logo não casava com registro nenhum. Sem efeito hoje porque a rota responde 501. |
| **Integridade fiscal** | Mesma situação em 2026-11-09: `NfeBrasil\Models\NfeTransaction` não existia, e `TituloBaixa` existia com condição sobre a coluna `origem`, ausente da tabela. |
| **PII / LGPD** | `PiiLeakActivityLogEnforceTest` + `RevertServicePiiRedactionTest` — o log não pode vazar PII, e o revert não pode reintroduzi-la. |
| **Não apagar o módulo** | [lápide §5 `proibicoes.md`](../../proibicoes.md) 2026-07-30 — não re-propor deprecação. |

---

## §4 — Design system aplicável ⚙️ derivado

Charters existem e estão **`status: draft`** nas duas telas — `Index.charter.md` (Goal único) e `Detail.charter.md` (Goals — Features). Padrão de tela: `Index` é lista → **PT-01**; `Detail` é leitura de um registro. `ux_targets` ⬜ **não declarados** nos charters — lacuna real, não omissão deste doc.

---

## §5 — Arquitetura ⚙️ derivado

### 5.1 Visão em camadas

```
Modules/*/Models  ──trait LogsActivity──►  activity_log  (Spatie)
                                              │  117.510 linhas
                                              ▼
                              AuditEntryService (leitura paginada + detalhe)
                                              │
                    AuditoriaController ──────┼──► Inertia: Auditoria/Index · Auditoria/Detail
                                              │
                              RevertService ──┴──► RevertCheck (allow/deny + OTel span)
                                              │
                                              └──► nova Activity (o undo também é auditado)
```

**A fronteira que a v1 do SCOPE errava:** quem **emite** é o Model de cada módulo (trait); quem **lê** é aqui, e reverter é capacidade codificada aqui. O módulo não emite nada. No diagrama, o ramo `RevertService` não é alcançado pelo controller em 2026-11-09.

### 5.2 Modelo de dados (núcleo)

| Tabela | Dono | Papel aqui |
|---|---|---|
| `activity_log` | **Spatie / núcleo** | fonte única — `subject`, `causer`, `properties.old`, `properties.attributes`, `business_id` |
| `auditoria_audit_notes` | Auditoria | **inexistente em produção** — migration nunca rodou. Não é o valor do módulo. |

Colunas de identidade do causer vêm de `2026_05_10_160000_add_causer_kind_and_revert_to_activity_log` (`causer_kind` + campos de revert) — defendidas por `CauserKindMigrationTest` / `CauserKindResolverTest`.

### 5.3 Fluxos críticos

**A. Investigar** — `/auditoria` → filtro → `/auditoria/{id}` → diff old×new.

**B. Reverter (codificado no serviço, desligado da rota).** Em 2026-11-09 `POST /auditoria/{id}/revert` responde 501 e a tela não tem botão, então nenhum passo abaixo roda por HTTP. Se a rota será ligada é a decisão D1 de [W]: a ADR 0127 prevê reverter aqui; `E-AUDIT-REVERT` (delegada em 2026-10-05) diz que a Auditoria é só leitura. O que `RevertService` faz quando chamado:
1. `canRevert(Activity, User)` avalia, **nesta ordem**:
   1. `business_id` bate? senão **deny** *"Activity de outro business — nao acessivel (Tier 0)"*
   2. tem `subject_type` e `subject_id`, a classe existe e o registro ainda existe? senão **deny**
   3. o subject casa com uma entrada do `unrevertibleRegistry()` e a condição dela é verdadeira? **deny** com a razão legal/fiscal
   4. janela e permissão: `unlimited`, `any` (30 dias) ou `own` (própria ação, 24 horas). Sem nenhuma, **deny**
2. `revert()` exige razão com 10 ou mais caracteres e `properties.old` preenchido (`DomainException` se vazio).
3. Restaura `properties.old` no Model e **cria nova `Activity`** `event=reverted`, registrando quem reverteu e a razão.

`RevertCheck` emite span OTel em **ambos** os caminhos (`auditoria.revert.check.allow` / `.deny`) — negação é observável, não silenciosa.

### 5.4 Onde os dois mundos ainda não se conversam

- Charters em `draft`.
- `auditoria_audit_notes` existe como migration e **não** como tabela em produção (medido em 2026-07-30) — código e schema discordam. `AuditNote` e os Requests de notas, export e lote não têm rota.
- A UI não expõe o `unrevertibleRegistry()`: a tela não tem ação de reverter.
- `causer_kind=agent` não tem produtor em 2026-11-09: o observer espera o bind `jana.agent_run_id`, que nenhum código de produção faz.

---

## §6 — Casos de uso ⚙️ derivado + 🖐 [W] confere

> Derivados do **código** (Controller → Service → Model) e das **rotas**. Não há Blade legada nem contrapartida Delphi para esta família — a ordem-de-fonte canônica se resolve na fonte 1. A coluna Estado foi medida em 2026-11-09.

| UC | Caso | Origem | Estado |
|---|---|---|---|
| **CU-AUD-01** | Listar alterações do meu business, paginadas, sem ver as de outro | `@index` + `AuditEntryService` | Contrato: UC-AUDI-01, UC-AUDI-02 |
| **CU-AUD-02** | Ver o diff exato de uma alteração (`old` × `attributes`) | `@show` | Contrato: UC-AUDD-01, UC-AUDD-02 |
| **CU-AUD-03** | Reverter uma alteração permitida, informando razão | `RevertService::revert()` | Só no serviço: rota responde 501 (D1) |
| **CU-AUD-04** | Ser **impedido** de reverter Activity de outro business | `canRevert()` eixo 1 · `MultiTenantIsolationTest` | Só no serviço |
| **CU-AUD-05** | Ser **impedido** de reverter marcação de ponto (Portaria 671/2021) | `unrevertibleRegistry()` | Intenção: a chave aponta para classe inexistente |
| **CU-AUD-06** | Ser **impedido** de reverter NFe autorizada / baixa de título / OS | mesmo registry | Intenção: chaves ou condição sem efeito |
| **CU-AUD-07** | Ser impedido quando não há `properties.old` | `DomainException` | Só no serviço |
| **CU-AUD-08** | O undo **aparece** na trilha como nova Activity | `revert()` cria Activity | Só no serviço, sem teste do caminho de sucesso |
| **CU-AUD-09** | A trilha não vaza PII | `PiiLeakActivityLogEnforceTest` · `RevertServicePiiRedactionTest` | Testes existem |
| **CU-AUD-10** | Negação de revert é observável (span OTel) | `RevertServiceOtelSpanTest` | Teste existe |

Os contratos vigentes são `Index.casos.md` e `Detail.casos.md` (5 UCs). Reverter, export e badge de irreversível ficam no backlog deles, sem id.

---

## §7 — Requisitos não-funcionais ⚙️ derivado

- **Paginação obrigatória** — o comentário no `@index` declara `LengthAwarePaginator` *"em activity_log com índices"*; com 117k linhas e crescendo, listagem sem paginação é incidente.
- **Observabilidade** — spans `auditoria.revert.check.*` nos dois caminhos.
- **`Inertia::defer`** aplicado em `activities` (`@index`) e `activity` (`@show`).

---

## §8 — Estratégia de qualidade ⚙️ derivado

**10 arquivos de teste no módulo** — o oposto do perfil "módulo abandonado":

`AuditEntryReversibilityTest` · `AuditNoteLogsActivityTest` · `AuditoriaModuleTest` · `MultiTenantIsolationTest` · `PiiLeakActivityLogEnforceTest` · `RevertServiceOtelSpanTest` · `RevertServicePiiRedactionTest` · `SmokeRoutesTest` · `Wave18SaturationTest` · `Wave27SaturationTest`

**Mais os testes globais** em `tests/Feature/Auditoria/`, que provam a **emissão** por Model e o contrato das telas: em 2026-11-09 eram 10 arquivos (`ls tests/Feature/Auditoria`), entre eles `AuditoriaTelasContratoTest`, `ContactPiiLogsActivity`, `ProductStockLogsActivity`, `SellLinePaymentLogsActivity`, `TransactionLogsActivity`, `CauserKind*` e `RevertServiceTest`. (Estes foram classificados como *"não são do módulo"* durante a tentativa de deleção — e são justamente a prova de que a capacidade é exercida.)

**Execução, medida em 2026-11-09:** a lane `auditoria-pest.yml` executa `tests/Feature/Auditoria/`. Os 10 arquivos de `Modules/Auditoria/Tests/Feature/` não estavam em lane de PR (`node scripts/governance/test-lane-coverage.mjs`); registro no `phpunit.xml` não é execução.

**Lacuna:** o caminho de sucesso do revert e os níveis de permissão não têm teste que os exercite ([SPEC §Validação Sprint 3](SPEC.md#validação-sprint-3)).

---

## §9 — Riscos e dívidas 🖐 curado

Medido em 2026-11-09.

| # | Dívida | Severidade |
|---|---|---|
| 1 | Reverter está codificado e desligado da rota. Decisão D1 pendente | alta |
| 2 | 3 chaves do `unrevertibleRegistry()` apontam para classes inexistentes, e a condição de `TituloBaixa` lê coluna ausente. Sem efeito enquanto a rota responder 501 | alta se ligado |
| 3 | `auditoria.view` só filtrava o menu: `index` e `show` não checavam a permissão | alta |
| 4 | `causer_kind=agent` sem produtor; depende do módulo Jana fazer o bind | média |
| 5 | Charters em `draft` | média |
| 6 | `auditoria_audit_notes` — migration existe, tabela não em produção (2026-07-30). Código e schema discordam | média |
| 7 | Métricas de saúde da SPEC não existem; `auditoria:health` não é agendado | baixa |

---

## §10 — Roadmap 🖐 [W] prioriza

Feitos: `casos.md` de Index e Detail ([#8325](https://github.com/wagnerra23/oimpresso.com/pull/8325)) e `Inertia::defer` no `@index`.

1. Decidir D1 (reverter em `/auditoria` ou só leitura) e alinhar SPEC, charters e casos com a decisão
2. Promover os charters de `draft` para `live`
3. Resolver a discordância `auditoria_audit_notes` (rodar a migration ou removê-la)

---

## §11 — Referências ⚙️ derivado

- [`memory/requisitos/Auditoria/SCOPE.md`](SCOPE.md) v2.0.0 — fronteira emitir × ler
- [`SUPERFICIE.md`](SUPERFICIE.md) — inventário de arquivos e papéis (gerado)
- [`BRIEFING.md`](BRIEFING.md) · [`SPEC.md`](SPEC.md) · [`UI-CATALOG.md`](UI-CATALOG.md)
- [lápide §5 `proibicoes.md`](../../proibicoes.md) — não deprecar
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
