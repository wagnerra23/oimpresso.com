---
page: /atendimento/canais/jana-templates
component: resources/js/Pages/Atendimento/JanaTemplates.tsx
owner: wagner
status: live
last_validated: "2026-05-12"
parent_module: Whatsapp
parent_capterra: memory/requisitos/Whatsapp/CAPTERRA-FICHA.md
supersedes: resources/js/Pages/Whatsapp/Settings.charter.md
related_adrs: [35, 48, 93, 135]
tier: B
charter_version: 1
---

# Page Charter — `/atendimento/canais/jana-templates`

> Define as invariantes da tela de toggle Bot Jana + Templates HSM. Sucessora simplificada de `/whatsapp/settings` (deprecated US-WA-067, removida US-WA-070).

---

## Mission

Permitir que o admin do business configure 2 coisas: (1) ligar/desligar o bot Jana global do business e (2) nomear os templates HSM aprovados pra disparos automáticos de repair/billing.

Drivers Whatsapp (Z-API/Meta/Baileys) moved para `/atendimento/canais` (ADR 0135 Omnichannel).

---

## Goals

- **Toggle bot Jana** global do business (`whatsapp_business_configs.bot_enabled`)
- **4 templates HSM** nomeados: `repair_ready`, `repair_waiting_parts`, `billing_due`, `billing_paid`
- **Multi-tenant Tier 0** ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)) — global scope `business_id` em queries

---

## Non-Goals

- ❌ Configurar drivers (Z-API/Meta/Baileys) — vive em `/atendimento/canais/{id}`
- ❌ Conectar/desconectar canal — vive em `/atendimento/canais/{id}/connect`
- ❌ ACL per-canal (quem vê fila X) — vive em `/atendimento/canais/{id}` tab Usuários (US-WA-068)
- ❌ Override bot per-contact — vive em conversa no inbox via `/config bot=off` (US-WA-077)
- ❌ Sincronizar templates HSM com Meta automaticamente — `/whatsapp/templates` faz isso (Controller separado)

---

## UX Targets

- Tela cabe em 1280px sem scroll horizontal (cliente piloto ROTA LIVRE)
- Salvar templates < 500ms ack
- Sem erros JS console

---

## UX Anti-patterns

- ❌ Mostrar fields de driver/credenciais nessa tela
- ❌ Confirmação dupla pra mudar nome de template (low-risk, 1 click)
- ❌ Validação que rejeita nome de template não-cadastrado na Meta (Meta valida na hora do envio — UX ruim falhar aqui)

---

## Automation Hooks

- POST `route('atendimento.canais.jana_templates.update')` — Inertia form, 5 campos
- Salva direto em `whatsapp_business_configs` (Model intocado pós US-WA-067)

---

## Automation Anti-hooks

- ❌ Não dispara conexão de driver nesta tela (Controller só persiste 5 campos)
- ❌ Não modifica config de canal individual

---

## Métricas vivas (Pest GUARD)

- (US-WA-070) `JanaTemplatesControllerTest::it_persists_5_fields()` — POST persiste só os 5 campos esperados
- (US-WA-070) `JanaTemplatesControllerTest::it_isolates_by_business_id()` — biz=99 não vê config de biz=1
- (US-WA-070) `JanaTemplatesRedirectTest::it_redirects_legacy_settings()` — GET `/whatsapp/settings` retorna 301 pra `/atendimento/canais/jana-templates`

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-12 | Wagner + Opus 4.7 | Charter v1 — supersedes Settings.charter.md v2 deprecated. Tela nasce simplificada (só Bot+Templates) após drivers migrarem pra Canais (US-WA-070 / ADR 0135). |
