---
page: /ads/admin/skills/{slug}/edit
component: resources/js/Pages/ads/Admin/Skills/Edit.tsx
related_prototype: n/a (herda PT-02 Formulário; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ADS
related_adrs: [76, 114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/skills/{slug}/edit (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ADS/Http/Controllers/Admin/SkillsController@edit` (GET, rota `ads.admin.skills.edit`) + `@store` (POST `/ads/admin/skills/{slug}`, cria version draft). ADR 0076 Fase 2 — editor inline que cria nova version `status=draft`, `origin=ui`.

---

## Mission
Deixar o admin editar frontmatter e body de uma skill e registrar o PORQUÊ da mudança via 4 rationales obrigatórios (problema, hipótese, métrica de sucesso, rollback). Salvar não vai direto pra production: cria uma version draft que a Approval queue promove depois. Força pensamento estruturado antes de mexer numa skill que afeta auto-activation.

---

## Goals — Features (faz)
- Form (`useForm`) com textarea de frontmatter YAML e textarea de body markdown.
- Aviso alto-impacto quando o frontmatter é modificado (description afeta auto-activation, name é a chave do matching).
- Contador de chars/linhas do body.
- 4 rationales obrigatórios: problema observado, hipótese de fix, métrica de sucesso, plano de rollback.
- Submit `POST /ads/admin/skills/{slug}` cria version `status=draft` (validado por `StoreSkillVersionRequest`); exibe erros de validação por campo.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não promove pra production nem publica no git — só cria draft (promoção é `Skills/Review` + `Skills/Show`).
- ❌ Não roda/testa a skill editada (isso é `Skills/Test`).
- ❌ Não edita skill filesystem-only sem persistir em DB — edição pressupõe fonte editável (DB).
- ❌ Não faz merge/diff visual de versões nesta tela. [inferência pendente de Wagner]

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2, layout de foco (form sem distração lateral).

---

## Automation hooks (faz)
- Submit dispara `SkillsController@store` → grava `McpSkillVersion` draft com os 4 rationales e `origin=ui`.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Salvar NÃO ativa a nova version em production — fica draft até aprovação humana.
- ❌ Não escreve no git automaticamente (publish é passo separado, pós-aprovação).
- ❌ Não auto-preenche rationales — são exigidos do humano de propósito.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar regra de permissão futura (`ads.admin.skills.edit`) vs `auth` atual
