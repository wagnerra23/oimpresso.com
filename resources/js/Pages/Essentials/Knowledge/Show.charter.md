---
page: /essentials/knowledge-base/{id}
component: resources/js/Pages/Essentials/Knowledge/Show.tsx
related_prototype: n/a (tela de detalhe bespoke — livro/seção/artigo com sidebar de navegação; não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Essentials
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /essentials/knowledge-base/{id} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Essentials/Http/Controllers/KnowledgeBaseController@show` (resource `knowledge-base`). Leitor da base de conhecimento com árvore de navegação (livro → seções → artigos) à esquerda e conteúdo à direita.

---

## Mission
Ler um nó da base de conhecimento no contexto do seu livro. A esquerda mostra a árvore navegável do livro (seções e artigos, com o nó atual destacado); a direita renderiza o conteúdo HTML sanitizado do nó, com badge de tipo e ações de Editar / Adicionar filho / Voltar.

---

## Goals — Features (faz)
- Sidebar com árvore do livro em 3 níveis (livro → seções → artigos), item ativo destacado conforme `sectionId`/`articleId`.
- Renderização do conteúdo via `dangerouslySetInnerHTML` com HTML já sanitizado no backend (`HtmlSanitizer::clean`).
- Cabeçalho com título, badge do tipo e, para livros, resumo de visibilidade (público / lista de usuários compartilhados).
- Ações: Editar (`/{id}/edit`), Adicionar seção/artigo (`create?parent={id}`, oculta em artigos), Voltar ao índice.
- Empty state quando o nó não tem conteúdo.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO exibe nó de outro business — `show` carrega com `where business_id` (multi-tenant Tier 0); árvore e livro resolvidos no mesmo escopo.
- ❌ NÃO tem busca full-text dentro do livro.
- ❌ NÃO permite editar inline — edição é na tela `edit`.
- ❌ NÃO exporta o conteúdo (PDF/print) — inferência pendente de Wagner.

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 quando aplicável

---

## Automation hooks (faz)
- Resolve o livro de topo a partir de qualquer nó (section/article sobem via `parent_id`) para montar o sidebar.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ NÃO renderiza HTML não-sanitizado de usuário (sanitização obrigatória no backend antes de enviar).
- ❌ NÃO faz polling/refresh do conteúdo.
- ❌ NÃO registra "leitura" nem métricas de acesso.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar necessidade de busca/print dentro do livro
