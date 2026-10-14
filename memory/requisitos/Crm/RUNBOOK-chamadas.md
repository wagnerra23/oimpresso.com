---
title: "RUNBOOK — Registro de chamadas do CRM (Blade → Inertia)"
module: Crm
tela: Crm/Chamadas/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Migração MWART /crm/call-log → Crm/Chamadas/Index

> Thread `crm/playbook/12` · processo [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md).
> Fonte de design: `prototipo-ui/cowork/Wagner/crm-blade-telas.jsx` → `Chamadas()` (rota `crm-chamadas`).
> Alvo medido: `governance/design/targets/crm--chamadas--index.alvo.json` (thread A2) — 6 seções: header · nav · filtros · toolbar · grade · rodape.

## 1. Tela
- **Legacy:** Blade `crm::call_logs.index` (DataTables + exclusão em massa por formulário).
- **Inertia (F3):** `Modules/Crm/Resources/js/Pages/Crm/Chamadas/Index.tsx` (+ charter + casos ao lado).
- **Controller:** `Modules\Crm\Http\Controllers\CallLogController` — `index` (lista), `massDestroy` (excluir selecionados), `allUsersCallLog` (widget do painel).
- **Rotas:** `GET /crm/call-log` · `POST /crm/mass-delete-call-log` (throttle 30/min).
- **Origem dos dados:** os registros chegam pelo app (API `Modules/Connector/.../Api/Crm/CallLogsController`); a tela não cria registro.

## 2. Estado final esperado
`GET /crm/call-log` responde Inertia `Crm/Chamadas/Index` **só quando a flag `useV2CrmChamadas` está ligada** para o negócio.
Desligada (default), segue a Blade. O ajax do DataTables (sem `X-Inertia`) continua recebendo JSON.

## 3. Fatias (1 PR por fase, ≤300 linhas)
| fase | o quê | estado |
|---|---|---|
| F1 | este RUNBOOK + [`chamadas-parity.md`](./chamadas-parity.md) | este PR |
| F2 | `CrmChamadasBaselineTest` (tenant 98 × 99) na lane `verticais-pest` | pendente |
| F3 | ramo Inertia atrás da flag + Page (filtros · toolbar · grade com seleção · rodapé de exclusão) + charter + casos + contrato | pendente |
| F4 | smoke biz=1 com a flag só para biz=1 | pendente |
| F5 | cutover — decisão [W] | — |

## 4. Escopo da F3
- **Lista:** mesma consulta (`business_id` da sessão; sem `crm.view_all_call_log` vê só o que criou; filtros contato, "registro criado por" e intervalo de datas), paginada no servidor, prop **deferida**.
- **Excluir selecionados:** só para quem a Blade mostra o botão (`is_admin`), pela mesma rota `massDestroy`.
- Sem drawer: o protótipo não tem detalhe por chamada.

## 5. Multi-tenant (Tier 0)
- Lista e exclusão partem do `business_id` da sessão (`CrmCallLog::where('business_id')`).
- ⚠️ `massDestroy` só exige `crm_module` (FormRequest): quem só vê os próprios registros apaga o de colega do mesmo negócio pelo id. Ver paridade §Achados (B2).

## 6. Pegadinhas
- **A tela está desligada por config:** `config/constants.php` fixa `enable_crm_call_log => false` e o `index` dá 403 sem ela — hoje ninguém abre `/crm/call-log` em produção. A F3 mantém o mesmo portão; ligar é decisão [W]. Testes ligam com `config([...])`.
- **Inertia × DataTables:** decidir pelo `X-Inertia`, não por `ajax()` (§5 2026-09-08).
- Datas saem pelo `format_datetime` legado; duração por `CarbonInterval::forHumans()` (em inglês). A Page formata HH:MM:SS a partir de `duration` (segundos).
- O filtro "Registro criado por" filtra `created_by`, não `user_id`.

## 7. Teste
`Modules/Crm/Tests/Feature/CrmChamadas*Test.php`, lane `verticais-pest` (MySQL), tenant 98 (99 como "outro negócio"). Nunca local.

## 8. Rollback
Flag OFF devolve a Blade. F1/F2 não tocam código de produção.
