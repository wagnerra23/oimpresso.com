---
page: /auditoria/{id}
component: resources/js/Pages/Auditoria/Detail.tsx
related_prototype: n/a (herda PT-03 Detalhe; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Auditoria
related_adrs: [79, 127, 114, 101]
tier: B
charter_version: 1
---

# Page Charter — /auditoria/{id} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Auditoria/Http/Controllers/AuditoriaController` (detalhe de uma entrada de log). Lê o diff do Spatie activitylog (`properties = { old?, attributes? }`). ADR 0079 (Art. 9 Auditoria) + ADR 0127 (UI Modules/Auditoria).

---

## Mission

Detalhe read-only de uma entrada do log de auditoria: mostra quem fez, quando, em qual entidade, e o **diff** da mudança (Campo · Antes · Depois) quando o activitylog trouxe `old` + `attributes`, ou um key/value simples quando não há par. É a tela pra investigar uma alteração específica sem abrir o banco — trilha de auditoria legível (Art. 9).

---

## Goals — Features (faz)

- Cabeçalho da entrada (PageHeader canon): ator, ação, entidade afetada, timestamp
- Diff formatado Campo · Antes · Depois quando há `old` + `attributes`; fallback key/value quando só há um lado
- Links navegáveis pra entidade/recurso relacionado quando resolvível
- Renderização por tokens DS (sem cor crua sky/zinc) — gap-fix board 2026-05-30 (58→≥70)
- AppShellV2 + PageHeader shared

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO edita nem apaga a entrada de log (auditoria é append-only por lei/ADR 0079)
- ❌ NÃO reverte a mudança auditada (só mostra o diff; rollback é fluxo de outra tela)
- ❌ NÃO lista todas as entradas (isso é a listagem de auditoria; aqui é 1 registro)
- ❌ NÃO expõe PII crua além do que o activitylog já persistiu (respeita redaction upstream)
- ❌ NÃO cruza tenants — escopo por `business_id` no controller (Tier 0)

---

## UX targets

- p95 < 1500ms (tela admin)
- Cabe em 1280px (ROTA LIVRE)
- Diff legível linha-a-linha (Campo/Antes/Depois), não JSON bruto
- Zero cor crua — só tokens DS

---

## Automation hooks (faz)

- Parser do payload Spatie activitylog decide diff vs KV automaticamente conforme `old`/`attributes`

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO grava nada em GET (leitura pura de trilha)
- ❌ NÃO dispara notificação/rollback ao abrir a entrada
- ❌ NÃO reprocessa/normaliza o log persistido

---

## Pendências antes de `status: live`

- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — entrada com diff e entrada KV simples
- [ ] Confirmar mapa de links navegáveis por tipo de entidade
