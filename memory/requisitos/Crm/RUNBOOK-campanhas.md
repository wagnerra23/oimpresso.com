---
title: "RUNBOOK — Campanhas do CRM (Blade → Inertia)"
module: Crm
tela: Crm/Campanhas/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Migração MWART /crm/campaigns → Crm/Campanhas/Index

> Thread `crm/playbook/11` · processo [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md).
> Fonte de design: `prototipo-ui/cowork/Wagner/crm-blade-telas.jsx` → `Campanhas()` (rota `crm-campanhas`).
> Alvo medido: `governance/design/targets/crm--campanhas--index.alvo.json` (thread A2) — 5 seções: header · nav · filtros · toolbar · grade.

## 1. Tela
- **Legacy:** Blade `crm::campaign.index` (DataTables `campaigns_table`, `crm.js:1109`) + modal `crm::campaign.show`; páginas `create`/`edit`.
- **Inertia (F3):** `Modules/Crm/Resources/js/Pages/Crm/Campanhas/Index.tsx` (+ charter + casos ao lado).
- **Controller:** `Modules\Crm\Http\Controllers\CampaignController` — `index` (lista), `show` (modal), `create`/`store`/`edit`/`update`/`destroy`, `sendNotification`.
- **Rota:** `GET /crm/campaigns` (resource). Envio: `GET /crm/{id}/send-campaign-notification` (throttle 20/min).

## 2. Estado final esperado
`GET /crm/campaigns` responde Inertia `Crm/Campanhas/Index` **só quando a flag `useV2CrmCampanhas` está ligada** para o negócio.
Desligada (default), segue a Blade. O ajax do DataTables (sem `X-Inertia`) continua recebendo JSON nos dois casos.

## 3. Fatias (1 PR por fase, ≤300 linhas)
| fase | o quê | estado |
|---|---|---|
| F1 | este RUNBOOK + [`campanhas-parity.md`](./campanhas-parity.md) | este PR |
| F2 | `CrmCampanhasBaselineTest` (tenant 98 × 99) na lane `verticais-pest` | pendente |
| F3 | ramo Inertia atrás da flag + Page (filtros · toolbar · grade · drawer de detalhe) + charter + casos + contrato | pendente |
| F4 | smoke biz=1 com a flag só para biz=1 | pendente |
| F5 | cutover — decisão [W] | — |

## 4. Escopo da F3 (só leitura)
- **Lista:** mesma consulta do DataTables (`business_id` da sessão, "só as minhas" para `crm.access_own_campaigns`, filtro `campaign_type`), paginada no servidor, prop **deferida**.
- **Drawer PT-02** (substitui o modal `show`): tipo, assunto, enviada em, criada por/em, quantidade de contatos, corpo **como texto**.
- **Escrita fica na Blade:** Adicionar, Editar, Excluir e **Enviar notificação** levam às páginas/rotas atuais (`?classico=1`). A Page nova **não chama** `sendNotification`.

## 5. Multi-tenant (Tier 0)
- Toda leitura parte de `business_id` da sessão; `Campaign` tem `HasBusinessScope` além do `where` explícito.
- "Só as minhas" (`created_by = auth()->id`) vale na lista, no `show`, no `edit`, no `update` e no `destroy`.
- ⚠️ `show` resolve os contatos com `CrmContact::find($campaign->contact_ids)` — `Contact` **não** tem escopo global, e `StoreCampaignRequest` valida `contact_id.*` só como `integer`. Ver paridade §Achados (A1). O drawer da F3 **não** lista contatos por esse caminho: mostra a contagem.

## 6. Envio (nunca em teste)
- `sendNotification` → `__sendCampaignNotification` dispara SMS (`NotificationUtil::sendSms`) ou e-mail (`Notification::send`).
- **Proibido** chamar a rota de envio em Pest, smoke ou F4. Testes que precisem passar perto usam `Notification::fake()` e nunca atingem SMS.

## 7. Teste
- F2/F3: `Modules/Crm/Tests/Feature/CrmCampanhas*Test.php`, lane `verticais-pest` (MySQL), tenant 98 (99 como "outro negócio"). Nunca local: CI ou CT 100.

## 8. Pegadinhas
- **Inertia × DataTables:** o Inertia manda `X-Requested-With` junto do `X-Inertia`; decidir pelo `X-Inertia`, não por `ajax()` (§5 2026-09-08).
- `show.blade.php` imprime `email_body`/`sms_body` com `{!! !!}` (HTML cru). A Page não usa `dangerouslySetInnerHTML`.
- `created_at` sai pelo `format_date` legado (shift +3h, ADR 0066). A Page formata o valor gravado.
- `destroy` só responde a ajax; visita comum devolve corpo vazio.

## 9. Rollback
Flag OFF devolve a Blade. Reverter o PR da F3 remove o ramo Inertia; F1/F2 não tocam código de produção.
