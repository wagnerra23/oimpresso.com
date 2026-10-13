---
id: requisitos-crm-campanhas-parity
titulo: Paridade de migração — /crm/campaigns (Campanhas) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-13'
tela: /crm/campaigns
related:
  - ../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-campanhas.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
---

# Paridade — Campanhas (`/crm/campaigns`)

## Metadados

- **Tela React:** `Modules/Crm/Resources/js/Pages/Crm/Campanhas/Index.tsx` (F3 — ainda não existe)
- **Blade legado:** `Modules/Crm/Resources/views/campaign/index.blade.php` + `show.blade.php` (modal) + `create`/`edit`
- **Controller:** `Modules\Crm\Http\Controllers\CampaignController`
- **Protótipo:** `prototipo-ui/cowork/Wagner/crm-blade-telas.jsx` → `Campanhas()`
- **Auditado em:** `2026-10-13` · **por:** [CL]

## Mapa campo-a-campo

| # | Feature do Blade | Na F3? | Evidência | Severidade se perdido | Defendido por |
|---|---|---|---|---|---|
| 1 | Sem `crm_module` na assinatura (e sem superadmin) → 403 | ✅ mesmo método | `index()` | **alta** | F2 baseline |
| 2 | Sem `crm.access_all_campaigns` nem `access_own_campaigns` → 403 | ✅ mesmo método | `index()` | **alta** | F2 baseline |
| 3 | Lista só campanhas do negócio da sessão | ✅ mesma consulta | `where('business_id')` + `HasBusinessScope` | **alta** | F2 baseline (T0) |
| 4 | Só `access_own_campaigns` → só as criadas por ele | ✅ mesma consulta | `where('created_by')` | **alta** | F2 baseline |
| 5 | Filtro "Tipo de campanha" (SMS / E-mail / Todos) | ✅ | `campaign_type` | média | F2 baseline |
| 6 | Colunas: Ação · Nome · Tipo · Criado por · Criado em | ✅ | `index.blade.php:34-38` | média | UC da F3 |
| 7 | Selo "Enviada" ao lado do nome quando `sent_on` | ✅ | `editColumn('name')` | média | UC da F3 |
| 8 | Ação Ver (modal `show`) | ✅ drawer PT-02 | `show()` | média | UC da F3 |
| 9 | Ação Editar só se não enviada | ✅ link para a Blade | `addColumn('action')` | média | UC da F3 |
| 10 | Ação Excluir (ajax, confirmação) | 🔁 Blade (`?classico=1`) | `destroy()` | média | F2 baseline (escopo) |
| 11 | Ação Enviar notificação só se não enviada | 🔁 Blade — a Page não envia | `sendNotification()` | **alta** (dispara SMS/e-mail) | — |
| 12 | Botão Adicionar | ✅ link para `create` | `index.blade.php:24` | baixa | UC da F3 |
| 13 | Modal: tipo, enviada em, assunto, corpo, atividade/dias, clientes, leads, criada por/em | ✅ drawer (contatos como contagem) | `show.blade.php` | média | UC da F3 |
| 14 | Busca textual do DataTables | ✅ busca no nome | DataTables | baixa | UC da F3 |
| 15 | Protótipo: coluna "Contatos" (quantidade) | ✅ novo (do protótipo) | `Campanhas()` cols | baixa | UC da F3 |

## Achados (não corrigidos neste PR)

- **A1 · Tier 0.** `StoreCampaignRequest` aceita `contact_id.*`/`lead_id.*` como qualquer inteiro, e o `update` usa `Request` cru. Um id de contato de **outro negócio** é gravado em `contact_ids`; `show` o resolve por `CrmContact::find()` (sem escopo) e `__sendCampaignNotification` envia SMS/e-mail a ele. A F2 prende o comportamento de leitura; o conserto (validar contra o negócio da sessão) é PR próprio da thread.
- **A2.** `sendNotification` checa só `crm_module`, não `access_all/own_campaigns`: quem só vê as próprias pode disparar a de um colega do mesmo negócio pelo id.
- **A3.** `show.blade.php` imprime `email_body`/`sms_body` com `{!! !!}` — HTML cru gravado por quem cria a campanha.

## Fora de escopo da F3
Criar, editar, excluir e enviar continuam na Blade até fatia própria.
