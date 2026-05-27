---
slug: como-integrar-c1-compras-converge-purchase
title: "Convergência C1 — botão + drawer de /compras delegam pra /purchases/{create,edit,show}"
type: como-integrar
date: 2026-05-25
status: introspectivo (não executado)
related_adrs: [0093, 0094, 0104, 0107, 0114, 0141, 0149]
related_specs: [memory/requisitos/Compras/SPEC.md]
related_us: [US-COM-001, US-COM-002, US-COM-004]
pii: false
owner: wagner
---

# Convergência C1 — /compras (cockpit) reusa /purchases ({create,edit})

> Decisão Wagner 2026-05-25: parar de prometer Wave 8 com `Pages/Compras/Create.tsx`. Trilho A (Purchase MWART piloto Wave2 B5) já tem `/purchases/create|edit|show` Inertia React funcional. Cockpit Compras vira shell de listagem + KPIs + drawer e **delega** CRUD pro trilho A.

Auditoria 100% INTROSPECTIVA — só leu código + memory. Sem web. Sem commit. Sem MCP task.

---

## Fase 1 — INVENTÁRIO (o que já existe?)

### Tabela de descoberta

| O que procurei | Onde achei | Status |
|---|---|---|
| Purchase/Create.tsx Inertia React | `resources/js/Pages/Purchase/Create.tsx` (520 LOC, charter Tier A F3 ★) | **completo** — aguarda smoke Wagner |
| Purchase/Edit.tsx Inertia React | `resources/js/Pages/Purchase/Edit.tsx` (502 LOC, charter Tier A) | **completo** — `canBeEdited` + bloqueio devolução |
| Purchase/Show.tsx Inertia React | `resources/js/Pages/Purchase/Show.tsx` (379 LOC, sem charter) | **completo** |
| Dual-response Blade/Inertia | `PurchaseController:73, 400, 928, 697` (`?v=2` ou header `X-Inertia`) | ativo — pegadinha crítica (§Fase 2) |
| Cockpit Compras Index | `resources/js/Pages/Compras/Index.tsx` (828 LOC) | Wave 1-4 scaffold |
| ComprasController backend | `Modules/Compras/Http/Controllers/ComprasController.php` (128 LOC) | só `index()` + `show()` JSON (Wave 5) |
| Rota `/compras/create` registrada | `Modules/Compras/Routes/web.php` | **AUSENTE** — 404 hoje |
| Botão "+ Nova compra" disabled | `Pages/Compras/Index.tsx:240-242` | promete Wave 8 — alvo C1 |
| Botão "↓ Importar XML" disabled | `Pages/Compras/Index.tsx:237-239` | promete Wave 6 — **NÃO** entra em C1 |
| Sidebar entry "Compras" → `/compras` | `Modules/Compras/Http/Controllers/DataController.php:106` | ativo via `\Menu::modify('admin-sidebar-menu')` |
| Sidebar `primary.href = /compras/create` | `DataController.php:114` | **link quebrado hoje (404)** — alvo C1 |
| Sidebar duplicação Purchase × Compras | `app/Http/Middleware/AdminSidebarMenu.php:260-271` | **JÁ resolvido** 2026-05-22 P3 (entry duplicada comentada por Wagner) |
| Topnav módulo Compras | `config/core_topnavs.php:59-94` | registrado em key `'Purchase'` com label "Compras", sub-itens `/purchases/*` |
| Permissions `compras.*` declaradas | `Modules/Compras/Http/Controllers/DataController.php:46-72` | catálogo `compras.{view,create,edit,delete,import_xml}` — **não há alias** com `purchase.*` |
| AcoesDropdown navegando edit/show | `Pages/Compras/components/AcoesDropdown.tsx:115, 108, 146, 155, 162` | **PARCIAL** — `navigateBlade('/purchases/{id}/edit')` via `window.location.href` (não Inertia → cai no Blade sem `?v=2`) |
| ADR proposta convergência | `memory/decisions/proposals/compras-modulo-greenfield-hibrido.md` | **AUSENTE** (SPEC referencia mas não está no repo) |
| Sessão prévia "como-integrar-compras" | `memory/sessions/2026-05-21-como-integrar-compras.md` | **AUSENTE** (SPEC referencia mas glob não encontra) |
| SPEC Compras §3 US-COM-004 | `memory/requisitos/Compras/SPEC.md:90-101` | **INVERTE** com C1 ("`/purchases` → 301 → `/compras`") |
| Tests Wave2 Inertia Purchase | `tests/Feature/Purchase/Wave2{Create,Edit}{Baseline,Inertia}Test.php` (4 files) | funcionais — dual-path testado |
| Test Compras Index | `Modules/Compras/Tests/Feature/ComprasIndexTest.php` (3 testes) | verde |

### Veredito Fase 1

**Estende — não cria do zero.** A peça nova de C1 é ~30 linhas:
1. trocar 2 botões em `Pages/Compras/Index.tsx:237-242`
2. trocar fluxo do AcoesDropdown linha 115/146/155/162 (que hoje usa `window.location.href` sem `?v=2`)
3. corrigir `DataController:114` (`/compras/create` → `/purchases/create`)

NÃO precisa criar `Pages/Compras/Create.tsx`. NÃO precisa criar `Pages/Compras/Edit.tsx`. NÃO precisa controller novo no `Modules/Compras/`. Reuso integral 1.401 LOC + 4 testes Pest já em smoke.

Economia: ~6-8h IA-pair (Wave 8 inteira) + manutenção 1.401 LOC paralelas + charter Tier A duplicado.

---

## Fase 2 — PEGADINHAS APLICÁVEIS

Filtradas pela natureza do C1 (trocar entry-point + AcoesDropdown). Lista enxuta, só relevantes.

### P1 — Dual-response Blade↔Inertia exige `?v=2` ou header `X-Inertia` (CRÍTICA)

**Fonte:** `PurchaseController:73, 400, 697, 928` + ADR 0104 MWART + ADR 0149.

`/purchases/create`, `/purchases/{id}/edit`, `/purchases/{id}` retornam Inertia React **só se** o request tiver header `X-Inertia: true` OU query string `?v=2`. Sem isso → Blade legacy (`view('purchase.create')`).

`<a href="/purchases/create">` simples cai no Blade legacy.
`router.visit('/purchases/create')` injeta `X-Inertia` automaticamente → React.
`window.location.href = '/purchases/{id}/edit'` (AcoesDropdown atual) **cai no Blade**.

**Implicação C1:**
- Botões `+ Nova compra` e edit no AcoesDropdown precisam usar `router.visit(...)` (Inertia) ou `?v=2` na URL. Nada de `<a href>` puro nem `window.location.href` sem fallback.

### P2 — Tier 0 multi-tenant `business_id` (ADR 0093 IRREVOGÁVEL)

**Boa surpresa:** brief Wagner suspeitou de divergência (`auth()->user()->business_id` em Purchase vs `session('user.business_id')` em Compras). **Não há divergência.**

`PurchaseController` linhas 66, 354, 482, 634, 834, 1057, 1186, 1284, 1342, 1432 → **todas** usam `request()->session()->get('user.business_id')`. Igual ao ComprasController:43.

C1 não toca scope — só roteamento. Tier 0 fica intacto.

### P3 — Charter Tier A Purchase/Create.tsx ★ — não invalidar

**Fonte:** `resources/js/Pages/Purchase/Create.charter.md` status "F3 implementado (aguarda smoke Wagner)" + ADRs 0104/0093/0114/0149.

C1 não muda Purchase/Create.tsx (só seu entry-point externo). Charter Tier A do trilho A **permanece intocado** — Wagner ainda valida smoke F1.5 visual gate (ADR 0107).

Implicação: **NÃO precisa novo visual gate F1.5 pra C1**. Visual já validado é da `/purchases/create` que continua igual; só o caller mudou.

### P4 — Charter `Pages/Compras/Index.charter.md` § Non-Goals + Anti-hooks colidem com C1

**Fonte:** `resources/js/Pages/Compras/Index.charter.md:52-54, 77`.

Charter v1 declara:
- ❌ Non-Goal: "NÃO permite criar compra inline — botão '+ Nova compra' disabled. Form completo Wave 8 (`/compras/create`)"
- ❌ Non-Goal: "NÃO toca `/purchases` legacy — deprecação só na Wave 8 via 301 + feature flag"
- ⚠️ Anti-hook: "Aparecer botão 'criar compra inline' sem Wave 8 — drift"

C1 quebra **explicitamente** esses 2 Non-Goals + o Anti-hook. **Precisa amendment do charter v1 → v2** (`charter_version: 2`, last_validated 2026-05-25). Sugestão de redação no §Fase 3.

### P5 — SPEC §3 US-COM-004 INVERTE direção da deprecação

**Fonte:** `memory/requisitos/Compras/SPEC.md:90-101` US-COM-004 "Deprecar `/purchases` legacy".

R-COM-302 hoje: `/purchases` → 301 → `/compras` quando flag ON.
C1 inverte: **`/purchases`** continua canônico (Inertia React Wave2 B5) e `/compras` só é cockpit de listagem.

US-COM-002 (criar compra manual) R-COM-101 ("Wrapper sobre PurchaseController::store extraído pra ComprasService::criar()") também fica vazio — C1 mantém `PurchaseController::store` como canon.

**Implicação:** SPEC §3 + US-COM-002 + US-COM-004 precisam amendment ou superseded.

### P6 — Permissions `compras.*` × `purchase.*` (gap, não bug)

**Fonte:** `DataController.php:48-72` declara 5 permissions `compras.{view,create,edit,delete,import_xml}`. `ComprasController:39, 91` usa `compras.view`. `PurchaseController:63, 350, 477` usa `purchase.{view,create,update,delete}`.

User com `purchase.create` mas SEM `compras.view` → vê `/purchases/create` mas é bloqueado em `/compras` (403). Inversamente, user com `compras.view` SEM `purchase.create` → vê cockpit `/compras`, clica "+ Nova compra", **leva 403** em `/purchases/create`.

C1 implica:
- Cockpit `/compras` esconde botão "+ Nova compra" se `!auth()->user()->can('purchase.create')` (não `compras.create`)
- Inversa: Edit também checa `purchase.update`
- **Alternativa V2:** PermissionsSeeder cria alias `compras.create` ↔ `purchase.create` (preserva nomenclatura módulo nova mas reusa permission existente). Não bloqueador V1.

### P7 — F3 LICOES anti-padrão M-AP-1 ("ler 2 arquivos do repo antes de TSX/PHP")

**Fonte:** `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` §M-AP-1.

C1 é F6 Soft (sem novo Cowork → React), MAS edita 3 arquivos sensíveis (Index.tsx, AcoesDropdown.tsx, DataController.php). **Ler antes**:
- `PurchaseController::create()` para confirmar `?v=2` exigido
- `Pages/Compras/Index.tsx` linhas 1-100 + 237-242 (estilo Cowork bundle CSS)
- AcoesDropdown.tsx inteiro (já navega Blade — só trocar Blade → Inertia)

### P8 — Topnav `core_topnavs.php` chave `'Purchase'` com label "Compras"

**Fonte:** `config/core_topnavs.php:59-94`.

Chave PHP é `'Purchase'` (não `'Compras'`), mas label visível é "Compras". `useAutoModuleNav()` ativa esse topnav pra URLs root `/purchases`. **Em `/compras` o topnav é renderizado por `Modules/Compras/Resources/menus/topnav.php` se existir** — hoje ausente (Glob retornou 0).

C1 pode opcionalmente:
- (a) NÃO adicionar topnav em Modules/Compras → `/compras` fica sem topnav próprio (estado atual)
- (b) Criar `Modules/Compras/Resources/menus/topnav.php` listando `/compras` (lista) + `/purchases/create` (nova compra) + `/purchase-return` (devoluções) + `/purchase-order` (pedidos). Convergência visual total.

Pendente Wagner — opção (b) é mais coerente com C1 mas adiciona ~30 LOC PHP. **Sugestão: deixar pra wave de polimento depois.**

---

## Fase 3 — PONTO DE PLUGUE (onde tocar)

### Mapa concreto

| Peça | Arquivo:linha | Ação |
|---|---|---|
| Botão "+ Nova compra" | `resources/js/Pages/Compras/Index.tsx:240-242` | Trocar `disabled` por `onClick={() => router.visit('/purchases/create')}` + check perm `purchase.create` via prop `permissions` (a adicionar no Controller) |
| Botão "↓ Importar XML" | `resources/js/Pages/Compras/Index.tsx:237-239` | **NÃO TOCAR em C1** — segue disabled aguardando Wave 6 (bridge DFe→Compra) |
| AcoesDropdown "Editar" | `resources/js/Pages/Compras/components/AcoesDropdown.tsx:115` | Substituir `navigateBlade('/purchases/{compraId}/edit')` por `router.visit('/purchases/${compraId}/edit')` (assina header X-Inertia, abre React) |
| AcoesDropdown "Ver" (linha) | `Index.tsx + AcoesDropdown.tsx:100-103` | **DECIDIR Wagner:** continua abrindo Drawer in-page (estado atual)? OU passa a navegar `/purchases/{id}` Show React? Sugestão: **manter Drawer in-page** (5 tabs Cowork denso é diferencial) e adicionar **link secundário "Ver tela cheia"** nos pés do drawer apontando `/purchases/{id}?v=2` |
| AcoesDropdown "Impressão" | `AcoesDropdown.tsx:108` | Manter `window.open('/purchases/print/{id}')` — print é Blade legacy ainda (não migrado) |
| AcoesDropdown "Reembolso" | `AcoesDropdown.tsx:146` | Manter `navigateBlade('/purchase-return/add/{id}')` — devolução é Blade legacy |
| AcoesDropdown "Atualizar status" | `AcoesDropdown.tsx:152-156` | Mudar pra `router.visit('/purchases/{id}/edit#status')` (Inertia → cai no Edit.tsx React) |
| AcoesDropdown "Notif. pendente" | `AcoesDropdown.tsx:162` | Mesmo: `router.visit('/purchases/{id}/edit#notify-pending')` |
| Sidebar primary action quebrado | `Modules/Compras/Http/Controllers/DataController.php:114` | Trocar `'href' => '/compras/create'` por `'href' => '/purchases/create'`. Update comentário linhas 100-102 (remover menção "Wave 3"). |
| Permission gate botão "+ Nova compra" | `ComprasController::index()` Inertia::render `Compras/Index` | Adicionar prop `permissions: ['create' => auth()->user()->can('purchase.create'), 'edit' => auth()->user()->can('purchase.update'), 'delete' => auth()->user()->can('purchase.delete')]` |
| Charter `Pages/Compras/Index.charter.md` | linhas 52-54 (Non-Goals) + 77 (Anti-hooks) + frontmatter `charter_version: 1` → 2 | Reescrever Non-Goal "NÃO permite criar inline" → "NÃO replica form Create — delega `/purchases/create` Inertia (C1)". Anti-hook "botão criar inline drift" → "botão criar inline que NÃO seja delegação `router.visit('/purchases/create')`" |
| SPEC `memory/requisitos/Compras/SPEC.md` | §3 + US-COM-002 R-COM-101 + US-COM-004 R-COM-302 + Wave 8 referência | Amendment date 2026-05-25 — Caminho C1 substitui Caminho B Wave 8 prometida. US-COM-002 vira "delegada a US-PUR-Wave2" (ou similar). US-COM-004 INVERTE direção: `/compras` cockpit não substitui `/purchases`, ambos coexistem. |
| ADR nova (opcional) | `memory/decisions/proposals/compras-purchase-convergencia-c1.md` | **Recomendado** — slug sugerido `compras-purchase-convergencia-c1-cockpit-delega-crud-purchase` (supersedes da proposta `compras-modulo-greenfield-hibrido` que sequer existe — apenas referencia). |
| Test Pest Compras navega `/purchases/create` | `Modules/Compras/Tests/Feature/ComprasIndexTest.php` | Adicionar test 4: "clique '+ Nova compra' navega via Inertia pra `/purchases/create` e retorna 200 React" — usa `actingAs` + `withHeaders(['X-Inertia' => 'true'])->get('/purchases/create')` assertComponent Purchase/Create |
| Test Pest Purchase smoke convergência | `tests/Feature/Purchase/Wave2CreateInertiaTest.php` (a estender) | Adicionar test smoke "user navega de `/compras` → `/purchases/create` mantém sessão multi-tenant + permission ok" |

### Plugues que NÃO existem (⚠️ atenção)

- ⚠️ **`Modules/Compras/Resources/menus/topnav.php`** — não existe. C1 V1 pode passar sem (mantém topnav `Purchase` do `core_topnavs.php`). V2 pode criar.
- ⚠️ **ADR proposta `compras-modulo-greenfield-hibrido`** referenciada na SPEC NÃO existe no repo. Status SPEC ainda `proposed` espera essa ADR — C1 pode promover a SPEC direto pra `accepted` via ADR `compras-purchase-convergencia-c1` substituindo a hipótese hibrida.
- ⚠️ **Session prévia `2026-05-21-como-integrar-compras.md`** referenciada na SPEC NÃO existe (Glob 0 resultados). Não-bloqueador, só hygiene de links quebrados.

---

## Fase 4 — CHECKLIST PRÉ-CÓDIGO

```markdown
## Pré-código checklist — C1 Compras converge Purchase

### Antes de Edit/Write
- [ ] Ler RUNBOOK existente:
  - `memory/requisitos/Compras/RUNBOOK-compras-index.md` (existe)
  - `memory/requisitos/Inventory/RUNBOOK-purchase-create.md` (referenciado em Purchase/Create.charter.md — confirmar)
- [ ] Confirmar feature flag necessária? **NÃO** — sem rollout gradual. C1 reuso direto, rollback é git revert único PR.
- [ ] Schema migration necessária? **NÃO** — só roteamento + UI.
- [ ] ADR nova necessária? **SIM** — recomendado `compras-purchase-convergencia-c1` slug supersedes da hipótese hibrida proposed nunca aceita.

### Pegadinhas a respeitar (filtradas C1)
- [ ] P1 — `router.visit(...)` Inertia em vez de `<a href>` ou `window.location.href` (caso contrário cai no Blade legacy de `/purchases/*`)
- [ ] P3 — NÃO tocar Purchase/Create.tsx nem charter Tier A. Só caller mudou.
- [ ] P4 — Amendment `Pages/Compras/Index.charter.md` v1 → v2 (Non-Goals + Anti-hooks atualizados)
- [ ] P5 — Amendment SPEC §3 + US-COM-002 + US-COM-004 OR superseded por ADR nova
- [ ] P6 — Permission gate botão usa `purchase.create` (não `compras.create`) até alias V2
- [ ] P7 — Ler 3 arquivos antes de Edit: PurchaseController:60-80, Index.tsx:230-250, AcoesDropdown.tsx inteiro

### Pontos de plugue (em ordem)
- [ ] Backend `ComprasController.php` — adicionar prop `permissions` (`purchase.create/update/delete` do auth user)
- [ ] Frontend `Pages/Compras/Index.tsx:240-242` — botão "+ Nova compra" → `onClick={() => router.visit('/purchases/create')}` + gate `props.permissions.create`
- [ ] Frontend `Pages/Compras/components/AcoesDropdown.tsx:115, 152, 162` — Blade → Inertia (`navigateBlade` → `router.visit`)
- [ ] Sidebar `DataController.php:114` — `/compras/create` → `/purchases/create`
- [ ] Charter `Pages/Compras/Index.charter.md` — version 2, Non-Goals + Anti-hooks reescritos
- [ ] SPEC `memory/requisitos/Compras/SPEC.md` — amendment §3 + US-COM-002 + US-COM-004 OU superseded por ADR
- [ ] ADR `memory/decisions/proposals/compras-purchase-convergencia-c1.md` (criar — opcional mas recomendado)
- [ ] Test: `Modules/Compras/Tests/Feature/ComprasIndexTest.php` — adicionar test smoke navega `/purchases/create` via Inertia retorna React
- [ ] Test: `tests/Feature/Purchase/Wave2CreateInertiaTest.php` — adicionar smoke convergência multi-tenant cross-modulo

### Smoke pós-deploy
- [ ] biz=1 (Pest) — clique "+ Nova compra" em `/compras` abre Purchase/Create.tsx (assertComponent)
- [ ] biz=1 (Pest) — clique "Editar" no AcoesDropdown abre Purchase/Edit.tsx (assertComponent)
- [ ] biz=4 (ROTA LIVRE prod, canary opcional Larissa) — fluxo end-to-end clique cockpit → criar compra real Larissa vestuário
- [ ] Chrome MCP `read_console_messages` — 0 erros JS após navegação Inertia cross-page

### Estimativa total (IA-pair, ADR 0106)
- Edits código: ~30min IA-pair (3 arquivos pequenos)
- Amendment charter + SPEC + ADR: ~45min
- Tests novos (2): ~30min
- Smoke biz=1: ~5min CI + ~15min review
- Smoke biz=4 Larissa: depende agenda dela (não estimável aqui)
- **Total IA-pair editável:** ~2h. Margem 2x ADR 0106: **4h**. Smoke real (Larissa): relógio mundo.
```

---

## Sinais / drifts a monitorar pós-merge

- ⚠️ Se aparecer `Pages/Compras/Create.tsx` ou `Pages/Compras/Edit.tsx` → **DRIFT** — quebra C1
- ⚠️ Se `DataController:114` voltar a apontar `/compras/create` → drift (link quebrado)
- ⚠️ Se PR contém migration nova de tabela `compras_*` ou `purchases_*` → fora do escopo C1
- ⚠️ Se charter Compras/Index Anti-hooks NÃO for atualizado → próximas waves vão re-introduzir botão disabled

---

## PENDENTE — perguntar Wagner

1. **ADR proposta `compras-modulo-greenfield-hibrido`** que SPEC referencia não existe no repo. Foi descartada ou está fora do worktree? Se descartada, C1 ADR vira nascimento, não supersedes.
2. **Drawer cockpit /compras** — após C1, manter Drawer 5 tabs in-page OU substituir por `router.visit('/purchases/{id}')` pra Show React? Recomendação: manter Drawer (Cowork denso é diferencial UX da listagem) e adicionar link "Ver tela cheia" no rodapé do drawer.
3. **Topnav `Modules/Compras/Resources/menus/topnav.php`** — criar agora ou empurrar pra wave de polimento? Hoje topnav global `core_topnavs.php` key `'Purchase'` cobre.
4. **Permission alias `compras.* ↔ purchase.*`** — criar PermissionsSeeder alias agora ou usar `purchase.*` direto nos gates? Sugestão: V1 usa `purchase.*` (zero migration), V2 cria alias se Wagner mantiver módulo Compras como conceito separado.
