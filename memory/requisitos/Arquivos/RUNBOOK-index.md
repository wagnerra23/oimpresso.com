---
id: requisitos-arquivos-runbook-index
title: "RUNBOOK — Arquivos (`/arquivos`)"
module: Arquivos
tela: Arquivos/Index
owner: W
status: rascunho
last_validated: "2026-08-24"
preconditions:
  - "Usuário autenticado com a permission `arquivos.access` (declarada em `DataController::user_permissions`, default `false`)"
  - "`business_id` na sessão — o `Arquivo` aplica global scope por business (ADR 0093, Tier 0)"
  - "Módulo `arquivos_module` habilitado no pacote do business (Camada 1 — superadmin/packages)"
  - "Tabela `arquivos` migrada (8 migrations do módulo, Sprint 1)"
preconditions_short: permission arquivos.access, business_id na sessão, módulo habilitado
---

# RUNBOOK — Arquivos (`/arquivos`)

> **F1 PLAN do MWART (ADR 0104).** Escrito ANTES de codar a Page, como o hook
> `block-mwart-violation` exige — ele me barrou na primeira tentativa de escrever o `.tsx`,
> e estava certo.
>
> Trio da tela (já no `main`): [`Index.charter.md`](../../../resources/js/Pages/Arquivos/Index.charter.md) (lei) ·
> [`Index.casos.md`](../../../resources/js/Pages/Arquivos/Index.casos.md) (contrato de teste).
> US: **US-ARQ-013** · ADR mãe: [0123](../../decisions/0123-modules-arquivos-backbone.md).

## 1. Objetivo

Dar a quem responde pela conformidade um lugar pra ver **o que o sistema guardou, por quanto
tempo a lei manda guardar, o que já passou do prazo e quem tocou em quê**.

Arquivos guarda coisa que a lei manda guardar (XML de NF-e por 5 anos) junto com coisa que a lei
manda apagar (PII depois da finalidade). Sem tela, ninguém no negócio sabe qual é qual.

## 2. Persona principal

Wagner (escritório, 1440px) e Eliana (financeiro) — conformidade e custo de disco.
**Não é tela de balcão:** Larissa continua alcançando o anexo pela tela da OS.

## 3. Pré-requisitos

Ver `preconditions` no frontmatter. Em especial: `arquivos.access` **não tinha nenhum
consumidor no repo** até esta tela — a rota é o primeiro. Antes dela, a permission existia
declarada e nunca era exercida.

## 4. Fluxo principal (golden path)

1. Usuário com `arquivos.access` abre `/arquivos`.
2. `ArquivosAdminController@index` recebe a **`ListArquivosRequest`** — que já existia órfã
   desde a Sprint 1 e valida `bucket · owner_type · mime · from/to · per_page · q · with_trashed`.
3. Props: `filtros` + `politica` eager (baratas); **`acervo` via `Inertia::defer`** — tem
   `paginate` + eager-load de `arquivable`, então é o caso default do RUNBOOK de defer.
4. A Page renderiza chips de bucket, busca e a tabela; o `<Deferred>` mostra skeleton até o
   payload caro chegar.

## 5. Onda desta entrega, e o que fica pra depois

Este RUNBOOK cobre a onda 1 · **PR-1 (acervo)**. As outras três vistas do charter chegam nos
PRs seguintes e **a barra de abas nasce com elas** — aba que não leva a lugar nenhum é promessa,
não navegação.

| Vista | PR | Estado |
|---|---|---|
| Acervo | PR-1 | esta entrega |
| Trilha (`arquivos_audit_log`, read-only) | PR-2 | pendente |
| Retenção (`summary()` + `preview()`, dry-run puro) | PR-3 | pendente |
| Cofre (health-check + dedupe + curador) | PR-4 | pendente |

## 6. Estados (loading / empty / error / success)

- **loading** — `<Deferred fallback>` com skeleton de 6 linhas (a prop cara não bloqueia a pintura).
- **empty** — `EmptyState` explicando que o acervo enche sozinho e que **esta tela não envia arquivo**.
- **filtrado-vazio** — mesma tabela, zero linhas: o chip ativo é o que explica.
- **sem-permissão** — o `can:arquivos.access` devolve 403 antes de renderizar.
- **success** — tabela com prazo **e base legal** por linha.

## 7. Atalhos de teclado

Nenhum nesta onda. A tela não está no `MENU_SHORTCUTS` do shell e não reivindica letra.

## 8. Dependências de API/backend

| Peça | Onde | Estado |
|---|---|---|
| `ListArquivosRequest` | `Modules/Arquivos/Http/Requests/` | **já existia** (Sprint 1) |
| `Arquivo` (global scope + SoftDeletes) | `Modules/Arquivos/Entities/` | já existia |
| `Config/retention.php` (prazo + base legal) | `Modules/Arquivos/Config/` | já existia |
| `ArquivosAdminController` | `Modules/Arquivos/Http/Controllers/` | **novo nesta onda** |
| rota `GET /arquivos` → `arquivos.index` | `Modules/Arquivos/Routes/web.php` | **nova nesta onda** |

Nenhum endpoint novo foi inventado — a regra 3 do pedido zero-toque é ligar o que existe.

## 9. Multi-tenant + LGPD

- **Tier 0 (ADR 0093):** `business_id` vem da **sessão**, nunca do request. O controller
  **não** repete o `where` do global scope de propósito: duplicar esconderia uma quebra do scope.
- **Zero `withoutGlobalScopes`** neste caminho.
- **Sem PII na vista de governança (LGPD Art. 37):** `storage_path` e MD5 **não** saem do
  controller. Eles vivem só em `arquivos_audit_log`.
- **`biz=4` (ROTA LIVRE) nunca em teste** — tenant fictício 98 ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).

## 10. Smoke check pós-deploy

1. `curl -sv https://oimpresso.com/arquivos 2>&1 | grep '^< HTTP'` → **302** pra login sem sessão.
2. Logado com `arquivos.access` → **200** e a tabela renderiza.
3. Logado **sem** a permission → **403**.
4. Screenshot 1280 e 1440 sem scroll horizontal.
5. Conferir na tela: nenhum caminho de storage, nenhum MD5.

## 11. O que NÃO fazer

- ❌ Não adicionar upload aqui — arquivo entra pelos módulos, via trait `HasArquivos`.
- ❌ Não servir arquivo do vault por `Storage::url` — sempre `DownloadController` (ADR 0123 §6).
- ❌ Não renderizar `storage_path`/MD5 na tela.
- ❌ Não mexer em `arquivos.download` (signed + `throttle:60,1`) nem nas 3 rotas Install (ADR 0024).
- ❌ Não usar o PageHeader antigo (`@/Components/shared/PageHeader`) — tela nova vai no canon
  `{ PageHeader } from '@/Components/PageHeader'` (ADR 0189/0190). O `pageheader-migration-guard`
  reprova adotante novo.
- ❌ Não dar `hard-delete` por esta tela.

## 12. Diagnóstico/Troubleshoot

| Sintoma | Causa provável |
|---|---|
| 403 com usuário admin | `arquivos.access` não marcada na função (Camada 3, `/roles/{id}/edit`) |
| Tela vazia com dados no banco | `business_id` da sessão diferente do dono das linhas — global scope funcionando |
| Skeleton eterno | a prop `acervo` é `defer`; conferir se o partial reload não está pedindo `only:[]` sem ela |
| Prazo sem a lei ao lado | `sub_destination` fora de `Config/retention.php` — cai no `default` (90d) |

## 13. Refs

- Charter: [`Index.charter.md`](../../../resources/js/Pages/Arquivos/Index.charter.md)
- Casos: [`Index.casos.md`](../../../resources/js/Pages/Arquivos/Index.casos.md)
- SPEC: [`SPEC.md`](SPEC.md) US-ARQ-013
- ADRs: [0123](../../decisions/0123-modules-arquivos-backbone.md) · [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [0360](../../decisions/0360-deprecacao-admin-center-supersede-0122.md) · [0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- Protótipo: `prototipo-ui/cowork/arquivos-page.jsx`
- Defer: [`RUNBOOK-inertia-defer-pattern.md`](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md)
