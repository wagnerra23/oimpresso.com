---
page: /repair/job-sheet
component: resources/js/Pages/Repair/JobSheet/Index.tsx
bundle_source: repair-page.jsx
owner: wagner
status: live
last_validated: "2026-09-05"
parent_module: Repair
parent_capterra: memory/requisitos/Repair/CAPTERRA-FICHA.md
related_runbook: memory/requisitos/Repair/RUNBOOK-jobsheet-index.md
related_visual_comparison: memory/requisitos/Repair/jobsheet-visual-comparison.md
related_adrs: [101, 104, 149, 143, 93]
tier: A
charter_version: 3
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/os-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Index]
  divergence_from_blueprint: "tabela ainda busca no endpoint DataTables AJAX legacy (sprint 2.5) — Wave W3-B6 documenta path canônico mas preserva implementação atual"
---

# Page Charter — /repair/job-sheet

> **Status:** live (Sprint 2.5 / MWART-0002, Blade → Inertia). A tela busca a lista no endpoint
> DataTables AJAX legacy (`datatable_url`) e a renderiza em tabela React própria. Migração do
> motor de dados (paginator server-side) é onda própria — decisão de [W], ver Histórico.

---

## Mission

Listar e filtrar Ordens de Serviço por status, cliente, equipe e local — ponto de entrada pra ações de OS.

---

## Goals — Features (faz)

- Header com título + descrição + botão "Nova OS" → `/repair/job-sheet/create`
- 3 dropdowns de filtro em `Select` do DS (local, status, cliente) + botão "Limpar"
- Busca a lista via `fetch` no endpoint DataTables legacy (`datatable_url`) e renderiza em
  `<table>` React própria, lendo só campos escalares do payload
- Skeleton `aria-busy` durante a carga; `EmptyState` com variante erro / vazio / filtro-sem-resultado
- Respeita 3 flags vindas do Controller: `is_user_service_staff`, `show_serial_no`, `enable_brand_in_job_sheet`
- Multi-tenant: dados scopados por `business_id` global scope no Controller

---

## Non-Goals — Features (NÃO faz)

- ❌ Criação/edição inline (vai pra `/repair/job-sheet/create` e `/edit`)
- ❌ Print direto (rota Blade separada `/repair/job-sheet/{id}/print`)
- ❌ Upload de arquivos (rota Blade separada)
- ❌ Mudança de status drag-and-drop (visualização-only, não kanban)
- ❌ Notificação push de novas OS (não escuta evento real-time)

---

## UX Targets

- p95 first-paint < 1200ms
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal
- Empty state visível quando filtros zeram a lista
- Nunca injetar HTML do payload: o endpoint DataTables devolve colunas com HTML embutido
  (`action`, `status`, `estimated_cost`), e a tela lê **apenas campos escalares**, renderizados
  como texto por React. Sem `dangerouslySetInnerHTML` (R-OWASP)

---

## UX Anti-patterns

- ❌ Modal pra criar OS (rota dedicada existe — não duplicar fluxo)
- ❌ Confirmação dupla em filtros (são read-only)
- ❌ Tooltip explicando o que é OS (audiência conhece o domínio)
- ❌ Consumir as colunas HTML do payload DataTables (ver UX Target acima)

---

## Automation Hooks

- Endpoint controller chama `JobSheetController::index()` com filtros injetados
- A lista vem do MESMO endpoint que serve o Blade legado (`route('job-sheet.index')` sob
  `request()->ajax()`) — o endpoint é compartilhado, não exclusivo desta tela
- Multi-tenant scoping via Eloquent global scope (`business_id`)

---

## Automation Anti-hooks

- ❌ Não dispara emails ao abrir
- ❌ Não dispara SMS ao listar
- ❌ Não muda status de OS (read-only)
- ❌ Não escreve no banco
- ❌ Não roda jobs em fila ao abrir
- ❌ Não chama Brain B/Sonnet
- ❌ Não acessa OS de outro `business_id` (multi-tenant Tier 0 — [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

---

## Métricas vivas (Pest GUARD)

> ⚠️ **NENHUM — revogado em 2026-09-05.** Esta seção listava 7 GUARDs
> (`RepairJobSheetCharterTest::...`) que **nunca existiram**: a classe não existe como arquivo e
> os 7 nomes de método não aparecem em nenhum `.php` do repo (medido no repo inteiro; os nomes que
> davam hit fora daqui estavam em outros `.charter.md`). Promessa de GUARD inexistente é instrução
> ativa pra regressão — a lápide §5 de `memory/proibicoes.md` manda **revogar**, não manter.
>
> Estado real da defesa desta tela (medido 2026-09-05 por `screen-coverage-map.mjs --screen`):
> trio **incompleto** (sem `.casos.md`), **zero** teste Pest Browser citando o path, **sem**
> proto-baseline. Escrever a defesa é trabalho a fazer, não fato a declarar.
>
> Quando os testes existirem, listá-los aqui — e só então.

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-07 | Opus + Wagner | Charter criado em S6 F1 (Foundation). Não enforced ainda — workflow `charter-gate.yml` em modo soft (warn-only) até F2. |
| 2026-09-05 | Claude | v3 — reconciliação com o código. A tela foi reescrita em 2026-05-31 (score-up: placeholder → tabela real) e o charter continuou descrevendo a versão anterior. Corrigido: (a) 7 GUARDs fantasmas revogados; (b) "embed do DataTables via container ref" → `fetch` + tabela React; (c) anti-padrão "sem loading skeleton no shell" removido — o skeleton `aria-busy` **é** a implementação atual; (d) guard XSS reescrito: não há `document.createElement` no código, a proteção real é não consumir as colunas HTML do payload; (e) "filtros persistem em URL/state ao recarregar" removido — são `useState`, somem no reload; (f) Non-Goal de export removido: não há botão de export em lugar nenhum, é lacuna e não delegação ao DataTables. |
