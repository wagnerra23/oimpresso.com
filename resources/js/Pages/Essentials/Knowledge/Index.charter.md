---
page: /essentials/knowledge-base
component: resources/js/Pages/Essentials/Knowledge/Index.tsx
owner: wagner
status: live
last_validated: "2026-06-17"
parent_module: Essentials
related_adrs: [93, 94, 101, 104]
tier: B
charter_version: 2
---

# Page Charter — /essentials/knowledge-base (Base de conhecimento interna)

> Migração Blade T1 Wave D — `Modules/Essentials/Resources/views/knowledge_base/index.blade.php` (cards Bootstrap collapse + jQuery accordion) → React/Inertia com grid de cards + collapse interativo. **NÃO confundir com `Modules/KB` (grafo Jana RAG)** — esta é base interna textual lite.

---

## Mission

Organizar **manuais, procedimentos e artigos internos** em estrutura hierárquica de 3 níveis (Livro → Seção → Artigo). Permite onboarding novo dev/operador e referência rápida de processos. ACL flexível: público / só criador / lista específica de users.

---

## Goals (faz)

- Grid responsivo (1/2/3 cols) de cards "Livro" com:
  - Título + content preview (HTML render via `dangerouslySetInnerHTML` — **NÃO** confiar no autor: autoria não é admin-only, qualquer user do tenant com assinatura `essentials_module` autora; `content` é sanitizado server-side no Controller via `App\Util\HtmlSanitizer::clean` (HTMLPurifier, mesma política do CMS) antes do Inertia — #2895)
  - Quick actions: Ver / Editar / Excluir / Adicionar seção (4 botões topo)
  - Lista colapsável de seções (collapse local `useState`)
  - Sub-lista artigos quando seção aberta (Ver / Editar / Excluir / Adicionar artigo)
- Empty state graceful quando sem livros ("Criar primeiro livro" CTA)
- AlertDialog confirmação remoção (warning: filhos serão removidos junto — cascade DB)
- Hierarquia eager-load no Controller (`children + children.children` — 2 níveis)
- ACL via Controller: `whereHas users + orWhere created_by + orWhere share_with public`

---

## Non-Goals (NÃO faz)

- ❌ **NÃO é `Modules/KB`** (grafo Jana RAG — esse vive em `Modules/KB/Entities/KbDocument` com embedder)
- ❌ NÃO indexa em Meilisearch (knowledge_bases é textual lite — sem search vector)
- ❌ NÃO suporta upload de PDF/anexo (só texto WYSIWYG — `content` HTML coluna)
- ❌ NÃO tem permalink público externo (auth obrigatório)
- ❌ NÃO versiona conteúdo (sem changelog — Sprint futuro se demandado)
- ❌ NÃO suporta níveis >3 (Livro/Seção/Artigo é o limite hardcoded)

---

## UX targets

- Grid colapsa elegante 3 → 2 → 1 cols (md/lg breakpoints)
- Collapse de seção via state local (não re-renderiza página inteira)
- Defer eager-load `books` (eager-load 2 níveis children + ACL where + map recursivo) ([RUNBOOK-inertia-defer-pattern.md](../../../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md))
- Toast Sonner em delete
- Mobile: 1 col, scroll vertical

---

## Backend contract

- `KnowledgeBaseController@index` retorna `{ books: Book[] (defer) }`
- `Book` shape: `{ id, title, content, kb_type, share_with, children: Section[] }` onde Section tem `children: Article[]`
- POST/PUT/DELETE `/essentials/knowledge-base/{id?}`
- Multi-tenant Tier 0: `KnowledgeBase` Entity tem `HasBusinessScope` ([HasBusinessScopeAdoptionTest](../../../../Modules/Essentials/Tests/Feature/HasBusinessScopeAdoptionTest.php))

---

## Métricas de sucesso

- ✅ User biz=1 NÃO enxerga book biz=99 (cross-tenant Pest)
- ✅ User sem ACL (não criador, não público, não na lista) NÃO vê book (ACL Pest)
- ✅ Delete book cascade remove sections + articles (FK DB)
- ✅ Smoke route `/essentials/knowledge-base` retorna 200 autenticado biz=1
- ✅ Charter + RUNBOOK presentes (gate MWART)
- ✅ Distinção clara doc: Essentials/Knowledge (textual lite) ≠ Modules/KB (RAG grafo)
