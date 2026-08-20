---
owner: W
last_validated: "2026-08-19"
slug: officeimpresso-runbook-logs
title: "Officeimpresso — RUNBOOK Logs (Máquinas Cadastradas + Timeline por máquina)"
type: runbook
module: Officeimpresso
status: ativo
date: 2026-08-19
related:
  - 0104  # Processo MWART canônico (mãe)
  - 0093  # Multi-tenant Tier 0
  - 0189  # PageHeader canon v3.1
  - 0190  # Primary universal roxo 295
  - 0275  # Gate nasce advisory + forward-only
  - 0358  # Doutrina de teste — tenant 98
---

# RUNBOOK — Officeimpresso `Logs/` (Onda 1 da migração React)

> **Escopo:** as **2 telas P0** do [RUNBOOK-migracao-react.md](RUNBOOK-migracao-react.md) — as de maior uso do módulo.
> **Não substitui** o RUNBOOK-migracao-react (que é o plano do módulo inteiro, 14 telas); este é o
> RUNBOOK **de tela** que a [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) exige na F1
> e que o hook [`block-mwart-violation.mjs`](../../../.claude/hooks/block-mwart-violation.mjs) procura.
> **Um RUNBOOK cobre as duas** porque o hook resolve pelo **subdir** (`Logs/` → `RUNBOOK-logs.md`),
> não pelo filename genérico `Index`.

| # | Blade origem | Rota | Page alvo | Padrão de Tela |
|---|---|---|---|---|
| 1 | `licenca_log/index.blade.php` | `GET /officeimpresso/licenca_log` | `Officeimpresso/Logs/Index` | [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md) |
| 2 | `licenca_log/timeline.blade.php` | `GET /officeimpresso/licenca_log/timeline/{licenca_id}` | `Officeimpresso/Logs/Timeline` | [PT-07 Feed-Timeline](../_DesignSystem/padroes-tela/PT-07-Feed-Timeline.md) |

## Estado final esperado

Duas Pages Inertia servidas por `LicencaLogController`, **dentro do módulo dono**
(`Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/`), atrás de feature flag
`useV2Logs`, com Blade intacto como rota de fuga até o cutover (F5).

## 1. Objetivo

Migrar as duas telas de operação de licenças desktop para o shell React, **sem perder um campo**
e **sem mudar a operação**. Nenhuma capacidade nova nesta onda — o ganho é o shell único
(AppShellV2, busca global, tema) e sair do AdminLTE/DataTables/jQuery.

**Quem usa:** [W] (superadmin) e o suporte com a permissão delegável `officeimpresso.access`
(concedida em 2026-07-29/30). **Visão cross-empresa é POR DESIGN aqui** — a WR2 é a fornecedora do
desktop; quem dá assistência precisa ver a máquina do cliente, não a própria. Isso **não** é furo do
[ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md): a guarda é `podeVerTodasEmpresas()`,
e quem não tem a permissão continua preso ao próprio `business_id`.

## 2. Pré-condições

| # | Pré-condição | Como conferir |
|---|---|---|
| 1 | Permissão `officeimpresso.access` existe e está atribuída | `php artisan tinker --execute="echo auth_user_can_check"` — ver `LicencaLogController::podeVerTodasEmpresas()` |
| 2 | Item de menu do módulo aparece no sidebar React | `DataController::modifyAdminMenu()` + `SIDEBAR_GROUPS` grupo `office` — skill [`sidebar-menu-arch`](../../../.claude/skills/sidebar-menu-arch/SKILL.md) |
| 3 | Glob de módulo do Vite ativo nas **duas** pontas | `grep -n "Modules/\*/Resources/js/Pages" resources/js/app.tsx resources/js/ssr.tsx` — tem que casar nos dois |
| 4 | Casing `Resources/` **maiúsculo** | `git ls-files "Modules/Officeimpresso/Resources/js/**"` — o glob do Vite é case-sensitive e no Windows o `mkdir` funde os dois |
| 5 | F2 mergeada antes de tocar `.tsx` | `logs-parity.md` existe + Pest baseline verde no CT 100 |

## 3. Passo-a-passo

### F2 — BACKEND BASELINE (1 PR)

1. **Pest baseline ANTES de mexer** — ≥5 fixtures cobrindo o comportamento atual do `index()` e do
   `timeline()`: guarda de acesso (403 sem permissão), escopo por business de quem não é
   `podeVerTodasEmpresas()`, os 5 filtros (`q`, `estado_atual`, `business_id`, `licenca_id`, `hd`),
   os 4 KPIs, e o 404 do `timeline()` com `licenca_id` inexistente.
2. **Action dual** no `LicencaLogController@index` e `@timeline`: devolve `Inertia::render` quando
   a flag está ligada; senão `view('officeimpresso::...')` como hoje.
   > ⚠️ **O `Inertia::render` entra no PR da TELA, não no da F2** (medido 2026-08-19). Pôr o render
   > antes do `.tsx` existir cria um render órfão — 500 esperando alguém ligar a flag — e o
   > `OrphanRenderGateTest` (required) reprova, corretamente: *"Inertia::render apontando pra page
   > inexistente — tela órfã/morta"*. A allowlist dele **não** serve de saída: o docblock crava que
   > ela **só encolhe** e é pra dead code em remoção, não pra tela que chega depois.
   > O que a F2 entrega de verdade é a **extração do payload** (`buildMaquinasPayload` /
   > `buildKpisPayload`) e o baseline Pest; a flag e o render viajam com a tela.
   > *(O DoD da skill `mwart-process` lista o dual na F2 — para a PRIMEIRA tela de um módulo isso é
   > cedo demais, porque não existe page nenhuma ainda pra apontar.)*
   > ⚠️ **Correção 2026-08-19 (medida na implementação).** A primeira redação desta linha e o DoD
   > da skill `mwart-process` mandam condicionar **também** ao header `X-Inertia`. **Está errado**
   > e quebraria a tela: o primeiro carregamento do Inertia é um GET de HTML comum e **não manda
   > esse header** — exigi-lo faria a React nunca abrir por navegação direta. O único dual em
   > produção (`SellController::create` + `SellPosController::create`) decide **só pela flag**;
   > `git grep 'X-Inertia' -- '*/Http/Controllers/*.php'` não acha nenhum controller gateando
   > página por header. Seguimos o código que roda, não o DoD.
3. **Feature flag** `useV2OfficeimpressoLogs`, default **OFF**.
   > ⚠️ **Correção 2026-08-19.** A primeira redação pedia um comando
   > `officeimpresso:enable-v2 {business_id}`. **Não existe esse padrão no projeto** —
   > `git grep -lE 'enable-v2|enableV2' -- '*.php'` devolve zero. Flag no oimpresso é
   > `FeatureFlagService` sobre GrowthBook (CT 100), ligada por toggle na UI, sem deploy.
   > Nome segue a convenção da única em produção: `useV2SellsCreate` → `useV2<Modulo><Tela>`.
   > Enquanto o GrowthBook não conhecer a chave, o `fallbackDefaults` não a lista e o default
   > é `false` — o Blade segue servindo.
4. **`Inertia::defer`** nas props caras — a lista de máquinas faz JOIN + N enriquecimentos e os KPIs
   são 4 `count()`; ambos entram em `defer` ([RUNBOOK-inertia-defer-pattern](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md)).
   `filters` e `permissions` ficam eager (state de UI, ~0ms).
5. **`logs-parity.md`** — mapa campo-a-campo Blade↔React ([template](../_DesignSystem/PARITY-TEMPLATE.md)).

### F3 — FRONTEND (1 PR por tela)

6. `Logs/Index.tsx` + `Index.charter.md` + `Index.casos.md` — PT-01, 6 slots.
7. `Logs/Timeline.tsx` + `Timeline.charter.md` + `Timeline.casos.md` — PT-07.

### F4 — QA

8. Todo item de severidade `alta` do `logs-parity.md` tem teste de **comportamento** que quebra se o
   campo parar de aparecer/persistir, citando o id do UC. **Presença do arquivo não conta.**
9. Smoke real na tela nova (screenshot 1280 + 1440, sem scroll horizontal com sidebar aberta).

### F5 — CUTOVER

10. Sem janela de aviso a cliente: o módulo é interno (WR2 + suporte), não tem cliente externo na tela.
    Flag ON → monitorar → remover Blade + a flag + o comando.

## 4. Tokens CSS

O Blade traz um design-system próprio (`officeimpresso::layouts.partials.design-system`) com classes
`oi-*` (`oi-page`, `oi-kpi`, `oi-card`, `oi-table`, `oi-pill`, `oi-btn`). **Nada disso atravessa.**

| `oi-*` legado | Canon React |
|---|---|
| `.oi-page` + `.oi-page-header` | `<AppShellV2>` + `<PageHeader>` |
| `.oi-kpi` (4 cards) | `<KpiCard>` |
| `.oi-card` + `.oi-table` | `<DataTable>` |
| `.oi-pill-ok` / `.oi-pill-blocked` | `<StatusBadge kind="licenca">` — **kind novo, criar** |
| `.oi-btn-danger` / `.oi-btn-success` | `<Button variant="destructive" ou "default">` |
| `.text-mono` | `font-mono` (Tailwind) |
| `bg-blue` / `bg-amber` / `bg-red` / `bg-green` dos ícones de KPI | tokens semânticos — **nunca cor crua** (R-DS-002) |

**`StatusBadge kind="licenca"` (a criar em F3, PR da Index):**

| value | label | variant |
|---|---|---|
| `ativa` | Ativa | `default` + `bg-success` |
| `maquina_bloqueada` | Máquina bloqueada | `destructive` |
| `empresa_bloqueada` | Empresa bloqueada | `destructive` |

## 5. Estados visuais

| Estado | Index | Timeline |
|---|---|---|
| **Loading** | skeleton da tabela dentro do `<Deferred>` (KPIs têm skeleton próprio) | skeleton do feed |
| **Vazio — sem filtro** | `<EmptyState>` com o texto do Blade: explica que a tabela é populada por `/connector/api/processa-dados-cliente` quando o Delphi envia CNPJ + HD | "Nenhum acesso registrado para esta máquina." |
| **Vazio — com filtro** | "Nenhuma máquina encontrada com os filtros aplicados." + ação **Limpar** | n/a |
| **Erro** | toast via flash global (`app.tsx`) | idem |
| **Filtro ativo** | chip removível por filtro (`business_id`, `licenca_id`, `hd`) — o Blade usa `alert-info` com link "Remover" | n/a |
| **404** | n/a | `licenca_id` inexistente devolve 404 (comportamento atual, preservar) |

## 6. Responsividade

Alvo **1280px com sidebar aberta** (monitor do cliente piloto é 1280 — [why-oimpresso](../../why-oimpresso.md)),
validar também 1440. A tabela tem **10 colunas** no Blade; a 1280 ela **vai** estourar se for porte
literal — as colunas de menor densidade informativa (`Location / CNPJ`, `Versão`) colapsam para
segunda linha da célula principal, como o Blade já faz com CNPJ. **Sem scroll horizontal na página**;
se precisar, o scroll é interno ao container da tabela.

## 7. Atalhos

Herdados do shell; nada específico da tela nesta onda. Se algum for adicionado: `removeEventListener`
no cleanup e bloqueio quando o foco está em `<input>`.

## 8. Component contract

```tsx
// Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Index.tsx
// namespace Inertia = 'Officeimpresso/Logs/Index' (o local do arquivo NAO muda o namespace)
import AppShellV2 from '@/Layouts/AppShellV2';            // default
import { PageHeader } from '@/Components/PageHeader';      // NAMED (barrel)
import KpiCard from '@/Components/shared/KpiCard';         // default
import DataTable from '@/Components/shared/DataTable';     // default
import EmptyState from '@/Components/shared/EmptyState';   // default
import StatusBadge from '@/Components/shared/StatusBadge'; // default
import { Deferred } from '@inertiajs/react';
```

> ⚠️ **O snippet do PT-01 erra os shapes.** Ele importa `DataTable`, `BulkActionBar` e `EmptyState`
> como **named** (`import { DataTable } from ...`) — os três são **default**. Medido em 2026-08-19 com
> `grep -nE "^export (default|const)"` nos 5 arquivos. É a mesma família do erro que a v1.1 do PT-01
> corrigiu (lá eram os *paths*, aqui são os *shapes*) — corrigir o PT-01 é PR à parte, deste módulo
> não é. Use a lista acima, que foi verificada.

**Props do `Logs/Index`:**

| Prop | Tipo | Defer? | Origem |
|---|---|---|---|
| `maquinas` | `Maquina[]` | **sim** | JOIN `licenca_computador` x `business` + enriquecimento por log |
| `kpis` | `{total_maquinas, maquinas_bloqueadas, empresas_bloqueadas, chamadas_24h}` | **sim** | 4 `count()` |
| `filters` | `{q, estado_atual, business_id, licenca_id, hd}` | não | query string |
| `permissions` | `{pode_ver_todas_empresas, pode_bloquear}` | não | Gate |

**Props do `Logs/Timeline`:** `maquina` (não — 1 row), `logs` (**sim** — até 200), `permissions`.

## 9. DoD checklist

- [ ] F2: Pest baseline >=5 fixtures **rodado no CT 100** (nunca local — [proibicoes §Ambiente](../../proibicoes.md))
- [ ] F2: `logs-parity.md` com todo campo do Blade mapeado + severidade
- [ ] F2: flag `useV2Logs` default OFF + comando artisan liga/desliga
- [ ] F3: 1 PR por tela, <=300 linhas, charter + casos ao lado do `.tsx`
- [ ] F3: `pages-colisao --check` verde (duas raízes de Pages podem colidir em silêncio)
- [ ] F3: prova de bundle é o **manifest**, não o exit code do build
- [ ] F4: cada item `alta` da paridade com teste de comportamento citando o UC
- [ ] F4: smoke com screenshot 1280 + 1440
- [ ] F5: flag ON, monitorar, remover Blade + flag + comando

## 10. Pegadinhas

1. **`toggle-block` é `GET` que muda estado.** `Route::get('/licenca_computador/{id}/toggle-block')`,
   protegido só por `confirm()` no browser. Em React isso vira `POST` — e é **divergência deliberada**
   de paridade (severidade `alta`), não port literal. Durante o dual-run as duas rotas coexistem;
   a `GET` sai no F5 junto com o Blade.
2. **A tela chamada `licenca_log/index` não lista log.** Lista **máquinas** (`licenca_computador`)
   enriquecidas com o último log. Nomear a Page de `Logs/Index` mantém a rota, mas o **título é
   "Máquinas Cadastradas"** — não "Logs". Não "consertar" isso renomeando a rota nesta onda.
3. **`licencas_log/index.blade.php`** (plural extra) não é rota de nada. O RUNBOOK do módulo manda
   **deletar**. Não migrar. Fora do escopo desta onda — some no F5.
4. **`business.bloqueado` desbloqueia a EMPRESA inteira**, não a máquina. Na coluna Ações do Blade os
   dois botões são visualmente parecidos e o de empresa é o mais destrutivo. Na React eles precisam
   ser distinguíveis sem ler o tooltip.
5. **`was_blocked_last` é tri-estado** (`null` = nunca houve log, mostra travessão; `true`/`false` =
   estado no último login). Tratar `null` como `false` perde informação.
6. **`effective_ts`** é `last_login ?? dt_ultimo_acesso` e existe só pra ordenação. Quando vem do
   cadastro (não do log), o Blade marca `(cadastro)` embaixo da data — manter essa distinção.
7. **`metadata` chega como string OU array.** O Blade faz `is_string(...) ? json_decode(...)` nos dois
   arquivos. O cast do model é `array`, mas as queries que usam `DB::table` fogem do cast.
8. **`timestamps = false`** no `LicencaLog` (só `created_at`). Factory/fixture que assume `updated_at` quebra.
9. **Tenant de teste é o 98** ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — **nunca** biz=4.

## 11. ADR de origem

- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — processo MWART, caminho único
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 (e por que o cross-empresa aqui é legítimo)
- [ADR 0189](../../decisions/0189-pageheader-canon-v3-1-cadastro-roxo.md) — PageHeader canon v3.1
- [ADR UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) — Constituição UI v2 (camada 3 = Padrão de Tela)
- [RUNBOOK-migracao-react.md](RUNBOOK-migracao-react.md) — plano do módulo inteiro (14 telas); esta é a Onda 1

---

**Última atualização:** 2026-08-19 — criado na F1 da Onda 1 (escopo escolhido por [W]).
