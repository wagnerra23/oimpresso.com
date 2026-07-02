---
page-id: superadmin/Usuario360/Index
status: draft
owner: wagner
pt: PT-01-Lista
adr: [UI-0013]
created: 2026-05-31
page: /superadmin/usuarios
component: resources/js/Pages/superadmin/Usuario360/Index.tsx
last_validated: "2026-05-31"
parent_module: Superadmin
related_us: [US-SUPER-010]
tier: B
charter_version: 1
---

# Charter — Usuário 360 · Lista de busca

> Status `draft` — Non-Goals e Anti-hooks aguardam revisão Wagner antes de `live`.

## Mission
Permitir ao superadmin localizar qualquer usuário (cross-business) por nome/email/empresa
e saltar pro perfil 360 em ≤2 cliques, com busca instantânea.

## Goals
- Busca por nome / email / username com **debounce 300ms** (sem exigir submit).
- Partial reload `only:['users','filters']` — só a tabela recarrega, header/shell ficam.
- Estado de **loading** (skeleton) durante a busca.
- **Paginação client-side** dos resultados (lotes de 10) sobre o conjunto retornado.
- **Empty state** dedicado: pré-busca ("Comece uma busca") e sem-resultado.
- Resultado em tabela responsiva (overflow-x em telas estreitas).

## Non-Goals (revisar Wagner)
- Edição/criação de usuário aqui (tela é read-only de busca).
- Filtros avançados (status, papel, período) — fora do MVP.
- Export CSV/relatório.
- Paginação server-side real (controller hoje retorna `limit(200)` num array;
  paginar server exigiria mudar `Usuario360Controller::index` — fora do escopo
  desta tela. A paginação aqui é client-side sobre o conjunto retornado).

## UX targets
- Primeiro resultado visível < 400ms após parar de digitar.
- Zero "flash" de empty state durante loading (skeleton cobre transição).
- Toda cor via tokens DS (sem hex/oklch inline).

## Anti-hooks (revisar Wagner)
- Não acoplar a `business_id` de sessão — é superadmin cross-tenant por design.
- Não persistir busca em localStorage.
- Não autocompletar/sugerir (privacidade — lista de usuários é sensível).

## Rotas (reais)
- Lista: `GET /superadmin/usuarios` → `superadmin.usuarios.index` (`Usuario360Controller@index`).
- Detalhe: `GET /superadmin/usuarios/{id}/360` → `superadmin.usuarios.show`.
- Props: `users: UserRow[]` (id, username, email, nome, business_id, status, user_type) + `filters: { q }`.

## Componentes DS
PageHeader + EmptyState (default exports `@/Components/shared/*`, prop `icon` = nome lucide string),
Button, Input, Card, Badge, Skeleton (`@/Components/ui/*`).
Ícones lucide: users, search, search-x, chevron-left, chevron-right, x.
