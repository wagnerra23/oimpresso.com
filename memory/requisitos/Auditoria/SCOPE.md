---
module: Auditoria
purpose: "Quem mudou este registro, o quê e quando? — lê e investiga a trilha por-registro (activity_log) com isolamento multi-tenant e guarda o registry do que a lei não deixa desfazer. A reversão está codificada (RevertService) porém desligada do caminho HTTP: POST /{id}/revert responde 501."
migracao_ui: "concluido — 0 Blade servido"
contains:
  - "AuditoriaController (index · show · revert)"
  - "AuditoriaLiberado — middleware das leituras: pacote auditoria_module + auditoria.view (superadmin passa)"
  - "AuditEntryService — leitura paginada e detalhe de activity_log"
  - "RevertService — undo restaurando properties.old + registro do próprio undo"
  - "RevertCheck — veredito allow/deny com OTel span"
  - "unrevertibleRegistry() — whitelist do que NUNCA reverte (append-only legal/fiscal)"
  - "Telas Auditoria/Index + Auditoria/Detail"
  - "DataController"
  - "InstallController"
  - "Comandos artisan auditoria:health e auditoria:backfill-business-id"
  - "FormRequests de revert, filtro, export, lote e notas (só RevertActivityRequest está ligado a rota)"
  - "AuditNote — entidade sem rota; tabela auditoria_audit_notes ausente de produção em 2026-07-30"
  - "Config/config.php e Config/retention.php"
not_contains:
  - "EMITIR o log (trait LogsActivity nos Models de cada módulo) → o módulo dono do Model emite; Auditoria LÊ"
  - "Trilha de chamada de tool MCP (mcp_audit_log) → Modules/Jana"
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/Forja"
  - "MCP server admin → Modules/Forja"
trust_required: L3
owner: wagner
permission_prefix: auditoria.*
charter_adr: 0094
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
url_prefixes:
  - /auditoria/*
  - /reports/activity-log (rota legacy — 301 pra /auditoria, em Routes/web.php do modulo; o NUCLEO registra a MESMA URI em routes/web.php e a do MODULO e a que responde: RouteCollection::addToCollections chaveia por metodo+URI e SOBRESCREVE, entao quem registra por ultimo vence — e o proprio nucleo documenta o desfecho em AdminSidebarMenu.php, "sobrescreveu"; a rota do nucleo e codigo morto)
drift_alerts: []
---

# Modules/Auditoria

## Missão

**Interface humana e guarda da trilha por-registro do ERP.** O `activity_log` (Spatie) grava *o que mudou em cada registro*; este módulo é quem **lê, investiga e reverte** — e quem **impede** a reversão do que a lei ou o fisco não deixam desfazer.

[W], 2026-07-30: *"ele registra as alterações em cada registro é super importante"* · *"não pode apagar"*.

## Fronteira — quem EMITE × quem LÊ

Esta é a distinção que a v1 errava, e o erro custou uma tentativa de deleção ([lápide §5](../../proibicoes.md)):

| Papel | Onde vive | Exemplo |
|---|---|---|
| **EMITIR** o registro | trait `LogsActivity` no Model de **cada módulo** | `App\Transaction`, `App\Contact`, `App\Product` |
| **LER · INVESTIGAR** | **aqui** | `/auditoria` · `/auditoria/{activityId}` |
| **REVERTER** (codificado, rota desligada) | **aqui** | `POST /{activityId}/revert` respondia 501 em 2026-11-09; decisão D1 de [W] pendente |

O módulo **não tem tabela própria com dado** — `auditoria_audit_notes` nunca chegou a produção e **não é o valor dele**. Ele vive do **`activity_log`**: em 2026-07-30, **117.510 linhas** com escrita no mesmo dia. Quem for medir a relevância deste módulo: **meça `activity_log`, não a tabela do módulo.**

## O que ele protege (por isso é Tier 0-adjacente)

Tudo nesta seção descreve `RevertService`, que em 2026-11-09 não era chamado por rota nenhuma. `canRevert()` nega nesta ordem:

1. **Multi-tenant Tier 0** — `activity.business_id ≠ user.business_id` → nega ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)). Sem isso o undo vira vazamento cross-tenant com cara de feature.
2. **Subject ausente** — sem `subject_type`/`subject_id`, classe inexistente ou registro apagado.
3. **`unrevertibleRegistry()`** — bloqueia `Marcacao` e `BancoHorasMovimento` (Portaria MTP 671/2021), documentos fiscais, baixa de título, OS com NFSe e `Transaction` com pagamento posterior. Em 2026-11-09 só a última tinha efeito, e as classes reais entraram em 2026-11-10 ([SPEC §Estado real](SPEC.md#estado-real-medido-em-2026-11-09)). No mesmo dia o serviço passou a negar registro de outra empresa, segunda reversão, campo de valor ou estoque e mudança de etapa FSM.
4. **Janela e permissão** — `auditoria.revert.unlimited`, `.any` (30 dias) ou `.own` (própria ação, 24 horas).

Depois de `canRevert`, `revert()` exige razão com 10 ou mais caracteres e `properties.old` preenchido. O undo **também é auditado**: cria uma nova `Activity` registrando quem reverteu e por quê.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Regra curta: **se o assunto é gravar o log, é do módulo dono do Model; se é ler, investigar ou desfazer, é aqui.**

---

- **v2.1.0** (2026-11-09) — `contains` passa a listar comandos, FormRequests, `AuditNote` e configs. Sai número de linha que apodrecia. A reversão fica registrada como desligada da rota, e o registry com o efeito medido.
- **v2.0.0** (2026-07-30) — Corrige o `not_contains` que expulsava o núcleo do módulo (declarava Activity Log per-Model como fora de escopo, quando `/{activityId}` e `/{activityId}/revert` sempre foram por-registro). Esse erro sustentou a conclusão "módulo vazio" numa tentativa de deprecação barrada por [W]. Adiciona a fronteira emitir×ler, o registry de irreversíveis e os 3 eixos de negação do revert.
- **v1.0.0** (2026-05-20) — SCOPE.md inicial gerado durante PR #1183 (Fiscal cockpit) pra desbloquear `check-scope --strict` no CI.
