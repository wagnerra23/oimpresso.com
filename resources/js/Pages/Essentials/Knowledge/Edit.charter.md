---
page: /essentials/knowledge-base/{id}/edit
component: resources/js/Pages/Essentials/Knowledge/Edit.tsx
related_prototype: n/a (herda PT-02 Formulário; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Essentials
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /essentials/knowledge-base/{id}/edit (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Essentials/Http/Controllers/KnowledgeBaseController@edit` + `@update` (resource `knowledge-base`). Edita título/conteúdo de um nó existente da base de conhecimento e, para livros, a visibilidade.

---

## Mission
Editar um nó já existente da base de conhecimento (livro, seção ou artigo). Reaproveita o mesmo formato do Create, mas com valores pré-preenchidos do nó. Para livros, permite ajustar "Compartilhar com" e a lista de usuários com acesso.

---

## Goals — Features (faz)
- Formulário pré-preenchido com título e conteúdo do nó (`useForm` inicializado com `kb`).
- Para tipo `knowledge_base`: seletor de visibilidade (`public` / `only_with`) e chips de usuários com acesso, iniciados a partir de `assigned_user_ids`.
- Submete via `PUT /essentials/knowledge-base/{id}`, com toasts e erros de campo inline.
- Botões Voltar/Cancelar retornam ao `show` do nó.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO edita nó de outro business — carregamento e update com `where business_id` (multi-tenant Tier 0).
- ❌ NÃO permite mudar o `kb_type` do nó nem seu `parent_id` (hierarquia é imutável no form) — inferência pendente de Wagner.
- ❌ NÃO oferece editor rich-text — conteúdo é textarea HTML cru.
- ❌ NÃO gerencia visibilidade de seção/artigo (só livro).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 quando aplicável

---

## Automation hooks (faz)
- Deriva rótulo/ícone do tipo do nó para o cabeçalho.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ NÃO salva rascunho automático (sem autosave).
- ❌ NÃO notifica usuários ao alterar a lista de acesso.
- ❌ NÃO sanitiza HTML no cliente (feito no backend na exibição).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar se mover nó de pai (reparent) deveria existir aqui
