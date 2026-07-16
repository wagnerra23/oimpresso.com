---
page: /essentials/knowledge-base/create
component: resources/js/Pages/Essentials/Knowledge/Create.tsx
related_prototype: n/a (herda PT-02 Formulário; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Essentials
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /essentials/knowledge-base/create (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Essentials/Http/Controllers/KnowledgeBaseController@create` + `@store` (resource `knowledge-base`). Formulário de criação de nó da base de conhecimento em 3 níveis (livro → seção → artigo).

---

## Mission
Permitir criar um novo nó da base de conhecimento do business. O tipo do nó (Livro / Seção / Artigo) é derivado do pai: sem pai vira `knowledge_base` (livro), dentro de livro vira `section`, dentro de seção vira `article`. Para livros, define-se a visibilidade (público ao business ou apenas usuários selecionados).

---

## Goals — Features (faz)
- Formulário com título (obrigatório) e conteúdo (HTML permitido, textarea).
- Deriva automaticamente o `kb_type` a partir do `kb_type` do pai (`nextTypeFor`), com rótulo e ícone correspondentes.
- Para tipo `knowledge_base`: seletor "Compartilhar com" (`public` / `only_with`) e, quando `only_with`, chips multi-seleção de usuários do business.
- Submete via `POST /essentials/knowledge-base` (`useForm`), com toasts de sucesso/erro e erros de campo inline.
- Breadcrumb + botões Voltar/Cancelar que retornam ao pai (`/essentials/knowledge-base/{parent.id}`) ou ao índice.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO cria nó em outro business — `business_id` scope Tier 0 aplicado no store; `parent` é resolvido com `where business_id`.
- ❌ NÃO oferece editor rich-text/WYSIWYG — conteúdo é textarea com HTML cru (sanitizado na exibição via `HtmlSanitizer`).
- ❌ NÃO define visibilidade granular para seção/artigo (só o livro carrega `share_with`).
- ❌ NÃO faz upload de anexos ao nó.

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 quando aplicável

---

## Automation hooks (faz)
- Deriva tipo/rótulo/ícone do nó a partir do pai sem input manual.
- Após store, backend redireciona para `knowledge-base.show` do nó criado.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ NÃO salva rascunho automático (sem autosave/localStorage).
- ❌ NÃO notifica usuários adicionados ao `only_with`.
- ❌ NÃO valida/sanitiza HTML no cliente — sanitização é responsabilidade do backend na exibição.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar se seção/artigo deveriam herdar visibilidade do livro
