---
tipo: deprecation-plan
alvo: Pipeline CRM pré-venda (Modules/Crm parte B)
preserva: Cadastro de Cliente (parte A) — intocável
status: planejado
data: "2026-06-22"
gerado_por: agente deprecar-modulo
owner: W
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0105-cliente-como-sinal-guiar-sem-mandar, 0179-cliente-drawer-760px-substitui-show-fullpage]
---

# DEPRECATION-PLAN — Pipeline CRM pré-venda (Modules/Crm parte B)

> **Status:** Planejado (planejamento puro — nada executado) · **Owner:** Wagner · **Sucessor canônico:** nenhum (descontinuação, não migração — "não faz sentido pro negócio gráfica/vestuário")
> **Gerado por:** agente `deprecar-modulo` · **Data:** 2026-06-22
> **Escopo:** APENAS pipeline pré-venda (B). O cadastro de Cliente / contatos (A) é **KEEP intocável** ([SPEC](../Cliente/SPEC.md)).

## TL;DR

O `Modules/Crm` é um módulo SPLIT. Confirmado por inventário de código que **A (cadastro) e B (pipeline) estão tecnicamente desacoplados** — os controllers `/cliente/*` e `app/Http/Controllers/ContactController` têm **ZERO referências** a entidades ou tabelas do pipeline. Logo, B pode ser deprecado sem tocar A, com 3 ressalvas de acoplamento na borda (todas resolvíveis):

1. **Tabela `contacts` é COMPARTILHADA.** O pipeline NÃO tem tabela de lead própria — `CrmContact extends App\Contact` reusa `contacts` com `type='lead'` + colunas `crm_source`/`crm_life_stage`. Essas colunas e linhas NÃO podem ser dropadas (tabela viva da Larissa). Decisão: **PRESERVE in-place** (colunas viram dormentes).
2. **API externa `Modules/Connector` lê o pipeline.** `/connector/api/crm/follow-ups` e `/connector/api/crm/leads` (middleware `auth:api` + `log.delphi`) resolvem `Modules\Crm\Entities\Schedule` e colunas `crm_life_stage`. **BLOQUEIO:** auditar consumidor Delphi/officeimpresso antes de remover entidades.
3. **`users.crm_contact_id` (FK → contacts, CASCADE)** é o link portal-cliente — pertence a A (cadastro/portal), NÃO ao pipeline. KEEP.

Tabelas pipeline-próprias (`crm_*`): **9 tabelas, 0 FK externa entrante** (todos os FKs entrantes são internos ao próprio pipeline). Decisão de dados: **5 ARCHIVE-then-DROP (após verificação de rows + gate Wagner), 3 DROP-cascade-interno, 1 PRESERVE-colunas (`contacts`)**.

Sinal de uso real: pipeline-código congelado (1 commit/90d, e é docs-sweep). MAS **as TABELAS podem ter rows em prod** — não rodei SQL. Toda decisão de DROP está **BLOQUEADA até verificação de row count por business** (queries propostas abaixo, não executadas) + gate Wagner explícito.

---

## Fase 1 — Inventário (resultado)

```
Módulo: Crm (SPLIT — A cadastro KEEP / B pipeline TARGET)
SCOPE vs BRIEFING: CONFLITANTE e intencional.
  - SCOPE.md purpose = "módulo de CLIENTE" (A), mas contains[] lista 27 controllers (mistura A+B).
  - BRIEFING.md = 🔇 SILENCIADO 2026-06-08 (descreve só o pipeline B legacy).
  - SPEC.md = draft de PROPOSTA de EXPANSÃO do pipeline (US-CRM-001..062), status "rascunho",
    needs_wagner_approval. NUNCA aprovado. A deprecação de B torna este SPEC obsoleto.
Code stats (módulo inteiro): 27 Controllers, 9 Services, 12 Entities, 27 Migrations, 13 Tests.
Git activity 90d (arquivos pipeline-only): 1 commit (docs/handoff sweep #3092). Deal = 2026-05-17 (pré-silêncio).
Cross-refs externos: 5 ADRs (todas sobre A/cadastro: 0179/0185/0186/0197 + 0013 inventário),
  1 módulo (Connector — lê pipeline B via API externa), 0 skills/hooks/rules pipeline-específicas,
  binding aspiracional CrmLeadRepositoryInterface (ninguém fora do Crm resolve).
```

### Classificação A (KEEP) vs B (TARGET) — confirmada por código

| Artefato | Lado | Evidência |
|---|---|---|
| `App\Contact` + `App\ContactAddress` (core) | **A KEEP** | Tabelas `contacts`/`contact_addresses` |
| `Cliente{Autosave,Lookup,Ia,Auditoria,OssData,Veiculos}Controller` + `ContactAddressController` | **A KEEP** | Rotas `/cliente/*`; grep pipeline = 0 matches |
| `app/Http/Controllers/ContactController` | **A KEEP** | `use App\Contact` (nunca `CrmContact`) |
| Pages `resources/js/Pages/Cliente/**` | **A KEEP** | "lead" nos arquivos = CSS `leading-*` ou comentário; 0 coupling real |
| `users.crm_contact_id` FK→contacts | **A KEEP** | Link portal-cliente (`ClienteOssDataController::persons`) |
| `ContactLoginController`, `OrderRequestController`, portal `/contact/*` | **ZONA CINZA** | Portal do cliente — NÃO é pré-venda. **Fora do escopo** (Wagner decide separado) |
| `LeadController`, `ProposalController`, `ProposalTemplateController`, `CampaignController`, `CallLogController`, `ScheduleController`, `ScheduleLogController`, `CrmDashboardController`, `ReportController`, `CrmMarketplaceController`, `DataController`, `CrmSettingsController` | **B TARGET** | Rotas `/crm/*`, nav `crm::layouts.nav` |
| Entities `Leaduser`, `CrmContact`, `Proposal`, `ProposalTemplate`, `Campaign`, `Schedule`, `ScheduleLog`, `ScheduleUser`, `CrmCallLog`, `Deal`, `CrmMarketplace`, `CrmContactPersonCommission` | **B TARGET** | Pipeline domain |
| Services `CrmLeadService`, `ProposalService`, `CampaignService`, `CallLogService`, `ScheduleService`, `DealPipelineService`, `LeadAssignmentService` | **B TARGET** | Pipeline domain |
| Services `BrLookupService`, `ContactBookingService` | **ZONA CINZA** | `BrLookupService` pode ser usado por `ClienteLookupController` (A) — **confirmar antes de remover** |
| Commands `pos:sendScheduleNotification` (everyMinute), `pos:createRecursiveFollowup` (daily), `crm:health` | **B TARGET** | Follow-up reminders |

> ⚠️ **Ajuste vs premissa:** não existe command `crm:send-follow-up-reminders`. O que existe é `pos:sendScheduleNotification` + `pos:createRecursiveFollowup`, agendados em `CrmServiceProvider::registerScheduleCommands()` (não em `app/Console/Kernel.php`). Igualmente, `CrmMarketplace` usa `crm_marketplaces` (plural). Existe `Deal`/`crm_deals` (Wave 27, 2026-05-17) não citado na premissa.

---

## Fase 2 — Cross-dependência crítica (A depende de B?)

**Resposta: NÃO há acoplamento que impeça remover B sem quebrar A.** Detalhe por suspeita:

| Suspeita de acoplamento | Veredito | Evidência |
|---|---|---|
| `CrmContact extends Contact` — o cadastro (A) usa `CrmContact`? | **NÃO.** A usa `App\Contact`. `CrmContact` só por controllers/services de B + `app/User.php` (typed relation) + Connector (externo) | grep: `Cliente*Controller`/`ContactController(app)` = 0 matches `CrmContact` |
| `convertToCustomer` é usado no fluxo de venda da Larissa? | **NÃO.** Só `LeadController` (rota `/crm/lead/{id}/convert`) e `CrmLeadService` | grep confinado |
| Colunas `contacts.crm_source`/`crm_life_stage` — A lê? | **NÃO (A).** Lidas por `CrmContact`, `CrmDashboardController`, `DataController` (B) e `Connector ContactController` (externo). `app/Contact.php` só tem 1 ref defensiva `where('type','!=','lead')` que deve PERMANECER | grep |
| `users.crm_contact_id` é pipeline? | **NÃO — é A/portal.** FK→contacts CASCADE, usado por `ClienteOssDataController::persons` (drawer 760, KEEP) | `app/User.php:356` |
| `BrLookupService` — usado por A? | **PROVÁVEL SIM.** `ClienteLookupController` (A) faz CEP/CNPJ/SEFAZ. **Confirmar antes de mover** | `routes/web.php:93` |
| `CrmLeadRepositoryInterface` resolvido fora do Crm? | **NÃO.** Binding aspiracional | grep fora de `Modules/Crm/` = 0 |

### Acoplamentos de BORDA que exigem ação (não bloqueiam A, mas exigem cuidado)

1. **`Modules/Connector` API externa** (`/connector/api/crm/follow-ups`, `/leads`, `FollowUpController`) — resolve `Schedule::statusDropdown()` etc. Remover a Entity `Schedule` quebra o endpoint. **BLOQUEIO E4** até auditar se o Delphi/officeimpresso ainda chama.
2. **`Connector ContactController::store`** chama `CrmContact::createNewLead()` quando `type='lead'`. Auditar se algum cliente externo cria leads.
3. **`contacts` colunas dormentes** — `crm_source`, `crm_life_stage`, `converted_by`, `converted_on` permanecem (PRESERVE). Não removê-las evita migração destrutiva na tabela viva.

---

## Fase 3 — Consistência de dados (decisão por tabela `crm_*`)

Mapa de FK confirmado por leitura das 27 migrations: tabelas pipeline apontam PARA `contacts`/`business` (FK saínte). **Nenhuma tabela externa aponta PARA o pipeline.** FKs entrantes são 100% internos.

| Tabela | Rows (verificar) | PII | LGPD retention | Decisão | Notas |
|---|---|---|---|---|---|
| `contacts` (colunas `crm_*`) | viva (A) | sim | 730d (lead) | **PRESERVE in-place** | NÃO dropar tabela nem colunas. Tier 0: tabela da Larissa |
| `crm_schedules` | ? | parcial | 1095d | **ARCHIVE→DROP** | Lido pela API Connector (BLOQUEIO) |
| `crm_schedule_users` (pivot) | ? | não | herda | **DROP cascade** | Drop antes de schedules (FK) |
| `crm_schedule_logs` | ? | parcial | 1095d | **ARCHIVE→DROP** | Notas de follow-up |
| `crm_followup_invoices` (pivot) | ? | não | herda | **DROP** | `down()` VAZIO (defeito a corrigir) |
| `crm_call_logs` | ? | **ALTA** (`mobile_number`) | 365d | **ARCHIVE(redacted)→DROP** | `down()` BUGADO (dropa `call_logs`). Dump COM PiiRedactor |
| `crm_proposals` | ? | média | 1825d | **ARCHIVE→DROP** | Pode ter `App\Media` anexos — auditar storage |
| `crm_proposal_templates` | ? | baixa | indefinido | **ARCHIVE→DROP** | Dump leve |
| `crm_campaigns` | ? | média | 1825d | **ARCHIVE→DROP** | Histórico marketing |
| `crm_marketplaces` | ? | baixa | 1095d | **ARCHIVE→DROP** | "uso real desconhecido" — se 0 rows, DROP direto |
| `crm_contact_person_commissions` | ? | baixa | 1825d | **ARCHIVE→DROP** | Regra comercial |
| `crm_deals` | ? | baixa | n/a (recente) | **ARCHIVE→DROP** | Wave 27; provável ZERO rows reais |
| `crm_lead_users` (pivot) | ? | não | herda | **DROP** | Atribuição vendedor↔lead |

Também: `business.crm_settings` (JSON) — **PRESERVE** (tabela core). `users.crm_*` — **KEEP** (usadas por A).

### Queries de verificação — PROPOR, NÃO EXECUTAR (rodar em réplica/staging, nunca em prod)

```sql
-- Rows por business em cada tabela pipeline (read-only SELECT). NÃO É DML.
SELECT 'crm_schedules' t, business_id, COUNT(*) n, MAX(updated_at) ultimo FROM crm_schedules GROUP BY business_id
UNION ALL SELECT 'crm_proposals', business_id, COUNT(*), MAX(updated_at) FROM crm_proposals GROUP BY business_id
UNION ALL SELECT 'crm_campaigns', business_id, COUNT(*), MAX(updated_at) FROM crm_campaigns GROUP BY business_id
UNION ALL SELECT 'crm_call_logs', business_id, COUNT(*), MAX(updated_at) FROM crm_call_logs GROUP BY business_id
UNION ALL SELECT 'crm_deals', business_id, COUNT(*), MAX(updated_at) FROM crm_deals GROUP BY business_id
UNION ALL SELECT 'crm_marketplaces', business_id, COUNT(*), MAX(updated_at) FROM crm_marketplaces GROUP BY business_id;

-- Leads vivos na tabela COMPARTILHADA contacts (NÃO dropar — só medir)
SELECT business_id, COUNT(*) leads FROM contacts WHERE type='lead' GROUP BY business_id;

-- Foco biz=4 ROTA LIVRE (Larissa) — quanto pipeline ela tem? (esperado ~0)
SELECT (SELECT COUNT(*) FROM crm_schedules WHERE business_id=4) schedules,
       (SELECT COUNT(*) FROM crm_proposals WHERE business_id=4) proposals,
       (SELECT COUNT(*) FROM contacts WHERE business_id=4 AND type='lead') leads_b4;
```

**Regra de ouro:** qualquer tabela com `n>0` em business pagante = **BLOQUEIO Wagner** antes de DROP (vira ARCHIVE indefinido). `crm_deals`/`crm_marketplaces` com `n=0` global = candidatas a DROP direto.

---

## Fase 4 — Incorporação nos receptores

Como B é **descontinuação** (não migração), não há receptor que absorve features. Patches de remoção/proteção:

| Alvo | Patch | Etapa |
|---|---|---|
| `Modules/Crm/Routes/web.php` | Comentar/flag grupo `/crm/*` (24-80). **NÃO tocar** `/cliente/*` (101-285) nem `/contact/*` (portal, decisão à parte) | E1/E2 |
| `Resources/views/layouts/nav.blade.php` | Remover itens leads/follow-ups/campaigns/call-log/proposals/reports/b2b-marketplace/dashboard | E2 |
| `CrmServiceProvider::registerScheduleCommands()` | Remover `pos:sendScheduleNotification` + `pos:createRecursiveFollowup` do schedule (MESMO PR da remoção dos commands) | E4 |
| `CrmServiceProvider::registerCommands()`/`registerContracts()` | Remover commands pipeline + binding `CrmLeadRepositoryInterface` | E4 |
| `Modules/Connector/Routes/api.php` (112-117) + `FollowUpController` | **BLOQUEIO** — auditar consumidor. Inativo → 410 Gone. Ativo → manter até migrar consumidor (Wagner+Felipe) | E4 (gated) |
| `Connector Api/ContactController::store` | Branch `type=='lead'` → remover `CrmContact::createNewLead`; 422 | E4 (gated) |
| `crm.*` permissions | Seed cleanup das permissões pipeline. **Preservar** `crm.access_contact_login` se portal ficar | E5 |
| `memory/requisitos/Crm/SPEC.md` | Status `descontinuado` (era draft nunca aprovado) | E6 |
| `Modules/Crm/BRIEFING.md` | Banner 🔇 → "DEPRECADO via ADR NNNN" + link plano | E6 |
| `Modules/Crm/SCOPE.md` | Remover do `contains[]` os controllers de B; manter `Cliente*`/`ContactAddress` | E6 |
| `module.json` | Manter `active:1` (A vive) | — |

---

## Fase 5 — Risk register Tier 0

| # | Risk | Sev | Tier 0? | Mitigation | Etapa |
|---|---|---|---|---|---|
| 1 | DROP de coluna/linha em `contacts` quebra cadastro da Larissa | Crítico | **SIM** | NUNCA dropar `contacts`/colunas `crm_*`. PRESERVE in-place. Pest cross-tenant + smoke `/cliente` biz=4 | E3/E4 |
| 2 | Cross-tenant leak ao arquivar (dump sem filtro `business_id`) | Crítico | **SIM (0093)** | Dump particionado por business; Pest cross-tenant antes/depois | E3 |
| 3 | PII em `crm_call_logs`/`crm_proposals` em dump claro | Crítico | **SIM (LGPD)** | `PiiRedactor` (`Modules\Jana\Services\Privacy\PiiRedactor`); AES-256; retention | E3 |
| 4 | API externa Connector quebra (Delphi ainda chama) | Alto | parcial | Auditar `log.delphi` antes de remover `Schedule`/`CrmContact`. 410 só após confirmar morto | E4 BLOQUEIO |
| 5 | `down()` defeituosos (call_logs nome errado; followup_invoices/users vazios) | Alto | **SIM (proibicoes)** | Migration de remoção (E5) com `down()` reverso CORRETO; não confiar nas legacy | E5 |
| 6 | Schedule cron órfão (everyMinute!) se remover command sem tirar do schedule | Alto | não | Remover schedule + command no MESMO PR | E4 |
| 7 | `crm.*` permissions órfãs | Médio | não | Seed cleanup no MESMO PR + Pest | E5 |
| 8 | `BrLookupService` removido por engano quebra CEP/CNPJ do cadastro | Alto | não | Confirmar se A usa antes; se sim, fica em A | E1 |
| 9 | Reversibilidade | Médio | não | E1-E2 só comentam (revert = descomentar); DROP só após 30d flag-off + dumps | E1-E5 |
| 10 | Larissa biz=4 UX quebrada sem aviso | Alto | não | Query Fase 3 confirma biz=4 ~0 pipeline; canary 24h + aviso 7d | E2/E4 |

---

## Fase 6 — Roadmap 6 etapas (faseado, reversível, gates Wagner)

| Etapa | Tipo PR | LOC | Pré-req | Gate Wagner | Reversível? |
|---|---|---|---|---|---|
| **E1 — ADR deprecação + verificação rows** | docs + SELECT read-only staging | ~120 | Este plano aprovado; queries Fase 3 em réplica; confirmar BrLookupService=A | ADR proposal→accepted. **Rows em biz pagante → BLOQUEIO (ARCHIVE indefinido)** | n/a |
| **E2 — Silenciar rota + nav (flag)** | chore | ~60 | E1 | `/cliente` + `/contact` intactos; `/crm/*` → 404; biz=4 OK | SIM |
| **E3 — ARCHIVE dados (dump + PiiRedactor)** | feat (script, sem DML destrutivo) | ~200 | E2 + queries E1 | Dump por business + redaction; Pest cross-tenant | SIM (dumps = cópia) |
| **E4 — Remover código + schedule + auditar Connector** | refactor | ~280 | E3 + auditoria Connector | Cron removido; Connector 410 só se morto; canary biz=4 24h | SIM (revert PR) |
| **E5 — DROP tabelas (30d após E4)** | feat (migration `down()` CORRETO) | ~180 | E4 +30d sem incidente; dumps E3 | 30d limpo + rows arquivadas; `down()` recria schema | Parcial |
| **E6 — Cleanup docs canônicos** | docs | ~120 | E5 | SPEC→descontinuado; BRIEFING→deprecado; SCOPE limpo; `proibicoes.md` +entry | n/a |
| **Total** | — | **~960** | — | — | **47d+ (30d wait E4→E5)** |

**Ordem de segurança:** `contacts` por último e nunca destrutivo. Pivots internos dropam ANTES das tabelas-pai (FK).

---

## Defeitos legacy a corrigir na remoção (E5)

- `down()` errado em `2021_02_04_120439_create_call_logs_table.php` (dropa `call_logs`).
- `down()` vazio em `2021_02_19_120846_create_crm_followup_invoices.php` e `2020_03_19_130231_add_contact_id_to_users_table.php`.

## BLOQUEIOS antes de qualquer DROP

1. Row count por business (queries Fase 3 — rodar em réplica; **não rodado**).
2. Auditoria do consumidor externo Connector (`log.delphi`).
3. Confirmar `BrLookupService` pertence a A.

## Refs

- **ADRs:** 0093 (Tier 0), 0094 (Constituição v2), 0105 (cliente como sinal), 0179/0185/0186/0197 (cadastro A — intactos).
- **Cadastro A (KEEP):** [SPEC Cliente](../Cliente/SPEC.md), `Modules/Crm/Http/Controllers/Cliente*Controller.php`, `app/Contact.php`, `app/ContactAddress.php`, rotas `/cliente/*`.
- **Pipeline B (TARGET):** `Modules/Crm/Routes/web.php:24-80`, `nav.blade.php`, `CrmServiceProvider::registerScheduleCommands()`, entidades/serviços/migrations `crm_*`.
- **Borda externa:** `Modules/Connector/Routes/api.php:112-117`, `Connector FollowUpController`, `Connector ContactController` (BLOQUEIO E4), `app/User.php:356` (KEEP).
