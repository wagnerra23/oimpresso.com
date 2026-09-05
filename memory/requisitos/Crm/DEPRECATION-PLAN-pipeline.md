---
id: requisitos-crm-deprecation-plan-pipeline
tipo: deprecation-plan
alvo: Pipeline CRM pré-venda (Modules/Crm parte B)
preserva: Cadastro de Cliente (parte A) — intocável
status: planejado
data: "2026-06-22"
gerado_por: agente deprecar-modulo
owner: W
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0105-cliente-como-sinal-guiar-sem-mandar, 0179-cliente-drawer-760px-substitui-show-fullpage, 0301-separar-cliente-deprecar-crm-pipeline]
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
| Services `BrLookupService`, `ContactBookingService` | `BrLookupService` = **A** (medido 2026-09-04) · `ContactBookingService` segue **ZONA CINZA** | `BrLookupService`: único consumidor de código é `ClienteLookupController` (A) — **fica em A, não move** (receita no §BLOQUEIOS). `ContactBookingService`: **não medido** |
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
| `BrLookupService` — usado por A? | **SIM — CONFIRMADO 2026-09-04** (era "provável"). Único consumidor de código é `ClienteLookupController` (A); **zero** no pipeline B. Fica em A | `Modules/Crm/Routes/web.php:93` · `ClienteLookupController::__construct` |
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
| `memory/requisitos/Crm/BRIEFING.md` | Banner 🔇 → "DEPRECADO via ADR NNNN" + link plano | E6 |
| `memory/requisitos/Crm/SCOPE.md` | Remover do `contains[]` os controllers de B; manter `Cliente*`/`ContactAddress` | E6 |
| `module.json` | Manter `active:1` (A vive) | — |

---

## Fase 5 — Risk register Tier 0

| # | Risk | Sev | Tier 0? | Mitigation | Etapa |
|---|---|---|---|---|---|
| 1 | DROP de coluna/linha em `contacts` quebra cadastro da Larissa | Crítico | **SIM** | NUNCA dropar `contacts`/colunas `crm_*`. PRESERVE in-place. Pest cross-tenant + smoke `/cliente` biz=4 | E3/E4 |
| 2 | Cross-tenant leak ao arquivar (dump sem filtro `business_id`) | Crítico | **SIM (0093)** | Dump particionado por business; Pest cross-tenant antes/depois | E3 |
| 3 | PII em `crm_call_logs`/`crm_proposals` em dump claro | Crítico | **SIM (LGPD)** | `PiiRedactor` (`App\Support\Privacy\PiiRedactor`); AES-256; retention | E3 |
| 4 | API externa Connector quebra (Delphi ainda chama) | Alto | parcial | Auditar `log.delphi` antes de remover `Schedule`/`CrmContact`. 410 só após confirmar morto | E4 BLOQUEIO |
| 5 | `down()` defeituosos (call_logs nome errado; followup_invoices/users vazios) | Alto | **SIM (proibicoes)** | Migration de remoção (E5) com `down()` reverso CORRETO; não confiar nas legacy | E5 |
| 6 | Schedule cron órfão (everyMinute!) se remover command sem tirar do schedule | Alto | não | Remover schedule + command no MESMO PR | E4 |
| 7 | `crm.*` permissions órfãs | Médio | não | Seed cleanup no MESMO PR + Pest | E5 |
| 8 | `BrLookupService` removido por engano quebra CEP/CNPJ do cadastro | Alto | não | ✅ **medido 2026-09-04: pertence a A** — fica em A, não entra em nenhuma etapa de remoção (receita no §BLOQUEIOS) | E1 ✅ |
| 9 | Reversibilidade | Médio | não | E1-E2 só comentam (revert = descomentar); DROP só após 30d flag-off + dumps | E1-E5 |
| 10 | Larissa biz=4 UX quebrada sem aviso | Alto | não | Query Fase 3 confirma biz=4 ~0 pipeline; canary 24h + aviso 7d | E2/E4 |

---

## Fase 6 — Roadmap 6 etapas (faseado, reversível, gates Wagner)

| Etapa | Tipo PR | LOC | Pré-req | Gate Wagner | Reversível? |
|---|---|---|---|---|---|
| **E1 — ADR deprecação + verificação rows** | docs + SELECT read-only staging | ~120 | Este plano aprovado; queries Fase 3 em réplica; ~~confirmar BrLookupService=A~~ ✅ **feito 2026-09-04** | ADR proposal→accepted. **Rows em biz pagante → BLOQUEIO (ARCHIVE indefinido)** | n/a |
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

1. Row count por business (queries Fase 3). 🔴 **aberto** — rodado no CT 100 em **2026-09-04**, mas
   **o CT 100 não pode responder esta pergunta**: nenhum dos seus bancos é réplica de prod (4 businesses
   fictícios de CI, zero pagante). O zero medido lá **não** autoriza DROP. Ver §Recibo abaixo.
2. Auditoria do consumidor externo Connector (`log.delphi`). 🔴 **aberto** — oráculo **verificado no
   código** e query rodada em **2026-09-04**; o vazio obtido mede a **instrumentação**, não o consumidor
   (zero linha `source='delphi_middleware'` em qualquer banco do CT 100). Ver §Recibo abaixo.
3. ~~Confirmar `BrLookupService` pertence a A.~~ ✅ **FECHADO em 2026-09-04** — pertence a A.

### Recibo do bloqueio 3 (fato datado — re-rodar em vez de confiar nesta linha)

Em **2026-09-04**, varredura contada no repo inteiro contra `origin/main`:

```
git grep -n "BrLookupService" origin/main    → 26 arquivos
```

Dos 26, **3 são código** (o resto é doc/ADR/handoff/skill): o próprio
`Modules/Crm/Services/BrLookupService.php`, o `Modules/Crm/Http/Controllers/ClienteLookupController.php`
(injeção no `__construct`) e `tests/Feature/Cliente/ClienteLookupCnpjCepTest.php` (instancia direto).
**Zero consumidor no pipeline B.** `ClienteLookupController` é classe **A** pela tabela §Fase 1 deste plano.

→ **Consequência para as etapas:** `BrLookupService` **não entra** em E4 (remover código) nem em nenhuma
etapa de remoção. O Risco 8 do §Fase 5 está mitigado por medição, não por promessa.

⚠️ O recibo mede o **repo**, não o mundo: se um consumidor novo nascer, a conclusão muda — por isso a
receita fica escrita aqui, para ser **re-rodada**, e não a conclusão sozinha.

### Oráculo do bloqueio 2 (nomeado em 2026-09-04 · **verificado no código em 2026-09-04**)

O plano pedia "auditar o consumidor externo Connector (`log.delphi`)" sem dizer **onde** olhar. Rastreado
no código: o alias `log.delphi` (`Modules/Officeimpresso/Providers/OfficeimpressoServiceProvider.php`)
resolve `Modules\Officeimpresso\Http\Middleware\LogDelphiAccess`, que grava em **`licenca_log`**
(`Modules\Officeimpresso\Entities\LicencaLog`, `protected $table`), com `endpoint` = `$request->path()`
(**sem** barra inicial), `http_status`, `business_id` e `created_at`.

Logo a pergunta *"o Delphi ainda chama a API CRM?"* é respondida por:

```sql
SELECT endpoint, COUNT(*) AS n, COUNT(DISTINCT business_id) AS bizs, MAX(created_at) AS ultima_chamada
FROM licenca_log
WHERE endpoint LIKE 'connector/api/crm%'
GROUP BY endpoint
ORDER BY ultima_chamada DESC;
```

Rodar em **réplica/staging** (nunca escrever em prod). Leitura do resultado: **0 linhas** ou
`ultima_chamada` antiga = candidato a `410 Gone` na E4; **qualquer linha recente** = BLOQUEIO mantido até
migrar o consumidor (Wagner + Felipe), como o §Fase 4 já manda.

As **duas pernas do oráculo foram verificadas em 2026-09-04** (varredura contada, comandos no §Recibo):

- o grupo `connector/api/crm` **passa mesmo** por `log.delphi` — `Modules/Connector/Routes/api.php:112`;
- o middleware grava **incondicionalmente** (sem flag, sem `if`), com `endpoint = $request->path()`
  **sem barra inicial** — `LogDelphiAccess.php:111`.

⚠️ **Mas a query sozinha não basta, e o motivo é medido:** `licenca_log` é escrita por **5 produtores**
com convenções de `endpoint` **diferentes** (§Recibo). Logo, todo uso desta query exige **antes** o
controle de instrumentação por `source='delphi_middleware'` — sem ele, ausência de linha mede o
produtor, não o consumidor (§5 2026-07-31).

### Recibo dos bloqueios 1 e 2 (medição no CT 100 — 2026-09-04)

> **Leia o ambiente antes de ler qualquer número.** Este recibo registra o que foi medido, **onde**, e o que
> aquele lugar consegue e **não** consegue responder. Nenhum DML foi executado — só `SELECT`. Nenhuma etapa
> E1–E6 foi executada.

#### O ambiente medido — e por que ele não fecha o bloqueio 1

O CT 100 expõe **4 bancos** com schema do oimpresso, e **nenhum é réplica nem anonimização de prod**:

```bash
tailscale ssh root@ct100-mcp 'PW=$(docker inspect oimpresso-staging-db --format "{{range .Config.Env}}{{println .}}{{end}}" | grep -m1 MARIADB_ROOT_PASSWORD | cut -d= -f2); docker exec -i oimpresso-staging-db mariadb -uroot -p"$PW" -e "SHOW DATABASES;"'
# -> oimpresso_staging | oimpresso_qa | oimpresso_kbf | oimpresso_kb_flake
```

`oimpresso_staging` (o banco que o container `oimpresso-staging` usa — `DB_DATABASE` do `.env` dele) tem
**387 tabelas** e os seguintes dados: **4 businesses, 1 contact, 2 transactions**. Os 4 businesses são
`CI Biz` (1), `CI Biz 2` (2), `CI Tenant 98 (ficticio)` (98) e `CTM Test Biz Adversario#99` (99) — todos
criados em 2026-08-20/24 pela receita de CI. **Não existe `business_id=4` (ROTA LIVRE), nem 164, nem
qualquer business pagante.** `oimpresso_qa` e `oimpresso_kbf` têm o mesmo perfil (3–4 businesses de CI).

→ **Consequência dura:** a regra de ouro do §Fase 3 fala em *"`n>0` em business **pagante**"*. No ambiente
medido não há business pagante algum, logo **`n=0` aqui não é evidência de tabela morta** — mede o seed do
CI, não o cliente. Ler esse zero como "candidata a DROP direto" seria a §5 2026-07-31 (vazio que era falha
de medição) na forma mais cara possível: perda de dado de cliente.

#### Bloqueio 1 — o que a medição de fato produziu

As tabelas **existem** no schema e estão **todas vazias neste ambiente** (`COUNT(*)`, não `table_rows`):

| Tabela | `COUNT(*)` em `oimpresso_staging` |
|---|---|
| `crm_schedules` · `crm_schedule_users` · `crm_schedule_logs` | 0 · 0 · 0 |
| `crm_followup_invoices` · `crm_call_logs` · `crm_proposals` | 0 · 0 · 0 |
| `crm_proposal_templates` · `crm_campaigns` · `crm_marketplaces` | 0 · 0 · 0 |
| `crm_contact_person_commissions` · `crm_deals` · `crm_lead_users` | 0 · 0 · 0 |
| `contacts WHERE type='lead'` | 0 (nenhum grupo retornado) |

→ O que isto **prova**: as queries do §Fase 3 são **executáveis** e o schema bate com a tabela de decisão.
→ O que isto **não prova**: nada sobre `crm_deals`/`crm_marketplaces` serem "DROP direto". Aquela
  conclusão exige `n=0` **global em prod**, e prod não foi medido.

⚠️ **Desvio de contagem, registrado sem reescrever o TL;DR:** o §TL;DR diz *"9 tabelas"*; o schema tem
**12** tabelas `crm_*` e o §Fase 3 lista as **12** (3 delas marcadas `pivot` — 12−3=9 explica o número,
mas o texto não diz isso). Contagem reproduzível:
`SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='oimpresso_staging' AND SUBSTRING(table_name,1,4)='crm_'` → **12**.

#### Bloqueio 2 — o vazio é da instrumentação, e isso foi **medido**

O plano mandava conferir que o middleware grava no ambiente consultado antes de ler vazio como "morto".
Conferido — e **reprovou**:

| Banco | linhas em `licenca_log` | `source` das linhas |
|---|---|---|
| `oimpresso_staging` | **0** | — |
| `oimpresso_kbf` | **0** | — |
| `oimpresso_qa` | **188** | `desktop_audit` (**100%**) |

**Zero linha `source='delphi_middleware'` em qualquer banco do CT 100.** As 188 do `oimpresso_qa` vieram do
`LicencaAuditService` (`source='desktop_audit'`), não do middleware do oráculo — e por isso trazem
`endpoint` em **outro formato** (`/api/sync`, `/oauth/token`, **com** barra).

→ **A query do plano devolveu 0 linhas** — e esse 0 **não significa "o Delphi não chama mais"**. Significa
  que o produtor daquela linha nunca rodou aqui. Controle negativo confirmando:
  `SELECT COUNT(*) FROM oimpresso_qa.licenca_log WHERE endpoint LIKE 'connector/api%'` → **0**.

#### Refinamento do oráculo: `licenca_log` tem **5 produtores**, não 1

Varredura contada no repo (`rg --hidden -g '!.git/**' "LicencaLog::create"` → 5 arquivos de código + 2 de teste):

| Produtor | `source` | formato de `endpoint` |
|---|---|---|
| `Http/Middleware/LogDelphiAccess.php:111` | `delphi_middleware` | `$request->path()` — **sem** barra |
| `Http/Middleware/LogDesktopAccess.php:68` | `api_middleware` | `$request->path()` — **sem** barra |
| `Services/LicencaAuditService.php:60` | `desktop_audit` | `$payload['endpoint']` — **livre** |
| `Listeners/LogPassportAccessToken.php:89` | `passport_event` | literal `'/oauth/token'` — **com** barra |
| `Console/ParseLicencaLogCommand.php` | `log_parser` | do arquivo de log |

→ **Por que isto importa:** o `LIKE 'connector/api/crm%'` do oráculo está **correto para o produtor certo**
  (o `LogDelphiAccess` grava sem barra), mas a tabela é **compartilhada** e sem convenção única. Rodar
  a query e ver 0 sem antes contar `source='delphi_middleware'` mede o produtor, não o consumidor.

#### Receita reexecutável — rodar **nesta ordem** (o controle vem primeiro)

```sql
-- PASSO 1 (CONTROLE, obrigatorio): o produtor do oraculo escreve neste ambiente?
--   n = 0  -> PARE. O passo 2 nao tem significado aqui (mede a instrumentacao, nao o consumidor).
SELECT COUNT(*) AS n, MIN(created_at) AS mais_antiga, MAX(created_at) AS mais_recente
FROM licenca_log WHERE source = 'delphi_middleware';

-- PASSO 2 (a pergunta): o Delphi ainda chama a API CRM?
SELECT endpoint, source, COUNT(*) AS n, COUNT(DISTINCT business_id) AS bizs,
       MAX(created_at) AS ultima_chamada
FROM licenca_log
WHERE endpoint LIKE 'connector/api/crm%' AND source = 'delphi_middleware'
GROUP BY endpoint, source ORDER BY ultima_chamada DESC;

-- PASSO 3 (rede contra a barra): pega linha que outro produtor tenha gravado com '/' na frente
SELECT endpoint, source, COUNT(*) AS n, MAX(created_at) AS ultima_chamada
FROM licenca_log WHERE endpoint LIKE '%connector/api/crm%'
GROUP BY endpoint, source ORDER BY ultima_chamada DESC;
```

Transporte (aspas aninhadas colapsam — passar o SQL por **stdin**, nunca inline):

```bash
cat query.sql | tailscale ssh root@ct100-mcp 'PW=$(docker inspect oimpresso-staging-db --format "{{range .Config.Env}}{{println .}}{{end}}" | grep -m1 MARIADB_PASSWORD | cut -d= -f2); docker exec -i oimpresso-staging-db mariadb -ustaging -p"$PW" oimpresso_staging -t'
```

#### O que **de fato** fecharia cada bloqueio

| # | Fecha quando | Onde, hoje |
|---|---|---|
| 1 | `COUNT(*)` por `business_id` das 12 `crm_*` medido num ambiente que **contenha business pagante** (réplica ou dump anonimizado de prod) | **Não existe** no CT 100 (medido: `SHOW DATABASES` + `business` dos 4 bancos). Exige réplica — decisão [W] |
| 2 | Passo 1 da receita retornar `n>0` **e** o passo 2 rodar num ambiente que o Delphi de fato chama | Idem: `delphi_middleware` = 0 linha no CT 100. O ambiente que o Delphi chama é **prod** |

⚠️ **O §Fase 3 proíbe rodar em prod** (*"rodar em réplica/staging, nunca em prod"*). Como o único ambiente
com o dado é prod e a réplica não existe, **os dois bloqueios ficam abertos por falta de ambiente, não por
falta de query** — e destravá-los é decisão [W]: provisionar réplica/dump anonimizado, ou autorizar
`SELECT` read-only em prod com janela combinada. Nenhuma das duas foi feita aqui.

⚠️ Como no bloqueio 3: este recibo mede **os bancos do CT 100 em 2026-09-04**, não o mundo. A receita fica
escrita para ser **re-rodada** — nunca a conclusão sozinha.

## Refs

- **ADRs:** 0093 (Tier 0), 0094 (Constituição v2), 0105 (cliente como sinal), 0179/0185/0186/0197 (cadastro A — intactos).
- **Cadastro A (KEEP):** [SPEC Cliente](../Cliente/SPEC.md), `Modules/Crm/Http/Controllers/Cliente*Controller.php`, `app/Contact.php`, `app/ContactAddress.php`, rotas `/cliente/*`.
- **Pipeline B (TARGET):** `Modules/Crm/Routes/web.php:24-80`, `nav.blade.php`, `CrmServiceProvider::registerScheduleCommands()`, entidades/serviços/migrations `crm_*`.
- **Borda externa:** `Modules/Connector/Routes/api.php:112-117`, `Connector FollowUpController`, `Connector ContactController` (BLOQUEIO E4), `app/User.php:356` (KEEP).
