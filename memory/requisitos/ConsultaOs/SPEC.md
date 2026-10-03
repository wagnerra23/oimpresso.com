---
id: requisitos-consulta-os-spec
module: ConsultaOs
version: "1.0"
last_updated: "2026-10-02"
anchor_format: "v1"
status: rascunho
owner: wagner
related_adrs: [0153-module-grade-rubrica-v1, 0154-module-grade-v2-na-justificado, 0155-module-grade-v3-sub-dimensoes-gate-ci]
na_justified:
  D3.b: "ConsultaOs é módulo público de consulta OS (cliente final consulta status via número). Desde 2026-10-02 lê as folhas de OS reais do Repair (US-CONSULTA-001 parcial — falta captcha e identificar a empresa). BRIEFING pós-migração é a US-CONSULTA-003."
  D6.a: "Portal público client-side state + fetch JSON API (não usa Inertia partial reload). `Inertia::render('ConsultaOs/Index')` leva só `buscaPorCelular` (leitura de config — exceção do defer pra config static); a busca vem de fetch('/consulta-os/buscar') retornando JsonResponse. Sem props paginadas/count()/with() eager — `Inertia::defer` inaplicável (análogo ao Connector REST API backend citado em ADR 0155 §188)."
---

<!-- schema-allowlist: US sob "## Roadmap (TODO migrar pra real)"; módulo mock-only/stub sem backlog ativo — as US-CONSULTA-NNN são itens de roadmap (migração futura pra real), não há "## User stories"/"## Backlog ativo" porque o módulo ainda não entrega valor de produto. Heading preservado pra não reestruturar o corpo. -->

# SPEC — Modules/ConsultaOs

## Visão

Módulo público (sem auth) para cliente final consultar o status de uma Ordem de Serviço (OS) por nº da OS, nº da venda ou celular. Desde 2026-10-02 lê as folhas de OS reais do `Modules/Repair` (US-CONSULTA-001) e é o portal do cliente do Repair: o antigo `/repair-status` redireciona pra cá.

## Arquitetura atual (2026-10-02)

- `ConsultaOsController` (index + buscar) → `ConsultaOsService` → `RepairConsultaOsRepository` (lê `repair_job_sheets` + status/marca/modelo/aparelho + `activity_log`)
- Sem entidades Eloquent próprias — usa `Modules\Repair\Entities\JobSheet`
- Contrato em `tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php` (lane sqlite do `ci.yml`)
- Sem `business_id` scope: o portal não sabe a empresa do cliente — pendência [W] na US-CONSULTA-001

## Roadmap (TODO migrar pra real)

### US-CONSULTA-001 · Substituir mock por query real em Modules/Repair `parcial`

Substituir mock por query real em `Modules/Repair` via Service read-only, com rate limit por IP + captcha.

**Aceite:** Dado um visitante sem login · Quando busca por nº da OS, nº da venda ou celular (este só com `repair.enable_repair_check_using_mobile_num`) · Então recebe as folhas de OS reais que casam com o critério, com **só** os campos que o `/repair-status` já mostrava (nº, marca, aparelho, modelo, série, status com cor, previsão e atividades) · E sem tipo válido ou com número vazio recebe 422 e o repositório não consulta · E o `/repair-status` redireciona pro `/consulta-os`. Casos: `UC-COS-01..11` em `resources/js/Pages/ConsultaOs/Index.casos.md`.

**Implementado em:** _parcial_ · `Modules/ConsultaOs/Repositories/RepairConsultaOsRepository.php` · `Modules/ConsultaOs/Services/ConsultaOsService.php` · `Modules/ConsultaOs/Http/Requests/ConsultaPublicaRequest.php` · `Modules/Repair/Http/Controllers/CustomerRepairStatusController.php` · `tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php` · verificado@2f38290 (2026-10-02) — fonte real ligada (decisão [W] 2026-10-02 "Ligar o ConsultaOs ao Repair"); rate-limit por IP já valia (`throttle:30,1`). Falta o **captcha** e falta **identificar a empresa**: um nº válido é procurado em todas as empresas, igual ao `/repair-status` — decisão [W] pendente (ver `prototipo-ui/cowork/Wagner/cowork-inbox/repair/playbook/_saida-04.md`).

**Testado em:** `tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php` (UC-COS-01..11)

### US-CONSULTA-002 · Canary 7d em ROTA LIVRE `pendente`

Canary 7d em ROTA LIVRE antes de outros tenants.

**Implementado em:** _pendente_ — rollout operacional que só faz sentido após US-CONSULTA-001 entregar a busca real; nenhum código correspondente hoje.

### US-CONSULTA-003 · Criar BRIEFING.md após migração real `pendente`

Criar `BRIEFING.md` após migração real (`na_justified D3.b` cai).

**Implementado em:** _pendente_ — a precondição (migração real US-CONSULTA-001) não ocorreu; o `na_justified.D3.b` segue vigente até a busca real entregar valor de produto. (Existe um `BRIEFING.md` de estado mock no módulo, mas esta US pede o briefing pós-migração-real, ainda não cabível.)

## N/A justificado

- **D3.b BRIEFING.md** — prematuro enquanto mock-only. Briefing canônico (1 página executiva) pressupõe capacidade de produto real entregando valor; mock não atende esse critério. Quando US-CONSULTA-001 for done, criar BRIEFING e remover N/A.
- **D6.a Inertia::defer** — portal público com client-side state + fetch JSON API. `index()` retorna `Inertia::render('ConsultaOs/Index')` SEM props (página React gerencia tudo via `useState`, busca dispara `fetch('/consulta-os/buscar')` retornando `JsonResponse`). Sem props pesadas pra deferir (sem `paginate()`, `count()`, `with()` eager-load, Service-DB call). Pattern análogo ao Connector REST API backend documentado em [ADR 0155 §188](../../decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md). Reavaliar após US-CONSULTA-001 caso payload passe a vir via Inertia props (em vez de JSON).

## Arquitetura — separation of concerns

| Camada | Arquivo | Responsabilidade |
|---|---|---|
| Inertia render (single page) | `ConsultaOsController@index` | Boot página React (prop única `buscaPorCelular`) |
| Busca pública (JSON) | `ConsultaOsController@buscar` | Recebe `ConsultaPublicaRequest` validado, devolve as OS públicas ou 404 |
| Validação anti-enumeration | `Http/Requests/ConsultaPublicaRequest` | `tipo` em lista fechada + `numero` não vazio, formato `[A-Za-z0-9/.-]`, `max:20` |
| Fonte de dados (whitelist) | `Repositories/RepairConsultaOsRepository` | Folhas de OS do Repair, payload montado campo a campo |
| Hooks UltimatePOS | `DataController` | `superadmin_package` + `user_permissions` + `modifyAdminMenu` (sidebar opt-out) |
| Boot módulo | `InstallController` extends `BaseModuleInstallController` | Install flow standard nWidart |
| Front-end (client-state) | `resources/js/Pages/ConsultaOs/Index.tsx` | `useState<Estado>` + `fetch` API; sem Inertia partial reload |

## Referências

- ADR 0153 — Module grade rubric v1
- ADR 0154 — Module grade v2 N/A justificado
- ADR 0155 — Module grade v3 sub-dimensões + N/A backward-compat (D6.a Inertia::defer)
