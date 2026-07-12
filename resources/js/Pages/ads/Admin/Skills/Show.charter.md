---
page: /ads/admin/skills/{slug}
component: resources/js/Pages/ads/Admin/Skills/Show.tsx
related_prototype: n/a (herda PT-03 Detalhe; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ADS
related_adrs: [76, 114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/skills/{slug} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ADS/Http/Controllers/Admin/SkillsController@show` (rota `ads.admin.skills.show`). Detalhe de uma skill (ADR 0076 Fase 2): frontmatter + conteúdo markdown renderizado + timeline de versões.

---

## Mission
Mostrar tudo de uma skill num só lugar — metadados (frontmatter), corpo markdown renderizado e o histórico de versões com status/origem — pra o admin decidir se edita, testa, promove pra production ou publica no git. É o hub de inspeção antes de qualquer ação de governança sobre a skill.

---

## Goals — Features (faz)
- Frontmatter em `<dl>` (slug, name, description, module, tags, arquivo git_path).
- Corpo da skill renderizado como markdown (ReactMarkdown + remark-gfm).
- Timeline de versões (tabela): número, origem (UI/git drift/git seed), status (draft/review/published/drift_pending/archived), data e ação atual.
- Ações por versão publicada: "Promover production" (move label) e "Publish to git" (abre PR) — via `router.post`, com `confirm()`.
- Botões contextuais "Testar" e "Editar" quando `editable` (skill em DB).
- Link externo pro arquivo no GitHub.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita conteúdo inline aqui (form de edição é `Skills/Edit`).
- ❌ Não aprova/rejeita draft aqui (isso é a Approval queue `Skills/Review`).
- ❌ Não roda a skill (isso é `Skills/Test`).
- ❌ Não versiona skill de outro business — skills são config project-global git-backed, não dado por `business_id`. [inferência pendente de Wagner]

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 com breadcrumb ADS › Skills › Detalhe.

---

## Automation hooks (faz)
- "Promover production" → `POST /ads/admin/skills/{slug}/move-label` (troca o label production pra a version escolhida — rollback/switch manual).
- "Publish to git" → `POST /ads/admin/skills/versions/{id}/publish` (cria PR no git).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Ações destrutivas/impactantes (promover label, publish git) exigem `confirm()` explícito — nunca disparam sozinhas.
- ❌ Não faz mutação em GET; a página em si é read-only, mutações são POST via botão.
- ❌ Não faz polling/auto-refresh da timeline.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Validar UX do fluxo promover/publish (confirm + flash de retorno)
