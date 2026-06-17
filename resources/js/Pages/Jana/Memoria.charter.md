---
page: /copiloto/memoria
component: resources/js/Pages/Jana/Memoria.tsx
owner: wagner
status: live
last_validated: "2026-05-16"
parent_module: Jana
parent_adr: memory/decisions/0052-memoria-jana-3-angulos-faturamento.md
related_adrs: [31, 33, 35, 36, 37, 52, 61, 93, 94, 131]
related_charters:
  - resources/js/Pages/Jana/Chat.charter.md
related_specs:
  - memory/requisitos/Jana/SPEC.md (US-COPI-MEM-005, US-COPI-MEM-008, US-COPI-MEM-012)
tier: A
charter_version: 1
permissao: copiloto.memoria.manage
lgpd_sensitive: true
---

# Page Charter — `/copiloto/memoria`

> **Status:** `live` — implementada e em uso prod biz=1 desde 2026-04. Charter retroativo Wave M 2026-05-16.
>
> **LGPD-sensitive** — gestão de **fatos persistentes** sobre o business. Tudo aqui é PII-adjacent.

---

## Mission

Tela LGPD-first onde dono/gestor **vê, edita e apaga fatos** que a Jana lembrou sobre o business (`copiloto_memoria_facts`). Cumpre direito de acesso + retificação + esquecimento (LGPD Art. 18). Sem essa tela, memória vira black-box → quebra confiança + compliance.

Audiência primária: **dono/gestor do business** (Wagner, Larissa). Acesso `business_id` scoped strict — fato cross-tenant = bug Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).

---

## Goals

- Listar fatos com filtro por `categoria`, busca fulltext em `fato`, sort por `valid_from DESC`
- Editar fato inline (texto + categoria + relevância) com `activitylog` registrando autor/quando/motivo
- Apagar fato (soft delete `deleted_at`) com `AlertDialog` "você tem certeza" — apaga embeddings Meilisearch async via job
- Mostrar `origem` do fato (chat / brief auto / inserção manual) — transparência
- Wagner como superadmin vê fatos cross-business via toggle `?escopo=plataforma` (audit log)

## Non-Goals

- ⛔ Bulk delete sem confirmação individual — LGPD exige consentimento granular
- ⛔ Export CSV de fatos PII sem audit log (futuro: `MemoriaController@export` com log obrigatório)
- ⛔ Insert manual de fato sem origem rastreável — toda criação registra `origem` e `user_id`
- ⛔ Mostrar fato de outro business mesmo pra superadmin sem flag explícita

## UX targets

- Render < 250ms p95 com `Inertia::defer()` em `fatos` paginated
- Empty state "Jana ainda não aprendeu nada sobre seu negócio" + CTA Chat
- Edit mode toggle inline (sem rota separada) — `useForm` Inertia
- Confirmação delete AlertDialog explicitando "esta ação é irreversível"
- Mobile responsivo — accordion por categoria

## Anti-hooks

- ⛔ Render texto fato sem `PiiRedactor` se contém CPF/CNPJ — Tier 0 LGPD
- ⛔ Update direto sem `activitylog` — quebra audit trail LGPD Art. 18
- ⛔ Forget físico (`forceDelete()`) sem job async — embeddings Meilisearch precisam expurgar consistente
- ⛔ Permitir edit por user sem permissão `copiloto.memoria.manage`

## Skills relevantes

`brief-first` (Tier A) · `multi-tenant-patterns` (Tier A) · `jana-recall-flow` (Tier B) · `jana-arch` (Tier B) · `commit-discipline` (Tier A — PII em commit nunca)

## Charter version log

- v1 (2026-05-16) — Charter retroativo Wave M boost Modules/Jana 64→78
