---
page: /repair/status
component: resources/js/Pages/Repair/Status/Index.tsx
bundle_source: repair-page.jsx
owner: wagner
status: live
last_validated: "2026-05-07"
parent_module: Repair
parent_capterra: memory/requisitos/Repair/CAPTERRA-FICHA.md
related_adrs: [101]
tier: A
charter_version: 1
---

# Page Charter — /repair/status (stub F1)

> **Status:** stub estruturado em F1. Detalhamento (UX targets quantitativos + métricas Pest reais) entra em F1.5/F2 quando dono validar.

---

## Mission

Configurar os status que ordens de serviço (Repair) podem assumir — CRUD simples administrativo.

---

## Goals — Features (faz)

- Listar status com cor + sort_order + flag is_completed
- Botão "Novo status" → `/repair/status/create`
- Empty state quando lista vazia
- Tabela com colunas: Nome, Cor (swatch + hex), Ordem, Concluído?
- Multi-tenant: dados scopados por `business_id`

---

## Non-Goals — Features (NÃO faz)

- ❌ CRUD inline (criar/editar via rotas dedicadas Blade)
- ❌ Drag-and-drop reorder (usar campo sort_order numérico)
- ❌ Mover OS entre status (essa é tela de config, não de OS)
- ❌ Histórico de mudanças do status (audit fica em outro lugar)
- ❌ Bulk actions (usuário edita 1 por vez)

---

## UX Targets

- Tela admin (baixo volume de uso) — p95 < 1500ms aceitável
- 0 erros JS console
- Tabela cabe em 1280px sem scroll horizontal
- Cor do status visível como swatch (≥16px) ao lado do hex
- Botão "Novo status" sempre visível no header

---

## UX Anti-patterns

- ❌ Modal pra editar (rota dedicada existe)
- ❌ Confirmação dupla em ações destrutivas (delete vai pra outra tela)
- ❌ Cor sem swatch visual (só hex confunde usuário)

---

## Automation Hooks

- Endpoint `Status::index()` no Controller Repair
- Multi-tenant: global scope `business_id`

---

## Automation Anti-hooks

- ❌ Não dispara nada ao abrir (read-only puro)
- ❌ Não muda status de OS existente (config-only)
- ❌ Não escreve no banco
- ❌ Não acessa status de outro `business_id`

---

## Métricas vivas (Pest GUARD)

Contrato executável em [`Index.casos.md`](Index.casos.md), defendido por
[`Modules/Repair/Tests/Feature/RepairStatusContratoTest.php`](../../../../../Modules/Repair/Tests/Feature/RepairStatusContratoTest.php)
— 6 UCs; run no CT 100 em 2026-09-05: **6 passed, 17 assertions, 0 skipped**.

Os três anti-hooks desta tela têm, cada um, o seu teste:

| Anti-hook do charter | UC | Teste |
|---|---|---|
| não acessa status de outro `business_id` | UC-RSTIDX-03 | *"status de outro tenant não aparece na lista"* |
| não escreve no banco (read-only puro) | UC-RSTIDX-04 | *"abrir a tela não escreve nada no catálogo"* |
| não dispara nada ao abrir | UC-RSTIDX-05 | *"abrir a tela não dispara e-mail nem notificação"* |

Mais: ordenação por `sort_order` (UC-RSTIDX-01), cor preservada como está no banco (UC-RSTIDX-02) e
gate de permissão (UC-RSTIDX-06).

> ⚠️ **Correção de fato — o que esta seção afirmava antes era falso.** Até 2026-09-05 ela listava
> `RepairStatusCharterTest::it_does_not_mutate_state()`, `::it_does_not_emit_emails()` e
> `::it_isolates_by_business_id()` como "(stub)". Esses testes **nunca existiram**: `git grep
> RepairStatusCharterTest` devolvia um único hit — este próprio arquivo. Promessa de Pest GUARD sem
> teste é pior que ausência, porque a próxima sessão confia numa cobertura que não está lá. A regra
> é grep antes de confiar, e então **cumprir ou revogar**; aqui foi cumprida.

Pendente de F1.5 (e declarado como pendente, não como existente): targets quantitativos de
performance, validação de cor e unicidade de `sort_order`.

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-07 | Opus + Wagner | Stub criado em S6 F1. Detalhamento Pest GUARD pendente F1.5. |
| 2026-09-05 | [C] | Pest GUARD cumprido — os 3 anti-hooks viram UC com teste. Os 3 nomes "(stub)" prometidos não existiam. |
