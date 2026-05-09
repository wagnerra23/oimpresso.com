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

> **Tipo:** PLAN Fase 1 do [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — único caminho de migração Blade→Inertia
> **Status:** DRAFT aguardando aprovação Wagner antes de F2 (BACKEND BASELINE)
> **Gate F1.5 ([ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)):** screenshots prototipo-ui pendentes — bloqueia qualquer Edit em `.tsx`

## 0. TL;DR

Migrar 4 telas Blade legacy + 1 superadmin pra Inertia/React seguindo padrão Cockpit V2 ([ADR 0110](../../decisions/0110-cockpit-pattern-v2.md)) e [RUNBOOK-dashboard.md](RUNBOOK-dashboard.md) como referência. **Bloqueador descoberto na vistoria:** controller atual viola multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — qualquer query expõe metas de todos os tenants.

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

- `Modules\Jana\Entities\Meta` — `business_id` fillable, **SEM global scope** (tenancy híbrida intencional: null = plataforma)
- `Modules\Jana\Entities\MetaApuracao` — apurações por meta

### US cobertas (já existem em SPEC)

US-COPI-010, US-COPI-011, US-COPI-012 (mesmas que cobrem [Dashboard](RUNBOOK-dashboard.md))

## 2. 🚨 ALERTA TIER 0 descoberto (P0 bloqueador)

**Multi-tenant isolation violado** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)):

```php
// MetasController.php:18 — VAZAMENTO
$metas = Meta::orderByDesc('ativo')->orderBy('nome')->get();

// MetasController.php:48 — VAZAMENTO
$meta = Meta::findOrFail($id);

// MetasController.php:64 — VAZAMENTO
$meta = Meta::findOrFail($id);
```

Sem `where('business_id', session('user.business_id'))` nem global scope no Model, qualquer user logado pode listar/visualizar/editar/deletar metas de outros tenants via mudança de `id` na URL.

**Decisão de PLAN:** F2 BACKEND BASELINE precisa **primeiro** corrigir o vazamento (com Pest fixture validando isolamento) ANTES de qualquer trabalho de UI. Fix proposto:
1. Adicionar `BusinessIdScope` no Model `Meta` (com exceção explícita pra `business_id IS NULL` = plataforma, só visível pra superadmin)
2. Adicionar `BusinessIdScope` no Model `MetaApuracao` (cascata via `meta_id` → meta.business_id)
3. Migration ratchet: `business_id NOT NULL` em metas com origem `manual` (origem `plataforma` mantém NULL)
4. Pest test em `tests/Feature/Modules/Jana/MetasMultiTenantTest.php` cobrindo:
   - User biz=4 NÃO vê meta biz=1
   - User biz=4 NÃO consegue editar meta biz=1 via URL injection
   - User biz=4 NÃO consegue listar metas plataforma (origem `plataforma`)
   - Superadmin consegue ver metas plataforma + todos businesses

## 3. Pré-flight (skill `mwart-quality` — 9 checks)

| Check | Status | Ação |
|---|---|---|
| 1. Controller usa `Inertia::render` | ❌ todas 5 telas usam `view()` | F3 |
| 2. Page Inertia em `resources/js/Pages/Jana/Metas/` existe | ❌ pasta não existe | F3 |
| 3. Controller filtra por `business_id` | 🚨 **NÃO** — Tier 0 violation | F2 prioridade absoluta |
| 4. Pest fixture do `store()` antes de mexer | ❌ não existe | F2 obrigatório ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) |
| 5. Página vive dentro de `AppShellV2` | n/a (criar) | F3 |
| 6. Tokens shadcn semânticos | n/a (criar) | F3 |
| 7. PT-BR em todos labels | n/a (criar) | F3 |
| 8. Dark mode validado | n/a (criar) | F3 |
| 9. Build local + manifest check | n/a | F3 final |

## 4. Plano por fase (ADR 0104 — 5 fases obrigatórias)

### F1 — PLAN ✅ (este documento)

### F2 — BACKEND BASELINE (com Tier 0 fix embutido)

**Não tocar Page Inertia ainda.** Trabalho 100% PHP/migration/test.

1. **Pest fixture baseline** — `tests/Feature/Modules/Jana/MetasControllerBaselineTest.php`:
   - `it('lista metas do business em foco')` — fixture biz=1 com 2 metas, biz=4 com 3 metas, login biz=4, GET `/copiloto/metas` retorna 3
   - `it('cria meta com business_id auto-derivado da session')`
   - `it('redirect store→show com id correto')`
   - `it('soft-delete via destroy seta ativo=false')`
   - `it('show 404 quando meta de outro tenant')` ← red, vai virar verde após fix
2. **`BusinessIdScope` no Meta + MetaApuracao** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
3. **Migration** — backfill `business_id` em metas existentes baseado em `criada_por_user_id`→`users.business_id`. Metas órfãs (sem user) → flag `origem=plataforma`, `business_id=null`
4. **Refactor Controller** — todas queries scopadas; `store()` injeta `business_id = session('user.business_id')` automaticamente; `destroy/update/show/edit` confiam no scope
5. **`reapurar()` stub permanece stub** (fora de escopo MWART; abrir US separada US-COPI-METAS-REAPURAR)
6. **Verde Pest** — todos casos passam

**DoD F2:** `php artisan test --filter=MetasControllerBaseline` verde + `jana:health-check` reporta `multi_tenant_isolation: OK` pra metas.

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
| F2 | Tier 0 fix + Pest baseline + migration | 1 dia útil |
| F1.5 | Screenshots + comparativos 15 dimensões | 0.5 dia (Wagner síncrono ~10min/tela × 5) |
| F3 | 5 telas Inertia (1 por PR) | 2-3 dias úteis |
| F4 | Smoke biz=1 + cross-tenant manual | 0.5 dia |
| F5 | Canary 7 dias + cleanup | **7 dias relógio mundo real** (não-codável, [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) |

**Total:** ~4-5 dias úteis de trabalho codável + 7 dias relógio canary = ~2 semanas calendário.

## 6. Riscos

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Backfill `business_id` falha em metas órfãs | Média | Alto (drop tenant data) | Migration com `--dry-run` em produção snapshot antes; backup `metas` + `meta_apuracoes` |
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

**Última atualização:** 2026-05-09 — PLAN F1 draft, aguardando aprovação Wagner.
