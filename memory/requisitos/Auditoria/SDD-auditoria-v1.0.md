---
id: requisitos-auditoria-sdd-trilha-por-registro-v1-0
slug: auditoria-sdd
title: "SDD — Trilha por-registro: leitura, investigação e reversão (domínio Auditoria)"
type: sdd
module: Auditoria
status: ativo
owner: W
version: 1.0.0
last_updated: "2026-07-30"
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
| `/auditoria/{activityId}/revert` | `@revert` (POST) | — | *"desfazer esta mudança"*, se e somente se permitido |
| `/reports/activity-log` | legacy | — | rota antiga preservada (fallback do sidebar) |

**Vale para todos os verticais** — a trilha é do núcleo, não de um CNAE. O sidebar aponta via `Route::has('auditoria.index') ? … : legacy` (`AdminSidebarMenu:775`), padrão condicional canônico que degrada sozinho.

---

## §2 — Público-alvo e personas 🖐 curado

⬜ **Não-verificado** — não há `memory/clientes/*/personas/` para este módulo. O que se sabe por uso, não por pesquisa: o consumidor é **operacional/administrativo** (quem investiga "por que esse valor mudou") e **[W] como dono** (accountability). Persona formal é decisão [W], não derivável.

---

## §3 — Governança aplicável ⚙️ derivado

| Tier 0 que morde aqui | Como |
|---|---|
| **Multi-tenant** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) | `canRevert()` compara `activity.business_id` × `user.business_id` e **nega** na divergência. Sem isso, o undo seria vazamento cross-tenant com cara de feature. Defendido por `MultiTenantIsolationTest`. |
| **Append-only por lei** (Portaria MTP 671/2021) | `Marcacao` está no `unrevertibleRegistry()` — marcação de ponto **nunca** reverte. |
| **Integridade fiscal** | `NfeTransaction` (nota autorizada na SEFAZ) e `TituloBaixa` (baixa financeira) no mesmo registry. |
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

**A fronteira que a v1 do SCOPE errava:** quem **emite** é o Model de cada módulo (trait); quem **lê e reverte** é aqui. O módulo não emite nada.

### 5.2 Modelo de dados (núcleo)

| Tabela | Dono | Papel aqui |
|---|---|---|
| `activity_log` | **Spatie / núcleo** | fonte única — `subject`, `causer`, `properties.old`, `properties.attributes`, `business_id` |
| `auditoria_audit_notes` | Auditoria | **inexistente em produção** — migration nunca rodou. Não é o valor do módulo. |

Colunas de identidade do causer vêm de `2026_05_10_160000_add_causer_kind_and_revert_to_activity_log` (`causer_kind` + campos de revert) — defendidas por `CauserKindMigrationTest` / `CauserKindResolverTest`.

### 5.3 Fluxos críticos

**A. Investigar** — `/auditoria` → filtro → `/auditoria/{id}` → diff old×new.

**B. Reverter (o fluxo que exige guarda):**
1. `canRevert(Activity, User)` avalia, **nesta ordem**:
   1. `business_id` bate? senão → **deny** *"Activity de outro business — nao acessivel (Tier 0)"*
   2. tem `subject`? senão → **deny** *"Activity sem subject"*
   3. classe do subject está no `unrevertibleRegistry()`? senão → **deny** com a razão legal/fiscal
   4. tem `properties.old`? senão → `DomainException` *"nao ha snapshot pra restaurar"*
2. Permitido → restaura `properties.old` no Model
3. **Cria nova `Activity`** registrando quem reverteu e a razão (undo auditável)

`RevertCheck` emite span OTel em **ambos** os caminhos (`auditoria.revert.check.allow` / `.deny`) — negação é observável, não silenciosa.

### 5.4 Onde os dois mundos ainda não se conversam

- Charters em `draft` sem `ux_targets`.
- `auditoria_audit_notes` existe como migration e **não** como tabela — código e schema discordam.
- ⬜ **Não medido:** se a UI expõe o `unrevertibleRegistry()` ao usuário (mostrar *por que* não dá pra reverter) ou só nega.

---

## §6 — Casos de uso ⚙️ derivado + 🖐 [W] confere

> Derivados do **código** (Controller → Service → Model) e das **rotas**. Não há Blade legada nem contrapartida Delphi para esta família — a ordem-de-fonte canônica se resolve na fonte 1. **[W] confere antes de virar `casos.md`.**

| UC | Caso | Origem |
|---|---|---|
| **CU-AUD-01** | Listar alterações do meu business, paginadas, sem ver as de outro | `@index` + `AuditEntryService` |
| **CU-AUD-02** | Ver o diff exato de uma alteração (`old` × `attributes`) | `@show` |
| **CU-AUD-03** | Reverter uma alteração permitida, informando razão | `@revert` + `RevertService::revert()` |
| **CU-AUD-04** | Ser **impedido** de reverter Activity de outro business | `canRevert()` eixo 1 · `MultiTenantIsolationTest` |
| **CU-AUD-05** | Ser **impedido** de reverter marcação de ponto (Portaria 671/2021) | `unrevertibleRegistry()` · `AuditEntryReversibilityTest` |
| **CU-AUD-06** | Ser **impedido** de reverter NFe autorizada / baixa de título / OS | mesmo registry |
| **CU-AUD-07** | Ser impedido quando não há `properties.old` | `DomainException` |
| **CU-AUD-08** | O undo **aparece** na trilha como nova Activity | `revert()` cria Activity |
| **CU-AUD-09** | A trilha não vaza PII | `PiiLeakActivityLogEnforceTest` · `RevertServicePiiRedactionTest` |
| **CU-AUD-10** | Negação de revert é observável (span OTel) | `RevertServiceOtelSpanTest` |

⚠️ **Nenhum `casos.md` existe hoje** para as 2 telas — estes UC são **proposta**, não contrato vigente.

---

## §7 — Requisitos não-funcionais ⚙️ derivado

- **Paginação obrigatória** — o comentário no `@index` declara `LengthAwarePaginator` *"em activity_log com índices"*; com 117k linhas e crescendo, listagem sem paginação é incidente.
- **Observabilidade** — spans `auditoria.revert.check.*` nos dois caminhos.
- **`Inertia::defer`** ⬜ **não verificado** se aplicado nas props caras do `@index` (regra canônica para `paginate()`).

---

## §8 — Estratégia de qualidade ⚙️ derivado

**10 arquivos de teste no módulo** — o oposto do perfil "módulo abandonado":

`AuditEntryReversibilityTest` · `AuditNoteLogsActivityTest` · `AuditoriaModuleTest` · `MultiTenantIsolationTest` · `PiiLeakActivityLogEnforceTest` · `RevertServiceOtelSpanTest` · `RevertServicePiiRedactionTest` · `SmokeRoutesTest` · `Wave18SaturationTest` · `Wave27SaturationTest`

**+ 7 testes globais** em `tests/Feature/Auditoria/` que provam a **emissão** por Model — `ContactPiiLogsActivity`, `ProductStockLogsActivity`, `SellLinePaymentLogsActivity`, `TransactionLogsActivity`, `CauserKind*`, `RevertServiceTest`. (Estes foram classificados como *"não são do módulo"* durante a tentativa de deleção — e são justamente a prova de que a capacidade é exercida.)

**Lacuna:** zero `casos.md`, logo o `casos-gate` não defende contrato nenhum destas telas. Fechar isso é o passo natural depois deste SDD.

---

## §9 — Riscos e dívidas 🖐 curado

| # | Dívida | Severidade |
|---|---|---|
| 1 | **Sem `casos.md`** nas 2 telas — nenhum UC citado por teste; contrato indefeso | alta |
| 2 | Charters em `draft`, sem `ux_targets` | média |
| 3 | `auditoria_audit_notes` — migration existe, tabela não. Código e schema discordam | média |
| 4 | `Inertia::defer` não verificado no `@index` com 117k linhas | média |
| 5 | ⬜ Não medido: a UI explica *por que* algo é irreversível, ou só nega? | baixa |

---

## §10 — Roadmap 🖐 [W] prioriza

1. `casos.md` para Index e Detail citando `CU-AUD-01..10` (fecha a dívida 1 e liga o `casos-gate`)
2. Promover os charters de `draft` → `live` com `ux_targets`
3. Resolver a discordância `auditoria_audit_notes` (rodar a migration ou removê-la)
4. Verificar `Inertia::defer` no `@index`

---

## §11 — Referências ⚙️ derivado

- [`memory/requisitos/Auditoria/SCOPE.md`](SCOPE.md) v2.0.0 — fronteira emitir × ler
- [`SUPERFICIE.md`](SUPERFICIE.md) — 36 arquivos em 12 papéis (gerado)
- [`BRIEFING.md`](BRIEFING.md) · [`SPEC.md`](SPEC.md) · [`UI-CATALOG.md`](UI-CATALOG.md)
- [lápide §5 `proibicoes.md`](../../proibicoes.md) — não deprecar
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
