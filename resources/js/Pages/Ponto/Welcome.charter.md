---
page: /ponto/react
component: resources/js/Pages/Ponto/Welcome.tsx
related_prototype: n/a (página hub de boas-vindas com atalhos — não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ponto/react (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: closure em `Modules/Ponto/Http/routes.php` (`Route::get('/react')` → `Inertia::render('Ponto/Welcome')`, rota `ponto.react.welcome`, middleware `ponto.access`). Página piloto de boas-vindas do módulo — sem controller nem props.

---

## Mission
Página de boas-vindas/hub do módulo de ponto: saúda o usuário logado (nome + business dos shared props) e oferece atalhos para as áreas principais — Aprovações, Banco de horas, Espelho de ponto e Importações. Nasceu como piloto React/Inertia pra validar o pipeline TW4+shadcn e serve hoje de porta de entrada visual.

---

## Goals — Features (faz)
- Saudação com nome do usuário e nome do business (via hooks `useAuth`/`useBusiness` dos shared props).
- Grade de 4 cards-atalho com ícone, título e descrição, cada um linkando para uma seção do ponto.
- Layout AppShellV2 + `PageHeader` (componente compartilhado).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não mostra KPIs, dados de ponto nem marcações — é só navegação (sem props do backend).
- ❌ Não é o dashboard real do ponto (a rota `/ponto` → `DashboardController` é a home; esta é o piloto `/ponto/react`).
- ❌ Não executa nenhuma ação de negócio — só links.
- ❌ Não toca dados multi-tenant além de exibir nome do business já na sessão.

---

## UX targets
- p95 < 800ms (página estática) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2.

---

## Automation hooks (faz)
- Nenhuma — a tela não dispara jobs, endpoints nem queries (renderiza sem props).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling nem fetch.
- ❌ Nenhuma mutação — 100% read-only/navegação.
- ❌ Não redireciona automaticamente para nenhuma seção.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Decidir se `/ponto/react` (piloto) permanece ou é substituído pelo Dashboard real
- [ ] Smoke visual 1280/1440 (screenshot)
