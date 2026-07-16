---
slug: jana-plan-mwart-metas
title: "Jana — PLAN MWART metas/* (Fase 1 ADR 0104)"
type: plan-mwart
module: Jana
status: draft-aguardando-aprovacao
date: 2026-05-09
fase: 1-PLAN
adr_mae: 0104
gate_visual: F1.5-pendente
---

# PLAN MWART — `metas/*` (Jana)

> ⚠️ **Reconciliação v2 (2026-07-15):** este PLAN (draft 2026-05-09) descreve o gate v1 *"Wagner aprova SCREENSHOT síncrono, sem aprovação F3 não inicia"* — **superado** pela v2 ([ADR 0241](../../decisions/0241-loop-design-cowork-code-autonomo-zero-humano.md) + [ADR 0282](../../decisions/0282-protocolo-v2-colapso-ratificacao.md)): gate visual = **CI** (visual-regression + PR UI Judge), não aprovação síncrona; o agente Code **gera** o design com acesso completo ([PROTOCOL §0.1](../../../prototipo-ui/PROTOCOL.md)). Texto v1 preservado como histórico.

> **Tipo:** PLAN Fase 1 do [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — único caminho de migração Blade→Inertia
> **Status:** DRAFT aguardando aprovação Wagner antes de F2 (BACKEND BASELINE)
> **Gate F1.5 ([ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)):** screenshots prototipo-ui pendentes — bloqueia qualquer Edit em `.tsx`

## 0. TL;DR

Migrar 4 telas Blade legacy + 1 superadmin pra Inertia/React seguindo padrão Cockpit V2 ([ADR 0110](../../decisions/0110-cockpit-pattern-v2.md)) e [RUNBOOK-dashboard.md](RUNBOOK-dashboard.md) como referência. **Correção honesta 2026-05-09:** primeira versão deste PLAN levantou ALERT Tier 0 falso — Meta JÁ tem `HasBusinessScope` aplicado. Issue real é P1 (cross-tenant pollution via `store()` aceitando `business_id` do client), não P0 vazamento. PLAN aguarda aprovação Wagner ANTES de qualquer mexida em código tenancy ([feedback memory](~/.claude/projects/D--oimpresso-com/memory/feedback_tenancy_changes_require_pest_local.md): mudanças tenancy exigem Pest verde local).

## 1. Inventário do estado atual

### Controller — [MetasController.php](../../../Modules/Jana/Http/Controllers/MetasController.php)

7 actions stub-quality:

| Action | Route name | Renderiza |
|---|---|---|
| `index()` | `jana.metas.index` | `copiloto::metas.index` (Blade) |
| `create()` | `jana.metas.create` | `copiloto::metas.create` (Blade) |
| `store(Request)` | `jana.metas.store` | redirect → `metas.show` |
| `show($id)` | `jana.metas.show` | `copiloto::metas.show` (Blade) |
| `edit($id)` | `jana.metas.edit` | `copiloto::metas.edit` (Blade) |
| `update(Request,$id)` | `jana.metas.update` | redirect → `metas.show` |
| `destroy($id)` | `jana.metas.destroy` | redirect → `metas.index` (soft delete via `ativo=false`) |
| `reapurar(Request,$id)` | `jana.metas.reapurar` | STUB: dispatch ApurarMetaJob não implementado |

### Blades-alvo

- [`metas/index.blade.php`](../../../Modules/Jana/Resources/views/metas/index.blade.php) — 27 linhas, table simples 4 colunas
- [`metas/create.blade.php`](../../../Modules/Jana/Resources/views/metas/create.blade.php) — 28 linhas, form vanilla
- [`metas/show.blade.php`](../../../Modules/Jana/Resources/views/metas/show.blade.php) — não inspecionado em detalhe nesta fase, mas inclui `route('jana.fontes.show')` cross-link
- [`metas/edit.blade.php`](../../../Modules/Jana/Resources/views/metas/edit.blade.php) — não inspecionado
- [`superadmin/metas.blade.php`](../../../Modules/Jana/Resources/views/superadmin/metas.blade.php) — visão plataforma vs cliente, controller `SuperadminController@metas`

### Domain models

- [`Modules\Jana\Entities\Meta`](../../../Modules/Jana/Entities/Meta.php) — `business_id` fillable, **com `use HasBusinessScope`** (linha 18) — global scope `ScopeByBusiness` aplica filtro automático por `session('user.business_id')`. Tenancy híbrida intencional: null = plataforma (visível só a superadmin)
- [`Modules\Jana\Entities\MetaApuracao`](../../../Modules/Jana/Entities/MetaApuracao.php) — apurações por meta. **Não tem coluna `business_id`** (verificado em [migration](../../../Modules/Jana/Database/Migrations/2026_04_24_000004_create_copiloto_meta_apuracoes_table.php)) — scope indireto via Meta (queries reais sempre passam por `Meta::find()->apuracoes`); adicionar `HasBusinessScope` quebraria SQL

### US cobertas (já existem em SPEC)

US-COPI-010, US-COPI-011, US-COPI-012 (mesmas que cobrem [Dashboard](RUNBOOK-dashboard.md))

## 2. ⚠️ Re-análise honesta — Tier 0 NÃO é violado (correção 2026-05-09)

**Versão anterior deste PLAN afirmou erradamente** que `MetasController` vazava entre tenants. **Falso.** Ao verificar:

- [`Meta`](../../../Modules/Jana/Entities/Meta.php) usa `HasBusinessScope` (linha 18) — aplica `ScopeByBusiness` automaticamente
- [`HasBusinessScope`](../../../app/Concerns/HasBusinessScope.php) chama `bootHasBusinessScope()` que adiciona o global scope no boot do Model
- [`ScopeByBusiness`](../../../Modules/Jana/Scopes/ScopeByBusiness.php) filtra por `session('user.business_id')` automaticamente; superadmin vê próprio + NULL (plataforma)

Logo `Meta::orderByDesc('ativo')->get()`, `Meta::findOrFail($id)` etc são **seguros** — global scope cobre. Não há vazamento.

`MetaApuracao` não tem `business_id` column (só `meta_id`). Scope indireto: queries reais sempre passam por `Meta::find($id)->apuracoes` ou `MetaApuracao::where('meta_id', $metaId)` após Meta::find — Meta scopada já garante isolamento. Adicionar `HasBusinessScope` em MetaApuracao quebraria SQL.

### Issues reais (P1, não P0) — descobertas ficam aqui mas não viram código sem aprovação Wagner + Pest local

1. **`store()` aceita `business_id` do client** — [MetasController.php:34](../../../Modules/Jana/Http/Controllers/MetasController.php) tem `'business_id' => 'nullable|integer'` na validação. User biz=4 pode POST `business_id=1` e poluir dados de biz=1. Não é vazamento (depois fica invisível pro autor) mas é cross-tenant pollution.
   - **Fix proposto:** remover `business_id` da validação; injetar `session('user.business_id')` em `Meta::create()`.
   - **Status:** NÃO aplicado. Wagner pediu zero-risk em mudanças tenancy 2026-05-09. Re-abrir só com Pest verde local.

2. **`reapurar()` é stub** — `dispatch(new ApurarMetaJob)` comentado. Botão na UI não faz nada útil.
   - **Fora de escopo MWART** — abrir US separada US-COPI-METAS-REAPURAR.

3. **`update()` accept-only narrow** — [MetasController.php:65](../../../Modules/Jana/Http/Controllers/MetasController.php): `$request->only(['nome', 'unidade', 'tipo_agregacao'])`. Inclui apenas 3 campos; `business_id`/`slug`/`ativo` ignorados (bom — evita pollution via update). OK.

### Pré-requisitos pra reabrir F2 (gate de Wagner)

- [ ] Wagner roda `composer install` na main repo `D:\oimpresso.com` OU autoriza operar a partir dela
- [ ] Pest test escrito + Wagner roda local (não worktree headless)
- [ ] `php artisan test --filter=MetasControllerBaseline` verde reportado por Wagner
- [ ] Smoke biz=1 manual confirmando que UI/CRUD continua funcionando depois do change
- [ ] PR isolada (nunca bundle com docs/cleanup)

## 3. Pré-flight (skill `mwart-quality` — 9 checks)

| Check | Status | Ação |
|---|---|---|
| 1. Controller usa `Inertia::render` | ❌ todas 5 telas usam `view()` | F3 |
| 2. Page Inertia em `resources/js/Pages/Jana/Metas/` existe | ❌ pasta não existe | F3 |
| 3. Controller filtra por `business_id` | ✅ via `HasBusinessScope` no Model Meta — global scope automático | n/a |
| 4. Pest fixture do `store()` antes de mexer | ❌ não existe | F2 obrigatório ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) — reabrir só com Wagner aprovando + Pest verde local |
| 5. Página vive dentro de `AppShellV2` | n/a (criar) | F3 |
| 6. Tokens shadcn semânticos | n/a (criar) | F3 |
| 7. PT-BR em todos labels | n/a (criar) | F3 |
| 8. Dark mode validado | n/a (criar) | F3 |
| 9. Build local + manifest check | n/a | F3 final |

## 4. Plano por fase (ADR 0104 — 5 fases obrigatórias)

### F1 — PLAN ✅ (este documento)

### F2 — BACKEND BASELINE (PAUSADO — gated em aprovação Wagner)

> **Status:** F2 não inicia até Wagner liberar. Sessão 2026-05-09 tentou primeiro corte e Wagner pediu reverter porque "vazar dados é o erro maior". Mesmo o change sendo defensivo (remove input do client, injeta session), Wagner exigiu Pest verde local antes de PR ([feedback memory](~/.claude/projects/D--oimpresso-com/memory/feedback_tenancy_changes_require_pest_local.md)).

Quando F2 abrir, escopo proposto:

1. **Pest fixture baseline** — `tests/Feature/Modules/Jana/MetasControllerBaselineTest.php` (5 casos):
   - `index()` lista apenas metas do business em foco (global scope ativo)
   - `store()` injeta business_id da session, ignora business_id do client
   - `show($id)` ModelNotFoundException cross-tenant
   - `update($id)` ModelNotFoundException cross-tenant
   - `destroy($id)` soft-delete via `ativo=false`
2. **Refactor `store()`** — remover `'business_id' => 'nullable|integer'` da validação; injetar `session('user.business_id')` em `Meta::create()` (cross-tenant pollution prevention)
3. **`reapurar()` stub permanece stub** (fora de escopo MWART; abrir US separada US-COPI-METAS-REAPURAR)

**DoD F2:** `php artisan test --filter=MetasControllerBaseline` verde **rodado por Wagner local** + smoke biz=1 manual confirmando UI/CRUD intactos.

**Não fazer em F2:** migration backfill (dados já corretos), `HasBusinessScope` em MetaApuracao (column inexistente), mexer em Meta model (já tem trait).

### F1.5 — VISUAL GATE ([ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)) — **BLOQUEADOR DE F3**

Antes de F3 (Frontend), Wagner aprova screenshots em `prototipo-ui/jana-metas/`:

- `01-index-list.png` — lista de metas (table com farol lateral, similar a Dashboard cards)
- `02-create-form.png` — form de nova meta (4 campos + selects)
- `03-show-detalhe.png` — detalhe meta com últimas 12 apurações + sparkline
- `04-edit-form.png` — edit reusando layout do create
- `05-superadmin-platform.png` — visão plataforma com tabs cliente/plataforma

Cada screenshot vai a `memory/requisitos/Jana/metas-visual-comparison.md` (15 dimensões — skill `mwart-comparative` V4) e Wagner aprova **screenshot, não tabela**, em ~10min síncrono. Sem aprovação, F3 não inicia.

### F3 — FRONTEND INCREMENTAL (após gate visual aprovado)

Ordem proposta de telas (incremental, 1 PR por tela):

1. **`Pages/Jana/Metas/Index.tsx`** — lista. Reutilizar pattern de `Pages/Jana/Dashboard.tsx` (cards com farol). Botão "Nova meta" no PageHeader. Empty state.
2. **`Pages/Jana/Metas/Show.tsx`** — detalhe. Header + KpiGrid últimas apurações + Sparkline + botão Reapurar (placeholder até US separada) + Link cross-module pra `/copiloto/metas/{id}/fonte` (mantém Blade legacy KB).
3. **`Pages/Jana/Metas/Create.tsx`** + **`Pages/Jana/Metas/Edit.tsx`** — Form unificado com `<Form>` shared (reusar de Pages/Repair se houver). React Hook Form + zod schema com 4 campos.
4. **`Pages/Jana/Superadmin/Metas.tsx`** — Tabs (cliente/plataforma) + tabelas separadas. Acesso `copiloto.superadmin`.

**DoD por tela (cada PR isolado):**
- AppShellV2 com title + breadcrumbItems
- Tokens shadcn semânticos
- Estados: default + empty + loading skeleton + error boundary
- Dark mode validado
- Atalhos: J/K (next/prev row em Index), `N` pra Nova meta, `⌘K` global
- Multi-tenant: confia em scope F2 + nunca aceita `business_id` no client form
- PT-BR
- Pest mantém verde

### F4 — QA (smoke biz=1, NUNCA biz=4)

[ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md): smoke em biz=1 (Wagner WR2 SC), nunca biz=4 (ROTA LIVRE — cliente).

- Criar meta `Faturamento maio 2026` em biz=1
- Apurar manualmente
- Verificar Index→Show→Edit→Reapurar→Destroy
- Cross-tenant test manual: login biz=4, tentar URL `/copiloto/metas/{id_de_biz1}` → deve 404

### F5 — CUTOVER

- **Aviso prévio cliente** — comunicar Larissa (ROTA LIVRE biz=4) **antes** que UI vai mudar
- **Canary 7 dias** — feature flag `JANA_METAS_INERTIA=true` em prod, default `false`
- Remover Blade `metas/*.blade.php` apenas após canary 7d sem regressão reportada
- Atualizar [SPEC.md](SPEC.md) marcando US-COPI-010/011/012 como "F5 closed"

## 5. Estimativa recalibrada ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md))

Tarefas codáveis com IA-pair (fator 10x + margem 2x):

| Fase | Trabalho | Estimativa (calendário, com margem) |
|---|---|---|
| F2 | Pest baseline + store hardening | 0.5 dia útil — **gated em aprovação Wagner + Pest local** |
| F1.5 | Screenshots + comparativos 15 dimensões | 0.5 dia (Wagner síncrono ~10min/tela × 5) |
| F3 | 5 telas Inertia (1 por PR) | 2-3 dias úteis |
| F4 | Smoke biz=1 + cross-tenant manual | 0.5 dia |
| F5 | Canary 7 dias + cleanup | **7 dias relógio mundo real** (não-codável, [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) |

**Total:** ~3.5-4.5 dias úteis de trabalho codável + 7 dias relógio canary = ~2 semanas calendário.

## 6. Riscos

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Mudança em `store()` quebra fluxo legítimo (ex: superadmin criando meta plataforma) | Baixa | Médio | Pest local com cenário superadmin antes do PR; smoke manual pós-merge |
| Wagner rejeita screenshots F1.5 | Média | Bloqueia F3 | Iterar — F1.5 pode ter 2-3 rounds |
| `metas/show` cross-link `jana.fontes.show` quebra | Baixa | Médio | KB module mantém Blade legacy — link continua funcionando |
| Apuração via `reapurar()` quebra durante MWART | Baixa | Baixo | É stub hoje, fora do escopo |
| Larissa (ROTA LIVRE) usa `metas/*` em fluxo crítico | Desconhecida | Alto | Verificar logs Hostinger últimos 30d antes de F5 — se uso baixo, canary curto OK |

## 7. Não-objetivos (escopo fora deste PLAN)

- ❌ Implementar `ApurarMetaJob` real (US-COPI-METAS-REAPURAR — separada)
- ❌ Migrar tela `metas/superadmin` no mesmo PR de cliente (separada)
- ❌ Mexer em `Modules/KB` (FontesController) — out of scope
- ❌ Alterar contrato `MemoriaContrato` ou drivers de apuração ([ADR 0031](../../decisions/0031-memoriacontrato-mem0-default.md))
- ❌ Mudar shape de `Meta::ativas()` (mantém compatibilidade com Dashboard)

## 8. Próximos passos imediatos (após aprovação deste PLAN)

1. ✅ Wagner aprova este PLAN ou pede ajustes
2. F2 começa: criar Pest baseline + Tier 0 fix + migration (PR isolado, ≤300 linhas)
3. F1.5: prototipo-ui screenshots → comparativo 15 dim → Wagner aprova SCREENSHOT
4. F3: 5 PRs sequenciais (1 por tela)
5. F4: smoke biz=1
6. F5: canary 7d → cleanup

## 9. Sinal de cliente ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))

**Pendência:** este PLAN nasceu da vistoria de migração MWART, não de pedido de cliente. Antes de F2 começar, validar:

- Larissa (ROTA LIVRE biz=4) USA `/copiloto/metas` hoje? → checar `oimpresso_logs` últimos 30d
- Wagner usa em biz=1 (testes Jana)?

Se uso é zero, PLAN vira ADR `feature wish` e deslincra. Se uso ≥1×/semana, prossegue.

## 10. ADRs de origem

- [ADR 0026](../../decisions/0026-posicionamento-erp-grafico-com-ia.md) — Jana IA no ERP
- [ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) — stack canônica
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — **Tier 0 (mãe do alerta)**
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — biz=1 em smoke
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — **processo único MWART (mãe deste PLAN)**
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal
- [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — recalibração velocidade
- [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — **gate F1.5 visual**
- [ADR 0110](../../decisions/0110-cockpit-pattern-v2.md) — Cockpit V2

---

**Última atualização:** 2026-05-09 — PLAN F1 v2: ALERT Tier 0 era falso (Meta já tinha `HasBusinessScope`); F2 reduzido + gated em aprovação Wagner com Pest local; sessão 2026-05-09 tentou primeiro corte e Wagner pediu reverter ([feedback memory](~/.claude/projects/D--oimpresso-com/memory/feedback_tenancy_changes_require_pest_local.md)).
