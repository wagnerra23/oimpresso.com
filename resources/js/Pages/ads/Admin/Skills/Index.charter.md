---
page: /ads/admin/skills
component: resources/js/Pages/ads/Admin/Skills/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ADS
related_adrs: [76, 114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/skills (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ADS/Http/Controllers/Admin/SkillsController@index` (rota `ads.admin.skills.index`, grupo middleware `web/SetSessionData/auth/language/timezone/AdminSidebarMenu/CheckUserLogin`). Lista as skills do Claude Code disponíveis no projeto (ADR 0076 — DB primary com fallback filesystem).

---

## Mission
Dar ao admin (Wagner) uma visão única de todas as skills do Claude Code registradas no projeto — slug, nome, descrição, módulo dono e tamanho — com busca client-side e ponto de entrada pro detalhe e pra fila de aprovação. É a porta de casa da governança de skills do ADS (ADR 0076): antes de editar/versionar, o admin acha e inspeciona a skill aqui.

---

## Goals — Features (faz)
- Lista todas as skills via `SkillsService@listAll` em tabela (slug, nome, descrição, módulo, tamanho em chars).
- KPIs no topo: total de skills, quantas têm módulo, tamanho médio do body.
- Busca client-side (`useMemo`) por slug, nome ou descrição, com contador "N de M".
- Badge de origem da fonte (`DB` vs `Filesystem (fallback)`) sinalizando se o import inicial rodou.
- Link por linha pro detalhe (`/ads/admin/skills/{slug}`) e botão pra Approval queue (`/ads/admin/skills-review`).
- EmptyState quando não há skills ou o filtro não bate.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita nem versiona skill nesta tela (isso é `Skills/Edit`).
- ❌ Não roda/testa skill aqui (isso é `Skills/Test`).
- ❌ Não pagina server-side — carrega a lista inteira e filtra no cliente (skills são poucas dezenas). [inferência pendente de Wagner]
- ❌ Não expõe skills de outro business — skills são config project-global git-backed, não dado de negócio por `business_id`. [inferência pendente de Wagner]

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 com breadcrumb ADS › Skills.

---

## Automation hooks (faz)
- Fonte lida em runtime por `SkillsService` (DB primary, fallback filesystem `.claude/skills/<slug>/SKILL.md`) — sem job; leitura síncrona no `index`.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não sincroniza git ↔ DB nesta tela (drift detection e publish são outras rotas/fases).
- ❌ Não faz mutação em GET — tela é 100% read-only.
- ❌ Não dispara auto-refresh/polling; recarrega só por navegação.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar se skills devem ou não ser filtradas por permissão/`business_id` (hoje só `auth`)
