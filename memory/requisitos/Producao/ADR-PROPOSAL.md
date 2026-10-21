---
id: requisitos-pcp-adr-proposal
slug: 0431-pcp-sai-do-desejo-modules-producao
number: 431
title: "PCP / Apontamento de Produção — camada fina cross-vertical (proposta)"
type: adr
status: accepted
accepted_at: "2026-10-15"
accepted_via: "ADR 0431 (memory/decisions/0431-pcp-sai-do-desejo-modules-producao.md)"
authority: canonical (quando accepted)
lifecycle: canon
proposed_by: [W]
proposed_at: 2026-05-12
module: Producao
tags: [pcp, apontamento, cross-vertical, fsm, multi-tenant, kanban, mobile-pwa, qr-code]
supersedes: []
amends: [0143]
related: [0093, 0094, 0104, 0121, 0129, 0143, 0137, 0105, 0106]
pii: false
review_triggers:
  - "Wagner aprovar D1 (Pcp vs IProduction)"
  - "Sinal qualificado piloto Vargas ou Extreme ativar fase 1"
  - "FSM canon (ADR 0143) sofrer breaking change em side-effects"
---

# ADR NNNN — PCP / Apontamento de Produção camada fina cross-vertical (proposta)

## Status

**Accepted 2026-10-15** pela [ADR 0431](../../decisions/0431-pcp-sai-do-desejo-modules-producao.md). [W] respondeu D1–D5 no chat em 2026-10-15 (*"eu autorizo tudo, nome da pasta 'Producao' · e sim quero PCP e autorizado fazer"*):

| id | resposta de [W] em 2026-10-15 |
|---|---|
| D1 | **`Modules/Producao`** — nem `Pcp`, nem renomear `IProduction` (que já tinha sido removido no PR #842) |
| D2 | PWA |
| D3 | híbrido, padrão automático |
| D4 | configurável por empresa, com valores sugeridos |
| D5 | regra simples na V1; IA depois |

O texto abaixo é a proposta de 2026-05-12, preservada como foi escrita: as seções "Decisões pendentes" registram as opções e as recomendações da época. Onde ela dizia `Modules/Producao`, as citações foram trocadas para o nome decidido (rename curado no `ghost-rename-map`); as permissões são `producao.*`.

**Proposed 2026-05-12.** Discovery + SPEC + MATRIZ-ROI + ROADMAP elaborados.

## Contexto

### Problema observado

Múltiplos clientes verticais (atuais e candidatos pipeline) precisam de **apontamento de produção** em granularidade OPERATION-by-USER-by-TIME, capacidade de máquina, agendamento e detecção de gargalo:

- **OficinaAuto** (Vargas recapagem multi-mecânico, Martinho caçambas) — quem trocou pneu de quem em qual box quando
- **ComunicacaoVisual** (Extreme/Gold/Zoom multi-plotter) — qual plotter imprimiu qual banner em que horário, capacidade m²/dia, fila
- **Repair** (oficinas técnicas pequenas) — quem diagnosticou, quem reparou, tempo padrão por defeito
- **Vestuario** (ROTA LIVRE futuro) — costura/corte multi-operadora (sinal qualificado futuro)

### Discovery — 60% já existe

Skill `mcp-first` + leitura de `_MAPPING/TELA-PRODUCAO-KANBAN.md` + `ADR 0143` + código Repair/Manufacturing revelou:

| Pré-arte oimpresso | Onde vive | Status |
|---|---|---|
| Kanban OS shared-infra (genérico, slot config JSON) | `Modules/Repair/Http/Controllers/ProducaoOficinaController.php` + Page Inertia | ✅ produção |
| FSM canon stages produção (`in_production`, `em_execucao`, `pausado`) + history append-only | ADR 0143 — 5 tabelas FSM | ✅ produção |
| OS entity (`repair_job_sheets`) com técnico, prazo, checklist, fotos | `Modules/Repair` | ✅ produção |
| BoM / Receita / Waste% | `Modules/Manufacturing/MfgRecipe` | ✅ legacy ativo |
| `service_orders` + `vehicles` | `Modules/OficinaAuto` US-OFICINA-001 PR #556 | ✅ scaffold |
| Multi-tenant Tier 0 scaffolding (`HasBusinessScope`, FK business_id indexed) | `App\Concerns\HasBusinessScope` | ✅ Tier 0 IRREVOGÁVEL |
| Notification cliente status change (event + listener + LGPD consent) | `RepairStatusChanged` + `NotifyRepairCustomer` | ✅ produção |
| Mapping legacy Delphi PCP (8 agrupadores Kanban + 13 lookups + WR_KANBAN table) | `research/_MAPPING/TELA-PRODUCAO-KANBAN.md` | ✅ source-first documented |
| `IProduction` placeholder | módulo vazio (DataController + Install stub L3) | ⚠️ não-construído |

### Gap real (40%)

1. **Apontamento OPERATION-level** (não STAGE-level) — quem fez o quê quando em qual posto + cronômetro
2. **Catálogo postos** (`pcp_workstations`) + capacidade hora/dia
3. **Catálogo operações** (`pcp_operations`) + tempo padrão
4. **QR code scan mobile** PWA — operador escaneia OS → cria appointment
5. **Detecção de gargalo** automática + alerta
6. **Agendamento** (`pcp_schedules`) drag-drop calendário
7. **Dashboard PCP** Inertia + Centrifugo broadcast
8. **Performance per-operator** (LGPD-guarded)

### Razão estratégica

Vargas/Extreme/Martinho (3 candidatos OfficeImpresso saudáveis) usam Delphi `PRODUCAO_TEMPO` table real hoje (legacy WR Comercial — apontamento manual via teclado). Sem essa funcionalidade no oimpresso novo, **migração quebra paridade** e bloqueia sinal qualificado (ADR 0105).

ROI alta — paridade Delphi + diferencial vs Bling (que NÃO tem cronômetro/QR mobile real-time). **Contra o Mubisys é paridade, não diferencial** (corrigido em 2026-09-29): ele anuncia terminal de apontamento que mede o tempo de cada tarefa pelo celular, e o app tem leitura de QR Code na tela de Produção — ver [pesquisa de concorrentes](../../research/2026-05-prospeccao/02-concorrentes-zenite-mubisys.md).

## Decisões pendentes

### D1 — Pcp módulo novo OU renomear IProduction?

**Opções:**

**(a) `Modules/Producao` novo** — nome canônico claro, isolado, sem baggage.
- ✅ Pró: nomenclatura PT-BR / domínio claro
- ✅ Pró: zero risk de mexer em código IProduction (mesmo stub)
- ❌ Contra: IProduction permanece zombie

**(b) Renomear `IProduction` → `Modules/Producao`** — aproveita scaffold existente (DataController/Install).
- ✅ Pró: limpa zombie
- ❌ Contra: rename custoso (migrations existentes? URL prefix mudaria de `/iproduction/*` pra `/pcp/*` — ver SCOPE.md `url_prefixes`). Skill `migrar-modulo` (ADR 0088) tem matriz 8 dimensões.
- ❌ Contra: IProduction tem `permission_prefix: iproduction.*` espalhado se houver permissions seedadas

**(c) `Modules/Producao` novo + delete `IProduction`** — limpa zombie sem rename custoso.
- ✅ Pró: zero baggage
- ✅ Pró: skill `criar-modulo` (8 peças) caminho conhecido
- ❌ Contra: deletar módulo (mesmo stub) exige ADR justificando

**Recomendação Claude:** opção (c) — `Modules/Producao` novo + ADR justificando delete IProduction (provavelmente vazio em produção; confirmar com `php artisan permission:cache-reset` + grep `iproduction.*`).

**Pendente:** Wagner decide.

### D2 — QR code OS: PWA mobile vs app nativo

**Opções:**

**(a) PWA Inertia + service worker + camera API** (`@zxing-js/library` ou `jsqr`).
- ✅ Pró: stack canônica (React 19 + Inertia v3 + Vite + Tailwind 4)
- ✅ Pró: 1 codebase web/mobile, zero deploy app store, zero custo Apple/Google
- ✅ Pró: install-on-homescreen via PWA prompt funciona iOS 16+ e Android
- ❌ Contra: câmera iOS Safari PWA tem quirks (autofocus, torch)
- ❌ Contra: offline-first via IndexedDB queue = código não-trivial

**(b) App nativo (React Native / Capacitor)**.
- ✅ Pró: câmera nativa robusta
- ❌ Contra: deploy app store, 2ª codebase, custo manutenção 2×, Wagner tem 5 pessoas time
- ❌ Contra: distribuição fora-da-loja = TestFlight/APK install não-trivial pros operadores

**Recomendação Claude:** **(a) PWA**. Inicia mais simples; se câmera quirk virar bloqueador prod, considera Capacitor wrapper depois (compromisso reversível).

**Pendente:** Wagner decide.

### D3 — Cronômetro automático vs apontamento manual

**Opções:**

**(a) Cronômetro automático** — `pcp_appointments.finished_at=null` enquanto operação roda, set automatic ao próximo scan `action: stop`.
- ✅ Pró: dado granular real (paridade Odoo Shop Floor / SAP confirmation)
- ✅ Pró: tempo padrão vs real automático
- ❌ Contra: operador esquece de stop → appointment "vazio" (24h+) — precisa cron auto-close ou alert

**(b) Apontamento manual lump-sum** — operador digita "trabalhei 3h hoje" no fim do turno.
- ✅ Pró: paridade Delphi `PRODUCAO_TEMPO` legacy WR Comercial
- ❌ Contra: dados de baixa fidelidade, perde gargalo real-time

**(c) Híbrido — default automático com fallback manual** — recomendado.
- ✅ Pró: melhor dos dois
- ❌ Contra: 2 caminhos no schema (precisa `manual_entry: bool` flag)

**Recomendação Claude:** **(c) híbrido com default (a)**. Schema suporta ambos sem complexidade extra.

**Pendente:** Wagner decide.

### D4 — Capacidade hard-coded vs configurável per-business

**Opções:**

**(a) Hard-coded por código** — `case 'plotter_uv': return 10` no service.
- ❌ Contra: muda cliente = muda código. Não-multi-tenant-friendly.

**(b) Configurável per-business** — coluna `pcp_workstations.capacity_per_hour` editável via UI admin.
- ✅ Pró: cada cliente seta sua realidade
- ✅ Pró: histórico via audit log
- ❌ Contra: cliente novo tem 0 dados — onboarding com defaults sugeridos

**Recomendação Claude:** **(b) configurável**, com **catálogo de defaults** (Modules/Producao seeder com referência tipo "Plotter HP Latex 64in 12m²/h" pra UX onboarding).

**Pendente:** trivial, Wagner aprova default.

### D5 — Gargalo detection: regras simples vs Jana IA

**Opções:**

**(a) Regras simples** — `count(waiting) >= 3 × capacity_per_hour → alert`.
- ✅ Pró: explicável, sem custo Brain B
- ✅ Pró: paridade ADS-Policy Tier 1 (ADR 0049)
- ❌ Contra: limitado a 1 heurística

**(b) Jana IA Brain B (Sonnet/Opus)** — context snapshot semanal → análise.
- ✅ Pró: insight contextual ("plotter A gargalo mas plotter B ocioso — redirecionar?")
- ❌ Contra: custo $0.05+/call, latência 3-8s
- ❌ Contra: governance ADS Tier 1+ exige policy + RBAC HITL

**Recomendação Claude:** **(a) regras simples** V1 (US-PCP-012), backlog **(b) Jana Brain B** P3 (após ADS canon estável — ~ago/2026).

**Pendente:** trivial, Wagner aprova caminho a→b.

## Multi-tenant Tier 0 amarração (OBRIGATÓRIO ADR 0093)

- ✅ Todas tabelas PCP (`pcp_workstations`, `pcp_operations`, `pcp_appointments`, `pcp_schedules`) têm `business_id` indexed + FK + `HasBusinessScope`
- ✅ `pcp_appointments` é append-only (trigger MySQL — paridade Portaria 671/2021 pattern aplicada)
- ✅ Roles Spatie per-business com sufixo `#{biz}` (ex: `pcp.workstation.create#1`, `pcp.performance.view#1`)
- ✅ Jobs assíncronos sempre recebem `$businessId` no constructor
- ✅ Endpoint API `POST /api/pcp/scan` scoped por JWT `business_id` claim — 404 silencioso anti-info-leak
- ⛔ `withoutGlobalScopes` permitido APENAS com comentário `// SUPERADMIN: <razão>` (skill `multi-tenant-patterns` Tier A)

## LGPD considerações

- **Performance per-operator** é PII trabalhista — `pcp.performance.view` permission gate
- `pcp_appointments.user_id` registra histórico individual — informar operadores no contrato/política (LGPD Art. 7º base legal: legítimo interesse empresa)
- WhatsApp notification ao cliente respeita `contacts.whatsapp_consent` (ADR 0143 já amarrado)
- PII redactor logs (skill `commit-discipline` Tier A enforce)

## Alternativas avaliadas

- ❌ Substituir Kanban Repair por Kanban novo Pcp — viola §0 (não duplicar)
- ❌ Criar processo FSM novo `producao_pcp` — viola §4 (usar `venda_com_producao` + `os_reparo_padrao`)
- ❌ Apontamento via tabela Manufacturing existente (`mfg_*`) — Manufacturing é recipes/BoM, NÃO temporal/operator-tracking
- ❌ Vendor SaaS PCP externo (TOTVS Mes/Frepple) — viola Constituição (Tier 0 isolation, custo, dependência externa, SoC brutal)
- ❌ MRP completo Fase 1 — over-engineering, faseado pra P3 (US-PCP-016+ BoM consumption ponta)

## Consequências esperadas

### Positivas

1. **Paridade Delphi WR Comercial** — Vargas/Martinho/Extreme migrados sem perder PRODUCAO_TEMPO funcionalidade
2. **Diferencial vs Bling/Conta Azul** — eles NÃO têm cronômetro mobile real-time + QR scan
3. **Audit trail completo append-only** — base legal trabalhista (LGPD Art. 7º) + paridade Portaria 671/2021 pattern
4. **Cross-vertical** — 1 fundação serve 4+ módulos verticais
5. **Reusa 60% pré-arte** — Kanban Repair + FSM canon + BoM Manufacturing + multi-tenant scaffolding

### Negativas / Trade-offs

1. **Cronômetro PWA câmera quirks iOS Safari** — Capacitor wrap fallback se bloquear
2. **Operador esquece stop** → appointment "vazio 24h+" → precisa cron `pcp:auto-close-stale-appointments`
3. **Offline-first PWA IndexedDB queue** = código não-trivial pra escrever bem (race condition, replay-safe)
4. **Aprendizado FSM action intermediária** (não cruzar fronteira de stage) — pattern novo, vai exigir docs
5. **`IProduction` zombie** se opção D1=(a)/(c) — limpar ou aceitar

## Refs

- **ADR mãe FSM**: [0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- **ADR Tier 0**: [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- **ADR Modular vertical**: [0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
- **ADR MWART**: [0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- **ADR Estimates 10x**: [0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- **ADR sinal qualificado**: [0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- **SPEC PCP**: [SPEC.md](SPEC.md)
- **MATRIZ-ROI**: [MATRIZ-ROI.md](MATRIZ-ROI.md)
- **ROADMAP**: [ROADMAP.md](ROADMAP.md)
- **Mapping legacy**: [research/_MAPPING/TELA-PRODUCAO-KANBAN.md](../../research/clientes-legacy-officeimpresso/_MAPPING/TELA-PRODUCAO-KANBAN.md)

## Aprovação

[W] decidiu D1–D5 em 2026-10-15; aceita pela [ADR 0431](../../decisions/0431-pcp-sai-do-desejo-modules-producao.md).

---
**Versão inicial proposed 2026-05-12.** Accepted 2026-10-15 (ADR 0431).
