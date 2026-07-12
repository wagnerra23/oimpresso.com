---
page: /ads/admin/skills/{slug}/test
component: resources/js/Pages/ads/Admin/Skills/Test.tsx
related_prototype: n/a (herda PT-02 Formulário; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ADS
related_adrs: [76, 114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/skills/{slug}/test (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ADS/Http/Controllers/Admin/SkillsController@test` (GET, rota `ads.admin.skills.test`) + `@runTest` (POST). ADR 0076 Fase 3 — roda a version production da skill contra um prompt e salva o resultado em `mcp_skill_test_runs`.

---

## Mission
Deixar o admin exercitar uma skill antes de aprovar/promover: rodar a version production contra um prompt manual OU contra as últimas N conversas reais (com filtro `business_id` + PII redactor obrigatório), e ver latência, tokens, redactions de PII e preview do output. Fecha o loop "editei → testei → aprovo com evidência" exigido pela Approval queue.

---

## Goals — Features (faz)
- Form (`useForm`) com seletor de origem: prompt manual (3–8000 chars) ou "últimas N conversas reais".
- Modo conversas reais: N (1–50) + `business_id` opcional (default sessão), lendo `copiloto_mensagens` scopado por business com PII mascarado.
- Submit `POST /ads/admin/skills/{slug}/test` roda o teste e persiste em `mcp_skill_test_runs`.
- Lista de execuções recentes: id, data, latência (ms), tokens, contagem de PII redactions, preview de prompt e output.
- Badge de modo `DRY RUN` (sem chamar API) vs `LIVE`.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita a skill (isso é `Skills/Edit`) — testa a version production atual.
- ❌ Não aprova/promove com base no teste — só gera a evidência que a Approval queue consome.
- ❌ Não roda test em massa cross-tenant sem `business_id` — o modo conversas reais é scopado por business. [Tier 0 multi-tenant]
- ❌ Não envia PII crua pra API — redactor mascara CPF/CNPJ antes da chamada.

---

## UX targets
- p95 < 1500ms na tela (a chamada LIVE à API pode exceder — mostra "Rodando…") ; cabe em 1280px ; AppShellV2.

---

## Automation hooks (faz)
- `runTest` chama `SkillTestRunnerService` (Anthropic API em LIVE; fixture em DRY_RUN) e grava o run.
- Modo conversas reais lê as N últimas mensagens de user filtradas por `business_id` e aplica PII redactor.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não chama a API externa em DRY_RUN — retorna fixture (custo zero).
- ❌ Não roda teste sozinho/agendado — só por submit do humano.
- ❌ Não vaza conversa de um business pra outro — sem `business_id`, usa o da sessão.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Validar redação de PII no modo "conversas reais" com dado real biz=1
