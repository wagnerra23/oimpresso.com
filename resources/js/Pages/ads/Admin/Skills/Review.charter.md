---
page: /ads/admin/skills-review
component: resources/js/Pages/ads/Admin/Skills/Review.tsx
related_prototype: n/a (fila de aprovação bespoke — cards + rationale + approve/reject; não casa com um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ADS
related_adrs: [76, 114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/skills-review (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ADS/Http/Controllers/Admin/SkillsController@review` (GET, rota `ads.admin.skills-review`) + `@approve`/`@reject` (POST por versionId). ADR 0076 Fase 4 — approval queue obrigatória antes de mover label production.

---

## Mission
Concentrar num só lugar todos os drafts de skill aguardando decisão humana, mostrando o rationale (problema + hipótese) e a evidência de testes, pra Wagner aprovar (vira published + label production move) ou rejeitar (arquiva, com comentário obrigatório). É o gate humano que impede que edição de skill vá pra production sem revisão.

---

## Goals — Features (faz)
- Lista de drafts (cards): nome/slug da skill, version, origem, problema observado, hipótese.
- Evidência de teste por draft: contagem de test runs e passados, ou aviso "sem test runs anexados"; link "Testar primeiro".
- Campo de comentário por draft (obrigatório ≥5 chars pra rejeitar, opcional pra aprovar).
- Aprovar → `POST /ads/admin/skills/versions/{id}/approve`; Rejeitar → `POST .../reject`.
- Flash de status de retorno; EmptyState quando não há drafts.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita o conteúdo do draft (isso é `Skills/Edit`).
- ❌ Não roda os testes aqui (só linka pra `Skills/Test`).
- ❌ Não publica no git — aprovar move label production; publish git é ação separada em `Skills/Show`.
- ❌ Não aprova em lote — decisão é uma a uma. [inferência pendente de Wagner]

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 com breadcrumb ADS › Skills › Review.

---

## Automation hooks (faz)
- Approve grava `McpSkillApproval` e move o label production pra a version aprovada (published).
- Reject arquiva a version e registra o comentário.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Rejeição SEM comentário ≥5 chars é bloqueada no cliente (e revalidada no servidor).
- ❌ Aprovar não publica no git sozinho — publish é passo posterior explícito.
- ❌ Não auto-aprova draft por idade/tempo — sempre exige clique humano.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Definir se aprovação deve exigir ≥1 test run passado (hoje só avisa)
