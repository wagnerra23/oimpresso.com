---
id: requisitos-governance-sdd-fluxo-v1-0
slug: governance-sdd
title: "SDD — Fluxo de ponta a ponta do Governance (cron → checker → alerta → Daily Brief → tela)"
type: sdd
module: Governance
status: ativo
owner: wagner
version: 1.0.0
last_updated: 2026-11-19
related_docs:
  - SPEC.md
  - SCOPE.md
  - BRIEFING.md
  - SUPERFICIE.md
  - RUNBOOK-drift-alerts.md
  - RUNBOOK-dashboard.md
related_adrs:
  - 0086-fase-5-mvp-governance-actiongate-warn
  - 0216-governance-drift-framework-driftchecker-plugavel
  - 0363-governance-incorpora-ads-nucleo-sem-receptor
  - 0366-fronteira-jana-forja-governance-kb
  - 0392-fronteira-governance-audiencia-enforcement-na-concessao
  - 0399-aposentar-rubrica-module-grade-gate-e-baseline
---

# SDD — Fluxo de ponta a ponta do Governance

> Derivado do código em `origin/main` @ `bedeeb0096` (2026-11-19). Cada afirmação aponta para o arquivo de onde saiu. Quando o código mudar, o código ganha: confira o arquivo citado antes de repetir uma frase daqui.
>
> Formato: o do SDD do Produto (`memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md`), reduzido às seções que se aplicam a um módulo sem cadastro de negócio.

## 1. Visão geral

O Governance responde "a regra está sendo cumprida?". Ele não bloqueia merge (isso é o CI em `scripts/governance/`); ele mede em produção e mostra. O trabalho tem três caminhos que convergem no Daily Brief e nas 7 telas de `/governance/*`:

| Caminho | Começa em | Termina em |
|---|---|---|
| A. Drift de runtime | cron `governance:audit --all --notify` | `mcp_alertas_eventos` + canal Centrifugo `governance:drift` |
| B. Scorecard e iniciativas | cron `governance:scorecard-snapshot --alert` | `mcp_scorecard_runs`, `mcp_alertas`, `mcp_governance_initiatives` |
| C. Linhas do Daily Brief | cron `brief:generate` (Modules/Forja) | texto do brief, gravado pelo Forja |

As telas leem por outro caminho: quase todas consultam o banco ou o filesystem no momento do request, sem passar pelo que os crons gravaram (seção 5.4).

## 2. Público

| Quem | O que usa |
|---|---|
| Wagner (superadmin) | as 7 telas, o brief diário |
| Time interno | Painel em leitura, se tiver `governance.dashboard.view` |
| Tenant cliente | nada. As tabelas `mcp_*` são cross-tenant por design (SPEC §"Por que Governance é cross-tenant intencional") |

## 3. Governança aplicável

- ADR 0216: framework de `DriftChecker` plugável.
- ADR 0392: fronteira do módulo. Passo 2 (`can:` por rota) feito em 2026-09-08; passo 1 (concessão, `Gate::before` do Admin) aberto, decisão [W].
- ADR 0399: a rubrica module-grade saiu. Nenhum caminho abaixo calcula nota de módulo.
- ADR 0062: o scheduler `environments(['live'])` roda no Hostinger. Isso decide o que funciona no caminho C (seção 5.3).

## 4. Componentes

Inventário arquivo a arquivo: [SUPERFICIE.md](SUPERFICIE.md) (gerado). Os papéis:

| Peça | Onde | Papel |
|---|---|---|
| `DriftCheckerRegistry` | `Modules/Governance/Services/DriftCheckerRegistry.php` | registra os checkers da chave `drift_checkers` de `Modules/Governance/Config/config.php` (o `GovernanceServiceProvider` itera e registra) |
| Checkers | `Modules/Governance/Services/Checkers/*` | cada um implementa um `DriftChecker` e devolve `DriftCheckResult` (`clean` ou `drifted`) |
| `PersistsDriftAlert` | `Services/Concerns/PersistsDriftAlert.php` | grava o achado em `mcp_alertas_eventos` |
| `PublishesDriftToCentrifugo` | `Services/Concerns/PublishesDriftToCentrifugo.php` | publica no canal `governance:drift` via `CentrifugoPublisher` do Whatsapp |
| Brief lines | `Services/*BriefLineService.php`, `AgentOutcomeBriefSectionService.php` | cada uma recebe o texto do brief e devolve o texto com a sua linha injetada |
| Telas | `Http/Controllers/*Controller.php` → `resources/js/Pages/governance/*.tsx` | 7 telas Inertia |

## 5. Arquitetura

### 5.1 Caminho A — drift de runtime

1. `app/Console/Kernel.php` agenda `governance:audit --all --notify` às 06:35 BRT, `environments(['live'])`, `onOneServer`.
2. `GovernanceAuditCommand` pede ao `DriftCheckerRegistry` todos os checkers e roda cada um.
3. Cada checker devolve `DriftCheckResult`. Em `main` hoje só existem dois estados, `clean` e `drifted` (`DriftCheckResult.php`): um checker que não conseguiu medir (npm ausente, tabela inexistente, JSON inválido) sai `clean`. O PR #11213 (aberto em 2026-11-19, lote L2) acrescenta o estado "não medido"; quando mergear, este passo muda.
4. Para cada resultado com drift, sem `--no-persist`, o comando chama `persistirDriftAlert` → linha em `mcp_alertas_eventos`.
5. Com `--notify`, publica o resultado no canal Centrifugo `governance:drift`.

Em paralelo e mais antigo, `governance:detect-drift` (06:15 BRT, `live`) compara o `contains[]` de cada `SCOPE.md` com os controllers no disco e grava `module_drift` em `mcp_alertas_eventos`.

### 5.2 Caminho B — scorecard e iniciativas

1. `governance:scorecard-snapshot --alert` às 07:00 BRT grava uma linha por módulo por dia em `mcp_scorecard_runs`; com `--alert`, quedas de 5 pontos ou mais viram linha em `mcp_alertas` (`kind=scorecard_drift`).
2. `governance:initiative-sync` às 08:00 BRT usa o snapshot do dia para atualizar `mcp_governance_initiatives`.
3. As regras do scorecard são avaliadas por `ScopedScorecardEvaluator`. A auditoria de 2026-11-19 mediu que `detectCiHealth` e `detectOtelQuery` devolvem `true` sem dado (o PR #11213 mexe no primeiro); enquanto isso valer, a nota desses dois tipos de regra não reprova.

O scorecard **SDD** é outro: `governance:sdd-scorecard-snapshot` saiu do Kernel em 2026-08-08 e roda por cron no CT 100 (`scripts/tests/ct100-sdd-scorecard-snapshot.sh`), gravando `mcp_sdd_scorecard_history`.

### 5.3 Caminho C — Daily Brief

1. `brief:generate` (comando do Modules/Forja, `GenerateBriefCommand`) roda às 7, 11, 14, 17, 20 e 23h BRT, `live`.
2. O comando chama, em sequência, as brief lines do Governance (mais a `TasksSemDonoBriefLineService` da Jana). Cada uma tem kill-switch em config.
3. `SddBriefLineService` lê `mcp_sdd_scorecard_history` (banco). As outras sete brief lines e a `AgentOutcomeBriefSectionService` rodam `node` com um script de `scripts/governance/` ou `scripts/qa/`; três delas também usam `gh`.
4. O Kernel registra (comentário de 2026-08-08) que o cron do Hostinger não encontra `node` (`exec: node: not found`). Nesse ambiente, as linhas que dependem de `node` não aparecem, e o brief não diz que faltaram. Isso é leitura do código mais a medição de 2026-08-08; o brief gravado em produção não foi conferido nesta data.

### 5.4 Onde o laço não fecha (medido em 2026-11-19)

O nome do fluxo promete cron → checker → alerta → brief → tela. No código de hoje:

| Elo | Situação |
|---|---|
| checker → alerta | fecha: drift vira linha em `mcp_alertas_eventos` |
| alerta → brief | **não fecha**: nenhuma brief line lê `mcp_alertas` nem `mcp_alertas_eventos` (`git grep` nas brief lines). O comentário do Kernel diz que o canal `governance:drift` é "consumido por Brief Jana 07h", mas o brief lê banco e scripts, não Centrifugo, e nenhum assinante do canal foi achado no repo |
| alerta → tela | **não fecha**: `DriftAlertsController` mostra o scan em runtime; `DriftAlertService::persistedAlerts()` devolve `[]` e o docblock diz que espera um enum futuro |
| checker → tela | fecha por outro caminho: a tela Drift refaz o scan de SCOPE.md × filesystem a cada request (throttle 20/min) |
| scorecard → brief | fecha só para o scorecard SDD, via `mcp_sdd_scorecard_history` |

Consequência prática: um drift que o `governance:audit` grava às 06:35 só é visto por quem consulta `mcp_alertas_eventos` direto. Ligar o elo alerta → brief ou alerta → tela é trabalho de código, fora deste documento.

### 5.5 Telas

| Rota | Controller | Lê | Gate na rota (2026-11-19) |
|---|---|---|---|
| `/governance` | — | redirect 302 para `/ia` | — |
| `/governance/dashboard` | `DashboardController` | `mcp_memory_documents`, `mcp_governance_rules`, `mcp_audit_log`, `mcp_actors`, `failed_jobs`, `jana_health_narratives`, via `Modules\Jana\Services\GovernancaService` | só `auth` (o PR #11216 acrescenta `can:governance.dashboard.view`) |
| `/governance/policies` | `PoliciesController` | `mcp_governance_rules` | `can:governance.dashboard.view`; toggle com `can:governance.policies.edit` |
| `/governance/audit` | `AuditController` + `AuditDrillDownService` | `mcp_audit_log` | `can:governance.audit.view` |
| `/governance/drift` | `DriftAlertsController` + `DriftAlertService` | SCOPE.md × filesystem, no request | `can:governance.dashboard.view` |
| `/governance/ds-rollout` | `DsRolloutController` | `governance/ds-ledger.json` | `can:governance.dashboard.view` |
| `/governance/custos` | `CustosController` + `Modules\Jana\Services\CustosService` | `jana_conversas`, `jana_mensagens` | `can:jana.admin.custos.view` no construtor |
| `/governance/qualidade-ia` | `QualidadeIaController` | `copiloto_memoria_metricas`, `jana_memoria_gabarito` | `can:jana.mcp.usage.all` no construtor |

O `Gate::before` do `AuthServiceProvider` libera o Admin de cada business em qualquer ability fora de backup/superadmin/manage_modules; `can:` não barra esse papel (comentário no `routes.php`, ADR 0392 passo 1).

## 6. Casos de uso

Os casos por tela vivem ao lado de cada `.tsx` (`resources/js/Pages/governance/*.casos.md`), com os testes que os citam. As US estão no [SPEC](SPEC.md). Este documento não repete os casos; ele descreve o caminho dos dados entre eles.

## 7. Requisitos não-funcionais

- Cross-tenant por design nas tabelas `mcp_*` (SPEC). As telas de Custos e Qualidade IA são por business.
- Throttle por rota (`routes.php`): 60/min leitura, 10/min toggle, 30/min audit/custos/qualidade, 20/min drift.
- Nenhum Job, Listener ou Event no módulo: nada roda em fila, então não há `business_id` a carregar em construtor de job.

## 8. Riscos e dívidas (2026-11-19)

| Dívida | Onde | Dono |
|---|---|---|
| alerta não chega ao brief nem à tela | seção 5.4 | código, a decidir |
| checker sem estado "não medido" | `DriftCheckResult` | PR #11213 (lote L2) |
| brief lines dependem de `node` no Hostinger | seção 5.3 | decisão [W]: mover `brief:generate` para o CT 100 ou pré-computar |
| `ActionGate` registrado e sem rota | `GovernanceServiceProvider`, `routes.php` | decisão [W] (ADR 0086) |
| concessão `admin_only` (passo 1 da 0392) | `AuthServiceProvider` | decisão [W] |
| `observability:aggregate-daily` agrega tabela sem escritor | `mcp_observability_spans` | a decidir |
| `RoutesZombieChecker` depende de `system_access_log`, que não tem migration | checker | a decidir |

## 9. Changelog

- **1.0.0** (2026-11-19) — primeira versão, derivada do código na auditoria Governance L4.4.
