---
date: 2026-05-25
modulo: Compras
nota_atual: 38
nota_target: 70
bucket_atual: Critico
bucket_target: Bom
auditor: audit-senior-expert
versao_rubrica: module-grade-v3
inputs_lidos:
  - Modules/Compras/Http/Controllers/ComprasController.php (141 LOC)
  - Modules/Compras/Http/Controllers/DataController.php (127 LOC)
  - Modules/Compras/Http/Controllers/InstallController.php (90 LOC)
  - Modules/Compras/Services/ComprasService.php (283 LOC)
  - Modules/Compras/Routes/web.php (39 LOC)
  - Modules/Compras/Providers/ComprasServiceProvider.php (89 LOC)
  - Modules/Compras/Providers/RouteServiceProvider.php (28 LOC)
  - Modules/Compras/Tests/Feature/ComprasIndexTest.php (129 LOC)
  - Modules/Compras/module.json
  - Modules/Compras/SCOPE.md
  - resources/js/Pages/Compras/Index.tsx (849 LOC)
  - resources/js/Pages/Compras/Index.charter.md (134 LOC)
  - resources/js/Pages/Compras/components/Drawer.tsx (608 LOC)
  - resources/js/Pages/Compras/components/AcoesDropdown.tsx (232 LOC)
  - memory/requisitos/Compras/SPEC.md v0.2
  - memory/requisitos/Compras/BRIEFING.md
  - memory/requisitos/Compras/AUDITORIA-COMPRAS-2026-05-21.md (auditoria upstream completa)
  - memory/requisitos/Compras/DISCOVERY-LARISSA-COMPRAS.md
  - memory/requisitos/Compras/RUNBOOK-compras-index.md
  - memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md
  - Modules/Financeiro/Tests/Feature/MultiTenantIsolationTest.php (template canônico)
  - config/governance/module_clients.yaml (confirmado: Compras AUSENTE da lista)
  - php artisan module:grade Compras --detail (breakdown granular dos 9 checks)
websearches_executadas: 7
---

# AUDITORIA SÊNIOR · Módulo Compras — oimpresso

> **Bucket:** Crítico (38/100). Único do projeto. Tier 0 multi-tenant `D1.b=0/15` é **BLOQUEADOR** mandatório antes de qualquer feature nova.
> **Persona piloto:** Larissa @ ROTA LIVRE (biz=4, vestuário). **Sinal qualificado real** mas Compras hoje não está em prod pra ela (D5=0/15).
> **Caminho arquitetural já decidido:** B híbrido — wrapper Inertia sobre `transactions` polimórfica + TransactionUtil + Observer Financeiro ([ADR proposta `compras-modulo-greenfield-hibrido`](../../decisions/proposals/compras-modulo-greenfield-hibrido.md) + [`compras-purchase-convergencia-c1`](../../decisions/proposals/compras-purchase-convergencia-c1.md) C1 2026-05-25).

---

## 1. Estado Atual — Por que 38/100

Breakdown granular do `php artisan module:grade Compras --detail` (executado 2026-05-25 20:26 BRT):

| Dim | Score | Componente | Por quê |
|---|---:|---|---|
| **D1 Multi-Tenant** | **10/30** 🔴 | `[5/10] D1.a` neutro (sem Entities próprias) · `[0/15] D1.b` ZERO Pest cross-tenant biz=1 vs biz=99 · `[5/5] D1.c` sem Jobs (neutro) | **Único teste com `business_id` usa apenas biz=1 — não prova isolamento. Tier 0 IRREVOGÁVEL violado.** |
| **D2 Pest** | **7/20** 🟡 | `[5/8] D2.a` 1 test/3 controllers (ratio 0.33) · `[0/8] D2.b` ZERO padrão canônico (MultiTenant+Smoke+Scaffold com asserção) · `[2/4] D2.c` parcial 1 dir registrado | Só 1 arquivo Pest com 4 testes happy-path. Nenhum bate scenarios canônicos. |
| **D3 Doc** | **12/15** 🟢 | `[5/5] D3.a` sim · `[5/5] D3.b` idade 0d · `[2/3] D3.c` 1 charter/5 tsx (20% só Index tem charter) · `[0/2] D3.d` ausente RUNBOOK pro componente | SPEC+BRIEFING+RUNBOOK+CAPTERRA-FICHA OK. Falta charter pros 3 components (Drawer/AcoesDropdown/GradeMatrixInput). |
| **D4 Arquitetura** | **11/20** 🟡 | `[6/6] D4.a` 1 Service/3 Controllers · `[0/5] D4.b` sem FSM ADR 0143 · `[5/5] D4.c` 5 tsx/0 blade · `[0/4] D4.d` ausente | FSM "6 estágios" só desenhada no Drawer.tsx (`STAGES` const) — não persistida via `spatie/laravel-model-states`. Status real é string mistura UPos legacy. |
| **D5 Cliente real** | **0/15** 🔴 | `D5.e` ninguém usa | **AUSENTE de `config/governance/module_clients.yaml`** (confirmado grep). Larissa biz=4 vestuário sinalizou no DISCOVERY mas Compras nunca subiu prod nem canary pra ela. |
| **D6 Perf** | **6/10** 🟡 | `[4/4] D6.a` Inertia::defer OK em 4 props · `[2/3] D6.b` OTel placeholder · `[0/3] D6.c` ZERO paginate() com eager-load detectado | Defer OK no Controller. Service `listarCompras()` faz `paginate()` mas sem `->with([...])` explícito (`buscarDetalhe` tem with mas é single). |
| **D7 LGPD** | **2/10** 🔴 | `[0/4] D7.a` ausente PiiRedactor · `[2/3] D7.b` sem Models próprios parcial · `[0/3] D7.c` ausente retention | Drawer mostra `tax_number`/CNPJ + `mobile` + `email` do fornecedor SEM PiiRedactor. Sem retention policy. |
| **D8 Sec** | **2/8** 🔴 | `[0/3] D8.a` ausente throttle · `[2/2] D8.b` CSRF default OK · `[0/3] D8.c` 0 FormRequests/3 Controllers | Nenhum throttle no `/compras` index. Nenhum FormRequest — validação inline solta. |
| **D9 Obs** | **2/7** 🔴 | `[0/4] D9.a` 0/1 Services com OTel · `[2/3] D9.b` failed_jobs (placeholder DB offline check) | ComprasService.php sem `Tracer::start` nenhum. Nenhum span custom. |

**Total:** 38/100 — Bucket Crítico.

**Gap real principal:** P0 `D1.b -15pts` (cross-tenant Pest biz=1 vs biz=99). Esse SOZINHO resolve +15pts → 53/100 sem mexer em features. Combinado com `D2.b +8` + `D8.c +3` + `D9.a +3` = +29pts em 1 onda de saneamento, projetando ~67/100. Onda 2 (LGPD+FSM canônica+cliente piloto canary) finaliza 70+.

---

## 2. Inventário (estado real 2026-05-25)

### 2.1 Código backend (PHP)

| Componente | LOC | Status | Comentário |
|---|---:|---|---|
| `Http/Controllers/ComprasController.php` | 141 | ✅ Wave 3 OK | 2 endpoints (`index`, `show`). 4 `Inertia::defer`. business_id explícito. Permission gate `compras.view`. |
| `Http/Controllers/DataController.php` | 127 | ✅ Wave 1 OK | Sidebar entry + 5 permissions catálogo + feature flag `compras_module`. |
| `Http/Controllers/InstallController.php` | 90 | ✅ stub | Padrão UltimatePOS. Wave 1 scaffold sem migration nova. |
| `Services/ComprasService.php` | 283 | 🟡 wrapper fino | `listarCompras` delega `TransactionUtil::getListPurchases`. KPIs+Summary+Detalhe próprios. **Falta eager-load em `listarCompras().paginate()`** (N+1 risk em rows quando >10). |
| `Routes/web.php` | 39 | ✅ OK | Middleware `web,auth,SetSessionData,language,timezone,AdminSidebarMenu,CheckUserLogin` — canônico UPos. **Falta throttle.** |
| `Providers/ComprasServiceProvider.php` | 89 | ✅ OK | Boot translations+views+migrations. Sem event listeners (correto — Wave 6 adiciona bridge DFe). |
| `Providers/RouteServiceProvider.php` | 28 | ✅ OK | Padrão nWidart minimal. |
| `Tests/Feature/ComprasIndexTest.php` | 129 | 🔴 INSUFICIENTE | 4 testes happy-path biz=1 apenas. Zero cross-tenant biz=1 vs biz=99. Zero teste de service. |
| `module.json` | — | ✅ OK | Bucket `process_horizontal` corretamente classificado. |
| `SCOPE.md` | — | ✅ OK | trust L3, contains/not_contains claros. |

### 2.2 Diretórios AUSENTES (relevantes pra Tier 0)

- ❌ `Modules/Compras/Entities/` — **não existe** (correto — usa `App\Transaction` polimórfica)
- ❌ `Modules/Compras/Database/Migrations/` — **não existe** (Wave 6 vai adicionar `transaction_id` em `nfe_dfe_recebidos`)
- ❌ `Modules/Compras/Jobs/` — **não existe** (Wave 6 vai adicionar `ImportarDfeComoCompraJob`)
- ❌ `Modules/Compras/Console/Commands/` — **não existe**
- ❌ `Modules/Compras/Listeners/` — **não existe** (Wave 6 vai adicionar listener `NfeDfeRecebido`)
- ❌ `Modules/Compras/Http/Requests/` — **não existe** (FormRequest pra validação de query string filters faz falta — D8.c=0)

### 2.3 Frontend (Inertia/React)

| Arquivo | LOC | Status | Comentário |
|---|---:|---|---|
| `Pages/Compras/Index.tsx` | 849 | ✅ Wave 1-4 | F1 pin literal do protótipo Cowork. AppShellV2. Defer 4 props. Tabs filtro client-side. |
| `Pages/Compras/Index.charter.md` | 134 | ✅ v2 C1 | Tier A. Goals/Non-Goals/UX/Anti-hooks detalhados. |
| `Pages/Compras/components/Drawer.tsx` | 608 | 🟡 OK + LGPD risk | 5 tabs (Resumo/Itens/Documentos/Pagamentos/Histórico). FSM stepper visual 6 estágios. **Renderiza `compra.contact.tax_number` + `mobile` + `email` sem PiiRedactor.** |
| `Pages/Compras/components/AcoesDropdown.tsx` | 232 | ✅ C1 | 9 ações. C1 convergência (Editar/Status/Notify delegam `router.visit('/purchases/*')`). |
| `Pages/Compras/components/GradeMatrixInput.tsx` | — | 🟡 estrutura | Existe arquivo mas não consumido em Index (Wave 4.5 pendente — vai pra `Pages/Purchase/Create.tsx` C1). |
| `Pages/Compras/components/VisibilidadeColunas.tsx` | — | ✅ helper | useColumnVisibility hook. |

### 2.4 Documentação canônica (D3)

| Doc | Status | Última atualização | Comentário |
|---|---|---|---|
| SPEC.md v0.2 | ✅ atual | 2026-05-25 | 5 US (1 cancelled C1, 1 inverted C1, 3 ativas). |
| BRIEFING.md v0.1 | ✅ | 2026-05-21 | TL;DR + 9 capabilities mapeadas. |
| RUNBOOK-compras-index.md | ✅ | 2026-05-21 | Sintomas + comandos + smoke. |
| CAPTERRA-DESIGN-FICHA.md | ✅ | 2026-05-21 | 35KB — comparativo Bling/Omie/Tiny/Cin7/Procurify. |
| DISCOVERY-LARISSA-COMPRAS.md | ✅ | 2026-05-21 | Script call Wagner. **Sinal confirmado:** Larissa opera grade tam×cor. |
| AUDITORIA-COMPRAS-2026-05-21.md | ✅ canon | 2026-05-21 | 240 linhas com gap analysis + 3 ondas. Esta auditoria sênior **complementa** com gaps Tier 0 que aquela não cobriu. |

---

## 3. ANÁLISE TIER 0 — BLOQUEADOR (PR-0 inadiável)

> **Princípio constitucional violado:** [ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL](../../decisions/0093-multi-tenant-isolation-tier-0.md) — "Toda query DEVE filtrar por `business_id`. Teste de cross-tenant é obrigatório."
>
> **Constatação pesquisa 2026:** "Skipping tenant scoping is the number one cause of data leakage in multi-tenant apps. Every query being automatically tenant-scoped eliminates approximately 80% of accidental data-leak risks." ([Laracopilot 2026](https://laracopilot.com/blog/laravel-multi-tenancy-saas-guide/))

### 3.1 O que VAZA potencialmente HOJE

#### 3.1.1 `ComprasController::index` — `ComprasController.php:43`

```php
$businessId = (int) session('user.business_id');
```

✅ **Aparentemente OK** — pega `business_id` da session. **MAS:**

- ⚠️ Se session for adulterada/spoofed via PHP serialize attack, vaza outro business
- ⚠️ NÃO tem assertion explícito `if ($businessId <= 0) abort(403)` — `(int) null = 0` passa silencioso
- ⚠️ NÃO tem `Auth::user()->business_id === $businessId` cross-check (defense-in-depth recomendado por [DEV 2026](https://dev.to/kamruljpi/laravel-for-saas-how-to-keep-multi-tenant-data-safe-3o7d): "A defense-in-depth security approach includes multiple layers")

**Fix snippet:**

```php
public function index(Request $request)
{
    if (! auth()->user()->can('compras.view')) abort(403);

    $businessId = (int) auth()->user()->business_id; // ✅ direto do auth, não session
    abort_if($businessId <= 0, 403, 'Business inválido');

    // ... resto
}
```

#### 3.1.2 `ComprasService::listarCompras` — `ComprasService.php:48-70`

✅ **Delega pra `TransactionUtil::getListPurchases($businessId)`** que é canônico UPos com `business_id` scope. Mas:

- ⚠️ Filtro `q` em `transactions.ref_no` + `contacts.name` + `contacts.supplier_business_name` (linhas 55-58) — `contacts` é tabela cross-business. **Confirmar via Grep que `getListPurchases` faz JOIN `contacts` com `WHERE contacts.business_id = X`** (TransactionUtil legacy nem sempre faz isso bem).

**Verificação obrigatória PR-0:**

```bash
# Validar JOIN scope no TransactionUtil
grep -n "contacts.business_id\|->where('contacts" app/Utils/TransactionUtil.php
```

Se não tiver scope explícito em `contacts.business_id`, **vaza nome de fornecedor de outro business** quando usuário digita busca `?q=<nome conhecido>`.

#### 3.1.3 `ComprasService::buscarDetalhe` — `ComprasService.php:171`

```php
$compra = Transaction::where('business_id', $businessId)
    ->where('id', $id)
    ->whereIn('type', ['purchase', 'purchase_order', 'purchase_return'])
    ->with(['contact', 'location', 'purchase_lines', ...])
    ->first();
```

✅ **OK** — `business_id` é o primeiro `where`. Eager load `with(['contact', ...])` herda automaticamente das global scopes se Contact/PurchaseLine tiverem (UPos core tem `BusinessIdGlobalScope`).

⚠️ **MAS:** `purchase_lines.product` (linha 178) — `App\Product` UPos tem `BusinessIdGlobalScope`? **Validar.** Se não, listar produto de outro biz.

#### 3.1.4 `ComprasController::show` — `ComprasController.php:102-116`

Endpoint `/compras/{id}/detalhe` — usuário pode chutar `id` numérico de outro business. ✅ Service faz `where('business_id', $businessId)` antes do `find` (linha 171) — **se nada achar, retorna `null` → controller 404**. Defesa OK.

⚠️ **MAS:** rate limit não existe. Atacante pode enumerar IDs 1..1000000 farmando 404s pra mapear espaço. **Adicionar `throttle:60,1`**.

#### 3.1.5 `DataController::modifyAdminMenu` — `DataController.php:84`

```php
$business_id = session('user.business_id');
```

✅ OK — só lê do contexto. Sem fix necessário.

### 3.2 Pest cross-tenant biz=1 vs biz=99 — INEXISTENTE

**`ComprasIndexTest.php`** tem 4 testes — TODOS usam `business_id => 1`. Zero teste prova:

- Usuário biz=1 NÃO vê compras de biz=2 no `/compras`
- Usuário biz=1 NÃO consegue `GET /compras/{id de biz=2}/detalhe`
- Filtro `?q=<termo que existe em biz=2>` NÃO retorna registros cruzados
- KPI `aberto` count NÃO inclui compras de outros businesses

**Padrão canônico oimpresso** já existe em [`Modules/Financeiro/Tests/Feature/MultiTenantIsolationTest.php:57`](../../../Modules/Financeiro/Tests/Feature/MultiTenantIsolationTest.php) — copiar:

```php
public function test_compras_index_isola_cross_tenant(): void
{
    $primary = Business::factory()->create();   // biz A
    $other = Business::factory()->create();     // biz B

    // Cria compra em biz B
    Transaction::factory()->create([
        'business_id' => $other->id,
        'type' => 'purchase',
        'ref_no' => 'CRO-LEAK-001',
    ]);

    // Login como user de biz A
    $userA = User::factory()->create(['business_id' => $primary->id]);
    $this->actingAs($userA)
        ->withSession(['user' => ['business_id' => $primary->id, 'id' => $userA->id]])
        ->getJson('/compras?q=CRO-LEAK-001')
        ->assertStatus(200)
        ->assertJsonMissing(['ref_no' => 'CRO-LEAK-001']);   // NÃO pode vazar
}

public function test_compras_show_404_para_id_de_outro_business(): void
{
    $primary = Business::factory()->create();
    $other = Business::factory()->create();

    $compraB = Transaction::factory()->create([
        'business_id' => $other->id,
        'type' => 'purchase',
    ]);

    $userA = User::factory()->create(['business_id' => $primary->id]);
    $userA->givePermissionTo('compras.view');

    $this->actingAs($userA)
        ->withSession(['user' => ['business_id' => $primary->id, 'id' => $userA->id]])
        ->get("/compras/{$compraB->id}/detalhe")
        ->assertStatus(404);
}
```

### 3.3 Esforço PR-0 Tier 0 (BLOQUEADOR)

| Tarefa | Esforço IA-pair (h) | Esforço calendar (com margem 2x) |
|---|---:|---:|
| 3 testes cross-tenant (index, show, KPIs) | 2-3h | 1 dia |
| 1 teste cross-tenant filtro `?q=` (validar contacts scope) | 1h | 0.5 dia |
| Fix `ComprasController::index` (business_id do auth + abort_if) | 0.5h | 0.5 dia |
| Throttle `throttle:60,1` no grupo `/compras` routes | 0.5h | 0.5 dia |
| Validar `TransactionUtil::getListPurchases` faz JOIN scope `contacts.business_id` (eventual hotfix) | 2-4h | 1-2 dias |
| **TOTAL PR-0** | **6-9h** | **3.5-4.5 dias calendar** |

**Resultado esperado:** D1 sobe 10→25 (+15), D8 sobe 2→5 (+3), D2 sobe 7→13 (+6). **Nota total 38 → ~62/100 só com PR-0.**

---

## 4. Estado-da-arte 2026 (resumo pesquisa)

Pesquisei 7 vetores em 2026 (não duplico a auditoria upstream que cobriu Bling/Tiny/Omie/Cin7/Procurify; aqui foco em **temas que ela NÃO cobriu** — LGPD, multi-tenant defense-in-depth, OTel, AP automation, Pest patterns):

### 4.1 3-way match — virou MANDATÓRIO 2026

> "The 2026 reform propels the 3-way match from the status of 'best practice' to that of 'essential.' The arrival of structured formats (Factur-X, UBL, CII) makes the exploitation of invoice data easier and more reliable than ever." ([WeProc 2026](https://blog.weproc.com/en/electronic-invoicing/supplier-electronic-invoicing-p2p-3way-match/))

Aplicação BR: NF-e SEFAZ + tolerância configurável + workflow exceções. Larissa hoje confere visual — fonte #1 overpayment 2-5%. Auditoria upstream já listou (gap P0 #3, 8-12 dd) — manter na Onda EVOLUIR.

### 4.2 AI invoice capture — barateou drasticamente

> "AI AP automation reduces per-invoice processing costs from $12-30 (manual) to $2-5 (75-85% reduction)... 70-90% touchless processing rates" ([Beancount 2026](https://chatfin.ai/blog/ai-accounts-payable-automation-the-end-of-manual-invoice-processing/))

**Insight:** Brasil tem **DFe SEFAZ pull NSU** (`Modules/NfeBrasil/Services/Manifestacao/DistribuicaoDfeService` já pronto) — substituto NATIVO de OCR/AI capture nesse país. Vantagem: dados estruturados sem inferência, 100% accuracy. **Roadmap diferente do mundo anglosaxão** — nosso gap é só `NfeDfeRecebido → Transaction` bridge (já listado P0).

### 4.3 LGPD 2026 — fiscalização intensificada

> "Em 2026, a principal mudança vem da intensificação das fiscalizações e exigência de uso efetivo de tecnologias e processos automáticos para controle... Governança é a maior dificuldade — muitas implementaram políticas mas não conseguem manter evidências." ([ProAdvanced 2026](https://proadvanced.com.br/lei-geral-de-protecao-de-dados-lgpd-em-2026-os-pilares-da-conformidade-e-a-protecao-de-dados/))

**Implicação Compras oimpresso:**
- Drawer mostra CNPJ/CPF + telefone + email do fornecedor **sem PiiRedactor** → exposição em logs/screenshots
- Sem retention policy declarada (LGPD não exige prazo fixo mas exige **decisão documentada**)
- Sem `LogsActivity` em Service (`spatie/laravel-activitylog` — Financeiro usa)
- Sem `mcp_audit_log` entry pra ações Compras

### 4.4 Multi-tenant Laravel — defense-in-depth 2026

> "A defense-in-depth security approach includes multiple layers: model scopes, middleware validation, authorization policies, and database constraints. Never trust tenant IDs from user input. Always get the current tenant from the authenticated context." ([DEV 2026](https://dev.to/kamruljpi/laravel-for-saas-how-to-keep-multi-tenant-data-safe-3o7d))

**Implicação Compras:** `(int) session('user.business_id')` é 1 camada. Adicionar:
- `auth()->user()->business_id` cross-check (defense layer 2)
- `abort_if($businessId <= 0)` guard
- Pest cross-tenant biz=1 vs biz=99 como **invariante de regressão**

### 4.5 Supplier scorecard — métricas canônicas 2026

> "Core pillars: quality, delivery, cost, service, innovation, sustainability, and risk. Typical examples: on-time in-full (OTIF), quality defects per million (PPM), lead time adherence, cost variance, corrective action cycle time" ([EvaluationsHub 2026](https://evaluationshub.com/supplier-scorecard-best-practices-kpis-weighting-cadence/))

Auditoria upstream lista gap #8 (P2, 4-6 dd) com 4 métricas: on-time%, lead-time avg, defect rate, fill rate. **OK.** Manter como Onda EVOLUIR (não P0).

### 4.6 OpenTelemetry Laravel — auto-instrumentation maduro

> "opentelemetry-auto-laravel package provides automatic instrumentation for Laravel applications, capturing traces for HTTP requests, database queries, cache operations... without writing boilerplate." ([Uptrace 2026](https://uptrace.dev/guides/opentelemetry-laravel))

oimpresso **já tem** `open-telemetry/opentelemetry-auto-laravel` no composer (vi warning na execução `module:grade`). **Falta extension `opentelemetry` carregada no `php.ini`** (warning explícito). Quando ligar:
- ComprasService ganha spans automáticos pra `Transaction::where(...)` queries
- D9.a sobe 0→4 automaticamente
- Adicionar `Tracer::start('compras.calcularKpis')` custom span no Service — D9.a hit perfeito 4/4

### 4.7 Pest cross-tenant — pattern 2026

> "If you only test 'happy path per tenant,' you'll miss failures that happen when the system is under stress or context is missing — good multi-tenant tests are mostly about enforcing invariants." ([DEV 2026](https://dev.to/addwebsolutionpvtltd/building-multi-tenant-saas-with-row-level-security-in-laravel-3kd3))

Template canônico oimpresso já existe em `Modules/Financeiro/Tests/Feature/MultiTenantIsolationTest.php` — replicar.

---

## 5. Gap Analysis vs Benchmark (15 gaps priorizados)

> **Estimates fator 10x IA-pair + margem 2x ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md))**

| # | Gap | Prio | Esforço (h) | Esforço cal (com 2x) | Impacto nota | Onda | Sistema-ref |
|---|---|:---:|---:|---:|---:|---|---|
| **1** | **Pest cross-tenant biz=1 vs biz=99** (index, show, filter `q`, KPIs) | **P0** | 3-4h | 1.5 dia | +15 (D1.b) | ESTABILIZAR | template Financeiro |
| **2** | **Fix `ComprasController::index` `business_id` source + `abort_if`** | **P0** | 0.5h | 0.5 dia | +3 (D8) | ESTABILIZAR | Laracopilot 2026 |
| **3** | **Throttle `60,1` em `/compras` route group** | **P0** | 0.5h | 0.5 dia | +3 (D8.a) | ESTABILIZAR | Laravel docs |
| **4** | **FormRequest `ListarComprasRequest`** (validar filters q/stage/sort/dir/per_page) | **P0** | 1.5h | 1 dia | +3 (D8.c) | ESTABILIZAR | Spatie LaravelData ou FormRequest puro |
| **5** | **Validar `TransactionUtil::getListPurchases` faz JOIN scope `contacts.business_id`** + hotfix se vazar | **P0** | 2-4h | 1.5 dia | (defesa, sem nota direta) | ESTABILIZAR | (grep no legacy) |
| **6** | **Eager-load `with(['contact','location'])` no `listarCompras().paginate()`** (anti-N+1) | **P1** | 1h | 0.5 dia | +3 (D6.c) | ESTABILIZAR | Laravel docs |
| **7** | **Adicionar Compras em `module_clients.yaml`** com level `piloto_reportando_dor` (Larissa DISCOVERY já confirmou sinal) → após canary biz=4 sobe pra `biz_4_rota_livre_prod` | **P0 doc** | 0.3h | 0.5 dia | +8 → +15 (D5) | ESTABILIZAR / EVOLUIR | rubrica ADR 0153 |
| **8** | **PiiRedactor no Drawer** (mascarar `tax_number`/`mobile`/`email` em logs/screenshots) | **P1** | 2-3h | 1-1.5 dia | +4 (D7.a) | CONSOLIDAR | LGPD 2026 ProAdvanced |
| **9** | **Retention policy declarada** (`config/retention.compras.php` ou bucket `Modules/Compras/Config/retention.php`) + `php artisan compras:prune` command | **P2** | 2-3h | 1.5 dia | +3 (D7.c) | CONSOLIDAR | precedente Financeiro retention.php |
| **10** | **LogsActivity no `Transaction` quando type='purchase'`** + entry em `mcp_audit_log` pra mutações | **P2** | 3-4h | 2 dia | +3 (D7.b → D9 cross) | CONSOLIDAR | Spatie Activitylog + LGPD 2026 |
| **11** | **OTel spans custom no `ComprasService`** (`Tracer::start('compras.listarCompras')`, `'compras.calcularKpis'`, `'compras.buscarDetalhe'`) | **P1** | 2-3h | 1.5 dia | +4 (D9.a) | CONSOLIDAR | Uptrace 2026 |
| **12** | **Charter pros 3 components** (Drawer.charter.md, AcoesDropdown.charter.md, GradeMatrixInput.charter.md) | **P2** | 2h | 1 dia | +1 (D3.c) | CONSOLIDAR | precedente Pages/Financeiro |
| **13** | **FSM canônica via `spatie/laravel-model-states`** (6 estágios `rascunho/pedido/transito/recebido/conferido/pago` + migration `compras_purchases.stage`) — ADR 0143 | **P1** | 8-12h | 4-6 dias | +5 (D4.b) | EVOLUIR | gap auditoria #2 + ADR 0143 |
| **14** | **Bridge `NfeDfeRecebido → Transaction(type=purchase)`** (Wave 6 SPEC US-COM-003) | **P0 feature** | 6-10h | 3-5 dias | (feature, +5 D5 quando Larissa usar) | EVOLUIR | auditoria upstream gap #1 |
| **15** | **3-way match** (PO ↔ Recv ↔ NF-e tolerância configurável + UI "Discrepâncias") | **P1 feature** | 10-15h | 5-8 dias | (feature, +3 D5 + diferencial Bucket Bom→Excelente) | EVOLUIR | auditoria upstream gap #3 + WeProc 2026 |

---

## 6. Roadmap 3 ondas (38 → 70+)

### 6.1 🌊 Onda **ESTABILIZAR** (PR-0 + PR-1) — 38 → ~55

> **Foco:** Tier 0 multi-tenant + LGPD básico + Sec mínimo + ativar piloto. **Sem feature nova.**

**PR-0 Tier 0 (mandatório):**
- Gap #1: Pest cross-tenant biz=1 vs biz=99 (4 testes)
- Gap #2: Fix `ComprasController::index` (business_id auth + abort_if)
- Gap #3: Throttle `throttle:60,1` em route group `/compras`
- Gap #4: FormRequest `ListarComprasRequest`
- Gap #5: Validar JOIN scope `contacts.business_id` no `TransactionUtil` (hotfix se vazar)
- Gap #6: Eager-load `with(['contact','location'])` em `listarCompras().paginate()`

**Esforço PR-0:** 8.5-13h IA-pair (~4-7 dias calendar com margem 2x). 1 dev.

**PR-1 Cliente sinal:**
- Gap #7: Adicionar Compras em `config/governance/module_clients.yaml` level `piloto_reportando_dor` (sinal DISCOVERY já confirmado)
- Agendar call follow-up Larissa pra confirmar GradeMatrixInput entrega na `Pages/Purchase/Create.tsx` C1

**Esforço PR-1:** 0.5-1h IA-pair.

**Resultado projetado pós-Onda ESTABILIZAR:**
- D1: 10 → 25 (+15)
- D2: 7 → 13 (+6 pela cobertura Pest cross-tenant)
- D5: 0 → 8 (+8, level `piloto_reportando_dor`)
- D6: 6 → 9 (+3 eager-load)
- D8: 2 → 8 (+6, throttle + FormRequest + abort_if)
- **Nota total: 38 → ~63/100** (Bucket Médio)

### 6.2 🌊 Onda **CONSOLIDAR** — 63 → ~72

> **Foco:** LGPD + Observability + Doc fina. Ainda sem feature nova.

- Gap #8: PiiRedactor no Drawer
- Gap #9: Retention policy `config/retention.compras.php`
- Gap #10: LogsActivity em mutações `type='purchase'`
- Gap #11: OTel spans custom no ComprasService
- Gap #12: Charters para Drawer/AcoesDropdown/GradeMatrixInput

**Esforço CONSOLIDAR:** 11-15h IA-pair (~5-7 dias calendar).

**Resultado projetado pós-CONSOLIDAR:**
- D3: 12 → 13 (+1, 4 charters/5 tsx = 80%)
- D7: 2 → 9 (+7, PiiRedactor + retention + LogsActivity)
- D9: 2 → 6 (+4, OTel custom spans)
- **Nota total: 63 → ~75/100** (Bucket Bom) — **TARGET BATIDO**

### 6.3 🌊 Onda **EVOLUIR** — 75 → 85+ (opcional, condicional)

> **Foco:** features de diferenciação. Só executa SE Larissa reportar dor concreta pós canary OU se métrica detectar drift ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).

- Gap #13: FSM canônica `spatie/laravel-model-states` (ADR 0143)
- Gap #14: Bridge `NfeDfeRecebido → Transaction(type=purchase)` (Wave 6 SPEC)
- Gap #15: 3-way match automático (PO ↔ Recv ↔ NF-e)
- (Auditoria upstream gap #7 KPIs reais já implementado parcialmente)
- (Auditoria upstream gap #8 Supplier scorecard básico — P2, deixar pra última)
- Canary biz=4 ROTA LIVRE (eleva D5 → 15)

**Esforço EVOLUIR:** 24-37h IA-pair (~12-18 dias calendar com margem 2x).

**Resultado projetado pós-EVOLUIR:**
- D4: 11 → 16 (+5, FSM canônica)
- D5: 8 → 15 (+7, canary biz=4)
- **Nota total: 75 → ~85+** (Bucket Bom → Excelente) — **Atinge teto realista** (saturação esperada ~85, conforme auditoria upstream §4)

---

## 7. Risk Register Tier 0 (ordenado por blast radius)

| # | Risco | Categoria | Blast radius | Probabilidade | Mitigação | Onda |
|---|---|---|---|:---:|---|---|
| R1 | **`TransactionUtil::getListPurchases` faz JOIN `contacts` sem scope explícito `contacts.business_id`** → busca `?q=<termo>` vaza nome de fornecedor de outro biz | TIER 0 multi-tenant | TODO o universo de businesses ativos | 🟡 MÉDIO (precisa validar grep) | Gap #5 (PR-0): grep + hotfix se necessário | ESTABILIZAR |
| R2 | **`ComprasController::index` lê `business_id` da session** sem cross-check `auth()->user()->business_id` | TIER 0 multi-tenant | Atacante com session hijack vê business escolhido | 🟢 BAIXO (session security default Laravel boa) | Gap #2 (PR-0): trocar pra `auth()->user()->business_id` + `abort_if` | ESTABILIZAR |
| R3 | **Zero Pest cross-tenant** → regressão silenciosa em qualquer mudança futura | TIER 0 invariante | Todo refactor futuro pode vazar sem detecção | 🔴 ALTO (probabilidade temporal — qualquer onda) | Gap #1 (PR-0): 4 testes cross-tenant como invariantes | ESTABILIZAR |
| R4 | **Drawer expõe CNPJ/CPF + mobile + email fornecedor em DOM** → screenshot/logs/HAR file vaza PII | LGPD | Fornecedor PJ (CNPJ é público mas mobile/email não); fornecedor PF (CPF é privado) | 🟡 MÉDIO | Gap #8: PiiRedactor + role-based visibility | CONSOLIDAR |
| R5 | **Endpoint `/compras/{id}/detalhe` sem rate limit** → atacante enumera IDs 1..N farmando 404s pra mapear espaço | Sec | Enumeração IDs próprios + cross-biz | 🟡 MÉDIO | Gap #3 (PR-0): throttle 60/min | ESTABILIZAR |
| R6 | **Sem FormRequest** → query string `?sort=<sql injection>` pode passar | Sec | Service usa SORT_MAP whitelist (linha 32-40) — defendido. Mas filtros futuros vulneráveis | 🟢 BAIXO | Gap #4 (PR-0): FormRequest valida tudo | ESTABILIZAR |
| R7 | **Sem LogsActivity em mutações Transaction type='purchase'`** → impossível auditar quem mudou compra na fiscalização LGPD | LGPD | Compliance auditing | 🟡 MÉDIO | Gap #10: ActivityLog observer | CONSOLIDAR |
| R8 | **Sem retention policy declarada** → ANPD 2026 exige decisão documentada | LGPD | Penalidade administrativa | 🟢 BAIXO curto prazo | Gap #9: retention.compras.php | CONSOLIDAR |
| R9 | **FSM "6 estágios" só vive no Drawer.tsx const** — não persistida → mismatch UI vs banco | Arquitetura/UX | Confusão Larissa "tela diz Recebido mas banco diz pending" | 🟢 BAIXO (Wave 5 ainda) | Gap #13: FSM canônica spatie/laravel-model-states | EVOLUIR |
| R10 | **Compras não está em prod nem canary** → toda nota fina é teórica | Cliente real | Risco de feature theater | 🔴 ALTO (já presente) | Gap #7 + canary biz=4 pós CONSOLIDAR | ESTABILIZAR + EVOLUIR |

---

## 8. Cliente piloto candidato (sinal qualificado ADR 0105)

> **Princípio:** "Backlog só recebe item se cliente paga + reporta OU métrica detecta drift" ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).

### 8.1 Mapeamento dos clientes oimpresso

| Cliente | biz_id | Setor | Compra insumos via sistema? | Sinal Compras? |
|---|---:|---|---|---|
| **Wagner (biz=1 oimpresso)** | 1 | gráfica | ✅ usa `/purchases` legacy diário | sinal moderado — Wagner mesmo pode validar canary biz=1 antes biz=4 |
| **Larissa @ ROTA LIVRE** | 4 | vestuário | ❓ DISCOVERY confirmou que dá entrada por grade; XML NF-e ainda incerto | **SINAL FORTE CONFIRMADO** pelo Wagner 2026-05-21 (DISCOVERY linha 29) — opera "compra + entrada por grade" |
| Officeimpresso legacy (Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart) | — | gráfica | ❓ não confirmado pela documentação canon | sinal indeterminado — exigiria call discovery individual |
| Martinho (CYCLE-06 Repair) | — | reparo | ❌ ainda não em prod | irrelevante curto prazo |

### 8.2 Decisão recomendada (Onda ESTABILIZAR PR-1)

**PR-1 imediato:** Adicionar Compras em `config/governance/module_clients.yaml`:

```yaml
Compras:
  level: piloto_reportando_dor   # 8 pts
  note: Larissa @ ROTA LIVRE biz=4 vestuário confirmou DISCOVERY 2026-05-21 — opera compra+entrada por grade. Aguarda canary GradeMatrixInput em Purchase/Create.tsx C1.
```

**Pós Onda CONSOLIDAR (~75/100):**

Subir para `biz_1_wagner_active` (10 pts) quando Wagner começar usar `/compras` no biz=1 prod diariamente (substituir `/purchases` legacy parcialmente — listagem + KPIs).

**Pós Onda EVOLUIR (canary biz=4 Larissa 7d sem dor):**

Subir pra `biz_4_rota_livre_prod` (15 pts) → D5 perfeito.

---

## 9. Tasks pré-formatadas pra `tasks-create` MCP

> **Formato:** pronto pra `mcp__Oimpresso_MCP___Wagner__tasks-create`. Parent agent decide quando invocar. NÃO chamei MCP nesta auditoria (constraint).

### 9.1 Onda ESTABILIZAR (P0 mandatório)

```yaml
- title: "Compras PR-0 · Pest cross-tenant biz=1 vs biz=99 (4 testes)"
  module: Compras
  priority: P0
  estimate_h: 3
  description: |
    Adicionar 4 testes em ComprasIndexTest (ou novo MultiTenantIsolationTest):
    1. test_compras_index_isola_cross_tenant
    2. test_compras_show_404_para_id_de_outro_business
    3. test_filtro_q_nao_vaza_termo_de_outro_business
    4. test_kpis_count_nao_inclui_compras_de_outro_business
    Template: Modules/Financeiro/Tests/Feature/MultiTenantIsolationTest.php:57+
    Resolve D1.b 0/15 → 15/15 (+15 pts nota módulo).
    BLOQUEADOR Tier 0 ADR 0093.
  refs:
    - memory/requisitos/Compras/AUDIT-SENIOR-2026-05-25.md#31-o-que-vaza-potencialmente-hoje
    - memory/decisions/0093-multi-tenant-isolation-tier-0.md

- title: "Compras PR-0 · Fix business_id source no Controller + abort_if + throttle"
  module: Compras
  priority: P0
  estimate_h: 1.5
  description: |
    ComprasController::index e show:
    1. Trocar `(int) session('user.business_id')` por `(int) auth()->user()->business_id`
    2. abort_if($businessId <= 0, 403)
    3. Adicionar throttle:60,1 no route group /compras em Routes/web.php
    Defense-in-depth multi-tenant 2026 (DEV.to + Laracopilot).
    Resolve D8.a +3 pts + defesa Tier 0.

- title: "Compras PR-0 · FormRequest ListarComprasRequest"
  module: Compras
  priority: P0
  estimate_h: 1.5
  description: |
    Criar Modules/Compras/Http/Requests/ListarComprasRequest.php validando:
    - q: nullable|string|max:120
    - stage: nullable|in:all,rascunho,pedido,transito,recebido,conferido,pago,cancelada
    - sort: nullable|in:transaction_date,ref_no,contact_name,location_name,status,payment_status,final_total
    - dir: nullable|in:asc,desc
    - per_page: nullable|in:10,25,50,100
    - compra_id: nullable|integer|min:1
    Resolve D8.c 0/3 → 3/3.

- title: "Compras PR-0 · Validar contacts.business_id scope em TransactionUtil::getListPurchases"
  module: Compras
  priority: P0
  estimate_h: 3
  description: |
    Grep `contacts.business_id\|->where('contacts` em app/Utils/TransactionUtil.php
    Se NÃO houver scope explícito em JOIN contacts:
    - Hotfix: adicionar ->where('contacts.business_id', $business_id)
    - Pest cobertura: test_filtro_q_nao_vaza_nome_de_fornecedor_de_outro_business
    Risco TIER 0 R1 do risk register — blast radius MAIOR.

- title: "Compras PR-0 · Eager-load contact+location em paginate"
  module: Compras
  priority: P1
  estimate_h: 1
  description: |
    ComprasService::listarCompras adicionar ->with(['contact','location'])
    antes do paginate. Evita N+1 quando UI renderiza supplier_business_name/location_name.
    Resolve D6.c 0/3 → 3/3.

- title: "Compras PR-1 · Adicionar Compras em module_clients.yaml level piloto_reportando_dor"
  module: Compras
  priority: P0
  estimate_h: 0.3
  description: |
    config/governance/module_clients.yaml:
      Compras:
        level: piloto_reportando_dor
        note: Larissa @ ROTA LIVRE biz=4 confirmou DISCOVERY 2026-05-21 — compra+entrada por grade.
    Resolve D5 0/15 → 8/15 (+8 pts).
```

### 9.2 Onda CONSOLIDAR (P1-P2)

```yaml
- title: "Compras · PiiRedactor no Drawer (tax_number, mobile, email fornecedor)"
  module: Compras
  priority: P1
  estimate_h: 2.5
  description: |
    Drawer.tsx ResumoTab — mascarar dados PII fornecedor baseado em role:
    - admin/financeiro vê completo
    - operacional vê últimos 4 dígitos CNPJ + email mascarado
    Backend: aplicar PiiRedactor em ComprasService::buscarDetalhe quando role limitada.
    Resolve D7.a 0/4 → 4/4.

- title: "Compras · Retention policy config/retention.compras.php + prune command"
  module: Compras
  priority: P2
  estimate_h: 2.5
  description: |
    Modules/Compras/Config/retention.php declarando:
    - compras_canceladas: 365d hard delete
    - timeline activitylog: 2 anos
    - drawer cache: 5min redis
    Comando Modules/Compras/Console/Commands/PruneCommand.php schedule daily 03:00.
    Resolve D7.c 0/3 → 3/3.

- title: "Compras · LogsActivity Transaction type='purchase' + mcp_audit_log"
  module: Compras
  priority: P2
  estimate_h: 3
  description: |
    Observer em Modules/Compras/Observers/TransactionPurchaseObserver.php
    pra dispatchar mcp_audit_log entry em created/updated/deleted quando type='purchase'.
    Pest cobertura mostra timeline retornando 3+ eventos pós CRUD.

- title: "Compras · OTel spans custom no ComprasService"
  module: Compras
  priority: P1
  estimate_h: 2.5
  description: |
    Adicionar Tracer::start em ComprasService::listarCompras, calcularKpis, calcularSummary, buscarDetalhe.
    Span attributes: business_id, filters, count_rows.
    Requer extension opentelemetry carregada em php.ini (Herd config).
    Resolve D9.a 0/4 → 4/4.

- title: "Compras · Charter pros 3 components (Drawer, AcoesDropdown, GradeMatrixInput)"
  module: Compras
  priority: P2
  estimate_h: 2
  description: |
    resources/js/Pages/Compras/components/Drawer.charter.md (Tier B)
    + AcoesDropdown.charter.md + GradeMatrixInput.charter.md
    com Mission/Goals/Non-Goals/Anti-hooks padrão.
    Resolve D3.c 2/3 → 3/3.
```

### 9.3 Onda EVOLUIR (condicional ao sinal Larissa pós canary)

```yaml
- title: "Compras · FSM canônica via spatie/laravel-model-states (ADR 0143)"
  module: Compras
  priority: P1
  estimate_h: 10
  description: |
    Implementar State machine "rascunho → pedido → transito → recebido → conferido → pago"
    + cancelled override. Migration consolidando transactions.status+shipping_status em column nova 'stage'.
    Resolve D4.b 0/5 → 5/5.
    BLOCKER: confirmar Wave 8 SPEC continua válida pós C1 convergência.

- title: "Compras · Bridge NfeDfeRecebido → Transaction(type=purchase) Wave 6"
  module: Compras
  priority: P0_feature
  estimate_h: 8
  description: |
    SPEC US-COM-003 (mantida intacta pós C1).
    Listener Modules/Compras/Listeners/CriarPurchaseAoReceberDfe + Job ImportarDfeComoCompraJob.
    Migration nfe_dfe_recebidos.transaction_id + UNIQUE compound.
    Auto-match supplier por CNPJ. Auto-match produto por EAN+xProd.
    Gap #1 auditoria upstream (P0, 5-8 dd).

- title: "Compras · 3-way match automático (PO/Recv/NF-e) Wave 7+"
  module: Compras
  priority: P1_feature
  estimate_h: 13
  description: |
    Algorithm: comparar PO.qty vs Recv.qty vs NFe.qty com tolerância configurável (%).
    UI: Drawer tab nova "Discrepâncias" listando linhas com diff > tolerância.
    Gap #3 auditoria upstream (P0, 8-12 dd). WeProc 2026 "essential 2026 reform".
```

---

## 10. Surpresa estratégica descoberta na pesquisa

**Constatação:** `php artisan module:grade Compras --detail` emite warning na 1ª linha:

> "PHP Warning: The opentelemetry extension must be loaded in order to autoload the OpenTelemetry Laravel auto-instrumentation in D:\oimpresso.com\vendor\open-telemetry\opentelemetry-auto-laravel\_register.php on line 13"

Isso significa: **oimpresso TEM `opentelemetry-auto-laravel` instalado via composer mas extension não está carregada no `php.ini` do Herd**. Quando carregar, **TODOS os módulos** (não só Compras) ganham auto-instrumentation gratuita pra HTTP requests + DB queries + cache + queues — D9.b (observability) pula automaticamente em todos.

**Recomendação:** **PR órfão fora do escopo Compras** — adicionar `extension=opentelemetry` no `php.ini` Herd dev + documentar `.env.example` requisito. Impacto: D9.b passa de placeholder pra hard check com benefício transversal pra **36 módulos**, não só Compras.

**Esforço:** 0.5h IA-pair. **ROI:** elevação coletiva da média do projeto (potencial +2-3 pts médios).

---

## 11. Pré-requisitos críticos antes de disparar implementadores

| Checklist | Estado atual | Como verificar |
|---|---|---|
| ✅ Auditoria upstream `AUDITORIA-COMPRAS-2026-05-21.md` revisada | ✅ feita 2026-05-21 | lida nesta sessão |
| ✅ SPEC.md v0.2 com C1 convergência | ✅ atual | `Read SPEC.md` |
| ✅ ADR proposta `compras-modulo-greenfield-hibrido` linkada | ✅ existe `decisions/proposals/` | `Glob proposals/` |
| ✅ ADR proposta `compras-purchase-convergencia-c1` linkada | ✅ existe | idem |
| 🟡 **Wagner sign-off PR-0** (mudança em Controller + Routes + nova test infra) | ⚠️ pendente | PR-0 muda business_id source — defense-in-depth mas mudança comportamental |
| 🟢 PHP/composer deps OK | ✅ Herd ativo | testado |
| 🔴 **Banco MySQL local online** (Pest cross-tenant precisa) | 🔴 OFFLINE detectado em `module:grade` | Wagner ligar serviço local antes de implementadores spawnarem |
| 🟢 GitHub Actions `module-grades-gate.yml` ativo | ✅ ([ADR 0155](../../decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md)) | bloqueia regressão |
| 🔴 **PHP extension `opentelemetry` carregada no Herd** | 🔴 NÃO carregada (warning detectado) | edit `php.ini` Herd + restart |

**Pré-flight obrigatório antes de spawnar implementadores:**
1. Wagner sign-off na proposta de defense-in-depth (PR-0 muda `session('user.business_id')` → `auth()->user()->business_id`)
2. Subir MySQL local pra Pest rodar
3. (Opcional mas recomendado) Ligar OTel extension no Herd

---

## 12. Sequência recomendada execução implementadores

### 12.1 Estratégia: **batches sequenciais com paralelismo limitado** (não full-parallel)

Razão: Gap #5 (validar `TransactionUtil` JOIN scope) pode disparar **hotfix que mexe em `app/Utils/TransactionUtil.php:6435 LOC`** — esse arquivo é compartilhado com Sells/Expense/StockAdjustment. Não pode rodar em paralelo com features novas (conflito merge garantido).

### 12.2 Sequência detalhada

**Batch 1 (paralelo seguro, 1 dev cada):**
- Implementador A: Gap #1 (Pest cross-tenant) + Gap #7 (module_clients.yaml)
- Implementador B: Gap #4 (FormRequest)
- Implementador C: Gap #3 (throttle) + Gap #6 (eager-load)

**Batch 2 (sequencial após Batch 1 verde):**
- Implementador D: Gap #5 (validar+hotfix TransactionUtil) — **isolado, mexe em core legacy**
- Implementador E: Gap #2 (Fix business_id source) — depende de Gap #5 validado

**Batch 3 (Onda CONSOLIDAR — paralelo seguro):**
- Implementador F: Gap #8 (PiiRedactor)
- Implementador G: Gap #9 (retention) + Gap #10 (LogsActivity)
- Implementador H: Gap #11 (OTel spans) + Gap #12 (charters)

**Batch 4 (Onda EVOLUIR — sequencial, condicional):**
- Implementador I: Gap #13 (FSM canônica) — depende migration nova
- Implementador J: Gap #14 (Bridge DFe) — depende FSM
- Implementador K: Gap #15 (3-way match) — depende #13+#14

---

## 13. Custo total projetado

| Onda | Esforço IA-pair (h) | Esforço calendar (com margem 2x) | Custo dev (R$/h estimado 120/h IA-pair) | Impacto nota |
|---|---:|---:|---:|---:|
| ESTABILIZAR (PR-0+PR-1) | 9-14h | 4-7 dias | R$ [redacted Tier 0]-1.680 | +25 pts (38→63) |
| CONSOLIDAR | 11-15h | 5-7 dias | R$ [redacted Tier 0]-1.800 | +12 pts (63→75) |
| EVOLUIR | 24-37h | 12-18 dias | R$ [redacted Tier 0]-4.440 | +10 pts (75→85) |
| **TOTAL roadmap completo** | **44-66h** | **21-32 dias** | **R$ [redacted Tier 0]-7.920** | **+47 pts (38→85)** |

**Custo infra adicional:** R$ [redacted Tier 0] (tudo backend Laravel + frontend React, sem novo SaaS).
**Custo LLM ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §4):** marginal — IA-pair Claude Sonnet 4.x ~R$ [redacted Tier 0]-150 acumulado em prompts implementação se usar Opus 4.7 só pra revisão/sênior decisions.

**ROI:** Onda ESTABILIZAR sozinha sai do bucket Crítico → Médio (+25 pts em 4-7 dias R$ [redacted Tier 0]-1.680). **Maior ROI projeto inteiro.** Se Wagner cortar orçamento, esta é a onda mínima inadiável (Tier 0 IRREVOGÁVEL).

---

## 14. Referências externas (7 WebSearch executadas)

1. [WeProc 2026 — Supplier Electronic Invoicing P2P & 3-Way Match](https://blog.weproc.com/en/electronic-invoicing/supplier-electronic-invoicing-p2p-3way-match/)
2. [Cierus 2026 — Bling vs Tiny vs Omie](https://www.cierus.com.br/news-details.php?slug=bling-vs-tiny-vs-omie-qual-erp-escolher)
3. [ProAdvanced 2026 — LGPD pilares conformidade](https://proadvanced.com.br/lei-geral-de-protecao-de-dados-lgpd-em-2026-os-pilares-da-conformidade-e-a-protecao-de-dados/)
4. [Laracopilot 2026 — Laravel Multi-Tenancy SaaS Guide](https://laracopilot.com/blog/laravel-multi-tenancy-saas-guide/)
5. [DEV 2026 — Laravel for SaaS: Keep Multi-Tenant Data Safe](https://dev.to/kamruljpi/laravel-for-saas-how-to-keep-multi-tenant-data-safe-3o7d)
6. [EvaluationsHub 2026 — Supplier Scorecard Best Practices](https://evaluationshub.com/supplier-scorecard-best-practices-kpis-weighting-cadence/)
7. [Uptrace 2026 — OpenTelemetry Integration for Laravel](https://uptrace.dev/guides/opentelemetry-laravel)
8. [ChatFin 2026 — AI Accounts Payable Automation](https://chatfin.ai/blog/ai-accounts-payable-automation-the-end-of-manual-invoice-processing/)
9. [Hafiz.dev 2026 — Laravel Pest 4 Testing Guide](https://hafiz.dev/blog/laravel-pest-4-testing-complete-guide)
10. [AX4B 2026 — LGPD 8 ajustes compliance 2026](https://ax4b.com/lgpd-2026-ajustes-essenciais-compliance-empresarial/)

---

**Fim auditoria sênior.** Próxima ação (parent agent): considerar invocar `tasks-create` MCP pras 11 tasks da Onda ESTABILIZAR (mandatória) + 5 da CONSOLIDAR. Onda EVOLUIR só após sinal canary Larissa.
