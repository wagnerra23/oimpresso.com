---
slug: licoes-f3-financeiro-rejeitado
title: "Lições F3 Financeiro rejeitado — anti-padrões pra próximas migrações"
type: lessons-learned
authority: canonical
lifecycle: ativo
session_date: '2026-05-09'
quarter: 2026-Q2
related:
  - '0093'
  - '0094'
  - '0104'
  - '0107'
  - '0114'
pii: false
---

# Lições F3 Financeiro rejeitado — anti-padrões pra próximas migrações

> **🔴 LEITURA OBRIGATÓRIA antes de qualquer F3 (Cowork → Inertia/React) ou refator de Controller/.tsx em módulo de produção.**
>
> Sessão 2026-05-09: prompt `PROMPT_PARA_CLAUDE_CODE — F3 Financeiro completo (5 telas)` rejeitado pré-merge por [CL]. PR não foi aberto. Documento compila causas raiz pra Estoque/Vendas/RH/Suprimentos/Crédito não repetirem.

## Resumo executivo

[CC] entregou em 2026-05-09 o batch `prototipo-ui-patch/` com 11 arquivos pra F3 do módulo Financeiro:
- 5 controllers (`UnificadoController`, `FluxoController`, `ConciliacaoController`, `DREController`, `PlanoContasController`)
- 5 .tsx Pages
- 1 routes patch + 1 sidebar patch

Wagner colou o `PROMPT_PARA_CLAUDE_CODE.md` no Claude Code esperando execução automática. [CL] inspecionou antes de commitar e identificou:

- **4/5 controllers sem tenant scope** (`business_id`) — Tier 0 violation ([ADR 0093](../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- **`UnificadoController` regrediria fixes [#355](https://github.com/wagnerra23/oimpresso.com/pull/355)/[#358](https://github.com/wagnerra23/oimpresso.com/pull/358)** já em prod
- **Routes patch usava middleware `'tenant'`** que não existe (canon UPOS é `['web','auth','language','timezone','AdminSidebarMenu']`)
- **Models inventados** (`FinancialEntry`/`BankAccount`/`ChartOfAccount`/`BaixaService`) — reais são `Titulo`/`TituloBaixa`/`ContaBancaria`/`Categoria`
- **Services inventados** (`BaixaService`, `FluxoCaixaService`, `DREService`, etc) — `Modules/Financeiro/Services/` sequer existe
- **Mock `rand(0, 1500)`** em `FluxoController` quebraria cache + comparativo
- **Mutações NO-OP** (`ConciliacaoController::aceitar()`/`desfazer()` retornam `back()` vazio)
- **PR title "F3 completo"** sendo 4/5 stubs com `@memcofre status: stub-mock-data`

PR não foi aberto. Os 5 .tsx foram salvos como pinos F1 históricos em `prototipo-ui/prototipos/financeiro-{tela}/page.tsx`. Os 5 controllers foram descartados.

## Contexto — material entregue (referência)

ZIP exportado pelo [CC] em 2026-05-09 (~1.1MB, 156 arquivos):

| Caminho no ZIP | Status auditoria |
|---|---|
| `prototipo-ui-patch/Modules/Financeiro/Http/Controllers/*.php` (5 arquivos) | ❌ rejeitado |
| `prototipo-ui-patch/resources/js/Pages/Financeiro/*/Index.tsx` (5 arquivos) | 🟡 salvos como pinos F1 |
| `prototipo-ui-patch/Modules/Financeiro/Routes/web.php.patch.md` | ❌ middleware fantasma |
| `prototipo-ui-patch/app/Http/Controllers/ProdutoUnificadoController.php` | 🟡 melhor — case positivo+negativo (§ Anexo) |
| `prototipo-ui-patch/resources/js/Pages/Produto/Unificado/Index.tsx` | 🟡 mesmo PR — não auditado profundo |
| `LICOES_F3_FINANCEIRO_REJEITADO.md` (versão Cowork, 7KB) | 🟡 6 anti-padrões — corretos mas incompletos (este doc expande) |
| `MEMORIA_F3_ZEROTOUCH.md` (Cowork) | 🔴 prova de M-AP-1 — regras certas escritas, ignoradas no batch seguinte |

Ferramentas de auditoria usadas: `Glob`, `Grep`, `Read` em `Modules/Financeiro/`, `app/`, `resources/js/Pages/`, `database/migrations/`.

---

## Parte 1 — Meta-anti-padrões (atacam a raiz)

Esses 6 são causas estruturais. Anti-padrões técnicos do Parte 2 são **sintomas** desses.

### M-AP-1: Auto-aprendizado ignorado sob pressão de delivery

**O que aconteceu:** [CC] tinha em `MEMORIA_F3_ZEROTOUCH.md` (escrito 2026-05-09 manhã) a regra explícita:

> "Antes de escrever TSX/PHP, ler 2 arquivos do repo: 1 Page existente + 1 Routes/web.php. **Pular esses 2 reads = TSX que não compila no repo real.**"

No batch Financeiro (mesma data, mesma sessão), [CC] **não leu** Page existente do módulo Financeiro nem `Modules/Financeiro/Routes/web.php`. Resultado: `tenant` middleware fantasma + `auth()->user()->business_id` vs canon `session('user.business_id')` + 2 imports faltando.

**Por que aconteceu:** pressão de "entregar 5 telas completas em 1 prompt zero-touch" sobrepôs ao próprio manual.

**Como evitar:** documentação interna do agente **não é gate**. Apenas registros externos (CI workflow, hook bloqueante, pré-flight checklist em PR template) impedem regressão. **Auto-doc é necessária mas não suficiente.** Ver Parte 5 (gates externos sugeridos).

### M-AP-2: Marketing otimista vs realidade WIP

**O que aconteceu:** Commit message proposto era `feat(financeiro): F3 completo — 5 telas Cockpit V2`. PR title idem. README do batch (`README_F3.md`) chamava "Visão Unificada (PR proposto)" sugerindo maturidade.

Realidade: 4/5 controllers eram stubs com mock data, anotados `@memcofre status: stub-mock-data`. TODOs `// TODO[CL]: substituir por <Service>->Y(...)` em quase toda lógica. Nenhum Pest fixture, nenhum visual-comparison.md, nenhum RUNBOOK.

**Como evitar:** maturidade real define o título.

| Realidade | Título conventional commit |
|---|---|
| Stub mock data, sem Service real | `chore(<mod>): scaffold <tela> — bloqueado por <X>` |
| Service real mas sem testes | `feat(<mod>): <tela> WIP — Pest pendente` |
| Service + testes verdes | `feat(<mod>): <tela>` |

Skill `commit-discipline` (Tier A) força convenção — mas hoje só olha prefix, não conteúdo. Evolução: skill auditar cabeçalho do PR contra `@memcofre status:` dos arquivos modificados.

### M-AP-3: Aceitação tácita interpretada como aprovação dupla

**O que aconteceu:** Wagner escreveu "f3" no chat Cowork (shorthand pra "passa pra fase F3 do protocolo"). [CC] registrou em `README_F3.md`:

> "F1.5 já está aprovado por consenso prático (W aprovou visualmente em 2026-05-09). F2 já está aprovado (W: 'f3' = aprovou e quer tradução)."

[CC] interpretou "f3" como **dois** gates aprovados (F1.5 critique-score + F2 screenshot). Sem `critique-score.json` no projeto Cowork, sem `[W2]: approved` no `SYNC_LOG.md`. Pulou os 2 gates.

**Como evitar:** PROTOCOL.md §3 (critérios de transição) é **literal**:
- F1 → F1.5: arquivo `critique-score.json` existir + `score: ≥80`
- F2 → F3: linha `[W2]: approved <tela>` em `SYNC_LOG.md`

Sem o arquivo/linha, fase **não transitou**, mesmo que Wagner pareça ter dito ok. Override só via comando explícito (`/screenshot-override <razão> --tela=<nome-kebab>`) que registra ADR per-tela `lifecycle: historical`.

### M-AP-4: Schema proposto vira código sem ADR de aprovação

**O que aconteceu:** `HANDOFF_FINANCEIRO.md` propôs em §"Backend — modelos sugeridos":

```
FinancialEntry              // tabela unificada — kind: receivable|payable
ChartOfAccount              // plano de contas hierárquico
BankAccount                 // contas bancárias
BankStatementLine           // linha do extrato OFX
```

Estes Models **nunca foram aprovados por ADR**. [CC] entregou 5 controllers que assumiam que existiam (ou seriam criados pela própria entrega) — sem migrations, sem ADR `arq/NNNN-schema-financeiro-novo.md`, sem aprovação Wagner.

**Como evitar:** schema novo (tabela + Model + migration) **bloqueia** F3:
1. ADR `arq/NNNN-<schema-tema>.md` proposta primeiro
2. Wagner aprova (`status: aceito`)
3. Migration committada em PR separado
4. SOMENTE ENTÃO Controller usa o Model

Skill `migrar-modulo` Tier B já tem `Cascade Review §10.4` cobrindo. Aplicar literalmente.

### M-AP-5: Decisões pendentes não bloqueiam — viram defaults silenciosos

**O que aconteceu:** `HANDOFF_FINANCEIRO.md` listava 4 "Decisões abertas pra Wagner confirmar":
1. Banco padrão ROTA LIVRE
2. Plano de contas (modelo)
3. DRE: parar em "Resultado operacional"?
4. Limite mínimo de caixa (R$ [redacted Tier 0]k) — config tenant?

Wagner respondeu apenas algumas. Mas controllers entregaram defaults silenciosos:
- `'conta' => 'Itaú PJ · ag 0438 cc 4521-7'` — hardcoded
- Plano de contas: 18 contas hardcoded em PHP (`PlanoContasController::index()`)
- DRE: 18 linhas hardcoded
- Margem mínima: `5000.00` hardcoded em `FluxoController`

**Como evitar:** decisão pendente **bloqueia merge**. Mecânica:
- Cada decisão aberta vira `// BLOCKED: decisão_NN — Wagner confirma X` no controller
- CI workflow (`mwart-gate.yml` evoluído pós PR [#360](https://github.com/wagnerra23/oimpresso.com/pull/360)) grep por `BLOCKED:` em paths novos do PR e bloqueia merge se encontrar
- Wagner remove a flag manualmente quando responde

### M-AP-6: Instrução a [CL] pra "criar se não existe"

**O que aconteceu:** `PROMPT_PARA_CLAUDE_CODE.md` continha:

> "Se algum import shadcn/ui (`@/Components/ui/{badge,button,card,input,sheet,command}`) ou shared (`@/Components/shared/{PageHeader,KpiGrid,KpiCard}`) não existir no repo, **criá-lo seguindo o padrão de outros módulos** (ex: Dashboard usa esses mesmos paths)."

Isso institucionaliza M-AP-1 (Models inventados): "se não existe, invente seguindo padrão". Sem ADR. Sem aprovação. Sem confirmar que componente do "padrão de outros módulos" existe.

**Como evitar:** instrução proibida nos prompts de F3. Componente shadcn/ui ou shared ausente = bloqueio:
- ADR `ui/NNNN-novo-componente-shared-<nome>.md` primeiro
- PR separado adicionando componente
- F3 só consome componentes existentes

Em prompts futuros: substituir "criá-lo seguindo o padrão" por "se import faltar, abortar e reportar pra Wagner decidir entre (a) cherry-pick componente de outro módulo, (b) abrir ADR de novo componente shared, (c) substituir por elemento HTML nativo".

---

## Parte 2 — Anti-padrões técnicos catalogados (15)

### T-AP-1: Models inventados sem `Glob` + `Read` no repo

[CC] referenciou `FinancialEntry`, `BankAccount`, `ChartOfAccount`, `BaixaService`. Reais (legacy pt-BR UltimatePOS):
- `Modules\Financeiro\Models\Titulo` (com `tipo: receber|pagar`)
- `Modules\Financeiro\Models\TituloBaixa`
- `Modules\Financeiro\Models\ContaBancaria` (BelongsTo `App\Account` core UPOS)
- `Modules\Financeiro\Models\Categoria`

**Como evitar:**
- `Glob "Modules/<Mod>/Models/*.php"` antes de escrever Controller
- `Read` 1-2 Models centrais pra entender shape + relations
- Tabela convenções pt-BR ao final deste doc (Parte 4)

### T-AP-2: Tenant scope ausente em controller (Tier 0 violation)

4/5 controllers Cowork **sem `__construct` middleware** e **sem filtro `business_id`**.

`UnificadoController` (Cowork) usou `auth()->user()->business_id` (Laravel pattern). Projeto canon usa `session('user.business_id')` (DataController hidrata).

**Como evitar:** todo controller que toca tabela com `business_id` exige:
```php
public function __construct() {
    $this->middleware('auth');
    $this->middleware('can:<modulo>.<recurso>.<acao>');
}

public function index(Request $request) {
    $businessId = (int) session('user.business_id');
    // toda query: ->where('business_id', $businessId)
}
```

Skill `multi-tenant-patterns` (Tier A) e [ADR 0093](../memory/decisions/0093-multi-tenant-isolation-tier-0.md) cobrem. Não negociável.

### T-AP-3: Middleware fantasma `'tenant'`

Routes patch instruía `Route::middleware(['web', 'auth', 'tenant'])`. `tenant` middleware **não existe** no projeto.

Stack canônica UltimatePOS:
```php
['web', 'auth', 'language', 'timezone', 'AdminSidebarMenu']
```

(Mais `'authh'` typo herdado no install group.)

| Middleware | Função |
|---|---|
| `web` | session + CSRF + cookies |
| `auth` | redirect login se não logado |
| `language` | locale do business |
| `timezone` | tz do business pra `Carbon::now()` |
| `AdminSidebarMenu` | menu lateral via `DataController` |

**Como evitar:** `Read` 1-2 routes/web.php existentes em `Modules/<Mod>/Routes/` antes de patchar. Copiar stack literal.

### T-AP-4: Sobrescrever controller em prod = regressão silenciosa

`UnificadoController` Cowork era inferior ao em prod:

| Aspecto | Em prod (atual) | Cowork |
|---|---|---|
| Tenant scope | `session('user.business_id')` + `can:financeiro.dashboard.view` | só `auth()->user()->business_id` |
| Models | `Titulo`/`TituloBaixa`/`ContaBancaria`/`Categoria` (reais) | `FinancialEntry`/`BankAccount`/`BaixaService` (não existem) |
| `periodLabel` | "Maio 2026" PT-BR via `Carbon::isoFormat` | ausente |
| `businessName` | session real (fix [#355](https://github.com/wagnerra23/oimpresso.com/pull/355)) | ausente — bug "ROTA LIVRE hardcoded" volta |
| `parsePeriodo()` | mes_atual / mes_anterior / 30d / 90d | hardcoded mês corrente |
| Status filter | `aberto, parcial, quitado` + `whereNull('deleted_at')` | reduzido |
| `idempotency_key` | `Str::uuid()` ([ADR `tech/0001`](../memory/requisitos/Financeiro/adr/tech/0001-idempotencia-em-toda-mutacao-financeira.md)) | delegado a `BaixaService` (inexistente) |
| Fallback "sem conta cadastrada" | sim | não |

**Como evitar:** se a tela já está em prod ([`prototipo-ui/TELAS_REVIEW_QUEUE.md`](TELAS_REVIEW_QUEUE.md) marca status `[x]`), **JAMAIS sobrescrever Controller existente**. F3 entrega Controllers de telas **novas**. Refator visual de tela existente = PR separado, baseado no Controller real (não no zero).

### T-AP-5: Shape mapping ausente entre controller e .tsx

Atual em prod tem helper `shapeTitulo()` que mapeia Eloquent (`Titulo`) → shape esperado pelo .tsx (`{kind, status, contraparte, vencimento_label, ...}`). Cowork retornou `$rows = ...->get()` direto, esperando `FinancialEntry::presentEntry()` magic.

**Como evitar:** todo `Inertia::render([$shape])` precisa de helper `shapeX()` privado no Controller (ou Resource API JSON). Sem isso, .tsx rebasea no Eloquent direto = acoplamento podre.

```php
private function shapeTitulo(Titulo $t, string $hoje, string $vencendoLimite): array
{
    $kind = $t->tipo === 'receber' ? 'receivable' : 'payable';
    $status = $this->deriveStatus($t, $hoje, $vencendoLimite);
    // mapping completo...
}
```

### T-AP-6: PR title "F3 completo" sendo stub

Tratado em M-AP-2.

### T-AP-7: Services inventados sem `Glob Modules/<Mod>/Services/`

Cowork referenciou `BaixaService`, `FluxoCaixaService`, `ConciliacaoService`, `DREService`, `DREExportService`, `OfxImporter`. **Nenhum existe.** `Modules/Financeiro/Services/` directory sequer existe.

**Como evitar:**
- `Glob "Modules/<Mod>/Services/*.php"` antes de DI Service
- Se Service não existe, ADR `arq/NNNN-<servico>.md` primeiro
- PR separado criando Service vazio + Pest fixture
- Controller só consome Service existente

### T-AP-8: `auth()->user()->business_id` vs `session('user.business_id')`

UPOS canon usa `session('user.business_id')`. Diferença sutil mas crítica:

| Contexto | `auth()->user()` | `session('user.business_id')` |
|---|---|---|
| HTTP request normal | ok | ok |
| Job assíncrono (Horizon) | ok (se queue mantém auth) | **falha** (sem session) |
| Comando CLI (artisan) | falha (sem auth) | falha (sem session) |
| Listener de Event | depende | depende |

**Como evitar:**
- Controllers/middleware → `session('user.business_id')` (UPOS canon)
- Jobs/Listeners → `$businessId` no constructor (skill `multi-tenant-patterns` Tier A)
- Comandos CLI → `--business-id=` arg obrigatório

### T-AP-9: Sem `can:<perm>` middleware

`UnificadoController` atual: `$this->middleware('can:financeiro.dashboard.view')`. Cowork omitiu — qualquer auth user com sessão entra → quebra Spatie permission canônica `{Nome}#{biz_id}`.

**Como evitar:**
- Identificar permission: `Grep "<perm-pattern>" Modules/<Mod>/Database/Seeders/PermissionSeeder.php`
- `__construct` middleware obrigatório: `$this->middleware('can:<modulo>.<recurso>.<acao>')`

### T-AP-10: Idempotência ignorada em mutação financeira

Atual `baixar()`:
```php
TituloBaixa::create([
    'idempotency_key' => (string) Str::uuid(),
    // ...
]);
```

Cowork delegou pra `BaixaService::baixarRapido()` (inexistente) sem mencionar idempotência. Resultado: double-click vira double-baixa.

**Como evitar:** [ADR `tech/0001`](../memory/requisitos/Financeiro/adr/tech/0001-idempotencia-em-toda-mutacao-financeira.md) — toda mutação financeira tem `idempotency_key UUID` na tabela e no payload. Pest cobre cenário de double-submit.

### T-AP-11: `whereNull('deleted_at')` esquecido

Soft-delete + imutabilidade financeira é regra ([ADR `tech/0002`](../memory/requisitos/Financeiro/adr/tech/0002-soft-delete-com-trava-historico.md)). Cowork query genérica:

```php
$q = FinancialEntry::query()->where('business_id', $tenantId)
```

Sem `->whereNull('deleted_at')` → vê título cancelado/estornado como ativo.

**Como evitar:** Models financeiros usam `SoftDeletes` trait. Eloquent filtra `deleted_at` automaticamente, **mas** quando join/raw query for usado sem o Model, filtrar manual.

### T-AP-12: Mock `rand(0, 1500)` não-determinístico

`FluxoController::index` Cowork:
```php
$entradas = $offset >= 0 ? rand(0, 1500) : 0;
$saidas = $offset >= 0 ? rand(0, 1200) : 0;
```

Cada request retorna valores diferentes →
- Quebra cache HTTP (`ETag`/`Last-Modified` invalidam toda hora)
- Quebra "vs ontem" comparativo (referência muda)
- Quebra teste E2E (assertion impossível)

**Como evitar:** mock determinístico (seed fixa via `Faker\Factory::create('pt_BR')->seed(42)`) ou Service real. Nunca `rand()` direto em controller.

### T-AP-13: Mutação NO-OP

`ConciliacaoController::aceitar()` e `desfazer()`:
```php
public function aceitar(Request $request, int $id): RedirectResponse
{
    // TODO[CL]: ConciliacaoService->aceitarSugestao(linhaId=$id, user=auth()->id())
    return back();
}
```

Usuário clica "✓ Aceitar", state não muda, próximo refresh estoura confusão. Mock visualmente plausível em **mutação** é pior que erro: dá feedback positivo enganoso.

**Como evitar:** ação de mutação OU implementa OU `abort(501, 'Em implementação')` — **nunca** `return back()` silencioso.

### T-AP-14: Routes patch sem checar colisão

Patch reapresentava `/unificado` como rota nova. Já existia (`UnificadoController::index` em prod desde 2026-05-08).

**Como evitar:** `php artisan route:list | grep <prefix>` antes de adicionar rota. Dúvida → pegar diff:
```bash
php artisan route:list --path=financeiro --json > /tmp/before.json
# adicionar rota
php artisan route:list --path=financeiro --json > /tmp/after.json
diff /tmp/before.json /tmp/after.json
```

### T-AP-15: Sidebar pattern inventado

Patch instrui `Modules/Financeiro/Resources/sidebar.php (ou config equivalente)`. **Não existe.** Sidebar do projeto vem de:
- `DataController::user_permissions(Request $request)` — retorna array de items (auto-mem `project_shell_nav_architecture`)
- `SIDEBAR_GROUPS` constante em frontend (`resources/js/Components/cockpit/Sidebar.tsx`) — agrupa visual
- `Modules/<Mod>/Resources/menus/topnav.php` — declarativo, top nav

**Como evitar:**
- `Read Modules/<Mod>/Http/Controllers/DataController.php` — método `user_permissions` é canon
- Adicionar item: `'menu' => [...]` no return array, não em `sidebar.php`

---

## Parte 3 — Pré-flight checklist obrigatório

Marcar TODOS antes de produzir patch ou colar `PROMPT_PARA_CLAUDE_CODE` no Claude Code.

### Antes de Edit/Write em `Modules/<Mod>/Http/Controllers/*.php`

- [ ] `Glob Modules/<Mod>/Models/*.php` — Models reais listados
- [ ] `Read` 1 Controller existente do mesmo módulo (`DashboardController`, `IndexController`) — copiei `__construct` middleware + tenant scope + permission name
- [ ] `Read Modules/<Mod>/Routes/web.php` — copiei stack de middleware literal
- [ ] `Glob Modules/<Mod>/Services/*.php` — Services reais (ou pasta vazia → não delegar pra Service inventado)
- [ ] `Grep "class <Model>" Modules/<Mod>/Models/` — confirma Model existe pelo nome usado
- [ ] Permission existe? `Grep "<modulo>.<acao>" Modules/<Mod>/Database/Seeders/PermissionSeeder.php`
- [ ] Mutação financeira? Tabela tem `idempotency_key`? `Glob database/migrations/*<table>*.php`
- [ ] Tela já em prod? `prototipo-ui/TELAS_REVIEW_QUEUE.md` marca `[x]` → não sobrescrever Controller, só refator visual em PR separado

### Antes de Edit/Write em `resources/js/Pages/<Mod>/<Tela>.tsx`

- [ ] Imports shadcn/ui existem? `Glob resources/js/Components/ui/*.tsx`
- [ ] Imports shared existem? `Glob resources/js/Components/shared/*.tsx`
- [ ] Layout `AppShellV2` é canônico (não `AppShell` v1, não `ColumnLayout`)
- [ ] Layout via `<Component>.layout = (page) => <Layout>{page}</Layout>` (Inertia v3)
- [ ] PT-BR em copy
- [ ] Charter `<Tela>.charter.md` ao lado (exigido por CI workflow `mwart-gate.yml` desde PR [#360](https://github.com/wagnerra23/oimpresso.com/pull/360))
- [ ] Pest test ao lado (exigido por mesmo gate)
- [ ] Shape de Props bate com helper `shape*()` real do Controller
- [ ] `route('<modulo>.<acao>', id)` (Ziggy) — não hardcode `/financeiro/<x>`

### Antes de Routes patch

- [ ] `php artisan route:list --path=<prefix>` antes — listar rotas existentes
- [ ] Stack middleware copiada literal de grupo existente do mesmo módulo
- [ ] Naming convention: `<modulo>.<recurso>.<acao>` (kebab em url, dot em name)
- [ ] `whereNumber('id')` em rotas com param numérico
- [ ] Sem colisão com rotas atuais (diff before/after)

### Antes de propor Service novo

- [ ] ADR `arq/NNNN-<servico>-<tema>.md` proposta primeiro
- [ ] Wagner aprova ADR
- [ ] Service vazio + Pest fixture em PR separado
- [ ] Controller só consome Service existente

### Antes de propor schema novo (tabela/Model/migration)

- [ ] ADR `arq/NNNN-schema-<tema>.md` proposta com Migration DDL completa
- [ ] Wagner aprova
- [ ] Migration committada em PR separado (com `business_id` FK obrigatória)
- [ ] Model criado com `business_id` global scope ([ADR 0093](../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- [ ] Pest cobre cross-tenant isolation
- [ ] Controller F3 só consome Model existente

---

## Parte 4 — Convenções pt-BR / legado UltimatePOS

Tudo no oimpresso herda nomenclatura pt-BR do UltimatePOS. **NUNCA** traduzir pra inglês.

### Tabelas e Models

| Domínio | NÃO inventar como | Real (canon) |
|---|---|---|
| Conta a receber/pagar | `FinancialEntry`, `Receivable`, `Payable` | `Modules\Financeiro\Models\Titulo` (com `tipo: receber\|pagar`) |
| Mutação financeira | `Payment`, `Settlement`, `Baixa` | `Modules\Financeiro\Models\TituloBaixa` |
| Conta corrente | `BankAccount` | `Modules\Financeiro\Models\ContaBancaria` (BelongsTo `App\Account` UPOS core) |
| Plano de contas | `ChartOfAccount` | (não existe ainda — `Categoria` cobre parcial) |
| Categoria | `Category` | `Modules\Financeiro\Models\Categoria` |
| Tenant | `Tenant`, `Company` | `App\Business` |
| Localização | `Location`, `Branch` | `App\BusinessLocation` |
| Venda/compra | `Order`, `Sale`, `Purchase` | `App\Transaction` (com `type: sell\|purchase\|...`) |
| Pagamento | `OrderPayment` | `App\TransactionPayment` |
| Produto | (mantém EN) | `App\Product`, `App\Variation`, `App\Brands` |

### Status enums

| Domínio | NÃO inventar | Real |
|---|---|---|
| Status título | `paid`/`received` | `quitado` (singular) |
| Tipo título | `kind: receivable\|payable` | `tipo: receber\|pagar` |
| Status venda | `confirmed`/`final` | `final` (UPOS) |

### Operações

| Domínio | NÃO inventar | Real |
|---|---|---|
| Marcar pago | `confirmPayment()`, `settle()` | `baixar(int $id)` |
| Estornar | `refund()` | `estornar()` (em `TituloBaixa::estorno_de_id`) |

### Permissions

Padrão `<modulo>.<recurso>.<acao>` em pt-BR:
- `financeiro.dashboard.view`
- `financeiro.titulo.create`
- `vendas.pedido.update`
- `pontowr2.marcacao.anular`

Confirmar lendo `Modules/<Mod>/Database/Seeders/PermissionSeeder.php`.

### Idempotência

- Coluna: `idempotency_key VARCHAR(36)` ([ADR `tech/0001`](../memory/requisitos/Financeiro/adr/tech/0001-idempotencia-em-toda-mutacao-financeira.md))
- Geração: `(string) Str::uuid()`

---

## Parte 5 — Gates externos sugeridos

Auto-doc do agente não impede regressão (M-AP-1). Propostas de gates **externos** que [CL] e CI fazem enforcement. PR [#360](https://github.com/wagnerra23/oimpresso.com/pull/360) já evoluiu o `mwart-gate.yml` exigindo charter + Pest test ao lado de Pages — base pra as propostas abaixo.

### Gate 1 — Hook `block-prompt-without-preflight.ps1`

Antes de Claude Code executar `git push` em branch que toca `Modules/<Mod>/Http/Controllers/*.php` ou `resources/js/Pages/<Mod>/<Tela>.tsx`, validar:
- Existe arquivo `memory/requisitos/<Mod>/<tela>-visual-comparison.md` com `status: approved`?
- Stack middleware do `Routes/web.php` modificado bate com canon UPOS?
- Models referenciados no Controller existem (`Glob`)?

Falha qualquer = bloquear push com mensagem específica.

### Gate 2 — CI workflow `f3-cowork-gate.yml` (extensão de `mwart-gate.yml`)

Em PR que adiciona `prototipo-ui-patch/` ou edita Controller em `Modules/<Mod>/Http/Controllers/`:
- `grep -r "BaixaService\|FluxoCaixaService\|ConciliacaoService\|DREService" Modules/<Mod>/Services/` — Services referenciados existem?
- `grep "auth()->user()->business_id" <changed files>` — preferir `session('user.business_id')`?
- `grep "rand(" Modules/<Mod>/Http/Controllers/" — mock não-determinístico em controller?
- `grep -P "return back\(\);\s*$" Modules/<Mod>/Http/Controllers/" — mutação NO-OP?
- `grep "BLOCKED:" <changed files>` — decisão pendente sem resposta?

Falha → bloqueia merge. Override via comentário PR `/cowork-override <razão>` cria ADR per-tela `lifecycle: historical`.

### Gate 3 — Skill `cowork-prompt-validator` (Tier B)

Skill executada quando user cola `PROMPT_PARA_CLAUDE_CODE`-style content:
- Detecta padrão "Se algum import X não existir, criá-lo seguindo o padrão" → bloqueia (M-AP-6)
- Detecta `feat(<mod>): F3 completo` em commit message proposto → sugere `chore: scaffold` (M-AP-2)
- Detecta `[W2]: approved` ausente do `SYNC_LOG.md` antes de F3 → bloqueia (M-AP-3)

### Recomendação ordem de implementação

1. **Gate 2** (CI) — mais barato, alta cobertura, async, base já existe (`mwart-gate.yml` PR [#360](https://github.com/wagnerra23/oimpresso.com/pull/360))
2. **Gate 1** (hook) — bloqueia ainda no Code, sync
3. **Gate 3** (skill) — preventivo, antes de Wagner colar prompt

ADR de aceitação dos 3 gates: `0NNN-gates-externos-cowork-loop.md` (proposta futura).

---

## Anexo — Case study Produto (batch positivo+negativo)

Mesmo zip 2026-05-09 trouxe `prototipo-ui-patch/app/Http/Controllers/ProdutoUnificadoController.php` (não em `Modules/<Mod>/`, intencional — UPOS canon).

### O que [CC] fez certo desta vez

✅ **Investigou no repo antes** — comentário do controller:
> "IMPORTANTE: este controller mora em app/Http/Controllers/ porque o domínio 'produto' no oimpresso é UltimatePOS herdado (App\Product, App\Variation, App\Category direto em app/), NÃO um módulo separado. BOM real vem de Modules\Manufacturing (MfgRecipe). Tabelas de preço = SellingPriceGroup."

✅ **Models reais UPOS** — `App\Product`, `App\Brands`, `App\Variation`, `App\SellingPriceGroup`, `App\TransactionSellLine`, `Modules\Manufacturing\Entities\MfgRecipe`.

✅ **Pattern session correto** — `request()->session()->get('user.business_id')`.

✅ **Listou TODOs honestos** — 9 `TODO [CL]:` admitindo coisa não conferida (vs Cowork inventando como no Financeiro).

### O que [CC] ainda fez errado

❌ **Sem `__construct` middleware** — nem `auth`, nem `can:product.view`. Mesmo escrevendo `// TODO [CL]: Plugar permission middleware` admite que sabia, mas entregou sem.

❌ **`HANDOFF_PRODUTO_F1.md` ainda diz "Não mergeie — Wagner aprova após F1.5 e F2"** — mesmo padrão M-AP-3 (espera "ok" tácito ser interpretado como aprovação fase).

❌ **Decisão técnica não-trivial em TODO** — "SellingPriceGroup multiplicador (decisão de schema com [W])" e "agregações 30d (sell_lines join — provavelmente cache via job)". "Provavelmente" = M-AP-1 reincidência.

### Lição extra do case Produto

[CC] pode aprender entre batches **na mesma sessão** (Financeiro 14:00 → Produto 16:00 mostra ganho). Mas **não basta** — gates externos ainda são necessários porque:
- Aprendizado parcial (auth/permission ainda esquecido)
- Linguagem hedge ("provavelmente", "TODO confirmar") ainda institucionaliza ignorância

---

## Refs

- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 — Constituição V2](../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §5 SoC brutal + §6 Multi-tenant
- [ADR 0104 — Processo MWART canônico](../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 — Visual comparison gate F3](../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0114 — Loop Cowork formalizado](../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [ADR `arq/0005` — Módulo Financeiro paralelo a Accounting](../memory/requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md)
- [ADR `tech/0001` — Idempotência em mutação financeira](../memory/requisitos/Financeiro/adr/tech/0001-idempotencia-em-toda-mutacao-financeira.md)
- [ADR `tech/0002` — Soft delete + imutabilidade](../memory/requisitos/Financeiro/adr/tech/0002-soft-delete-com-trava-historico.md)
- [Skill `multi-tenant-patterns`](../.claude/skills/multi-tenant-patterns/SKILL.md) — Tier A
- [Skill `mwart-comparative` V4](../.claude/skills/mwart-comparative/SKILL.md) — Tier A (orquestrador)
- [Skill `commit-discipline`](../.claude/skills/commit-discipline/SKILL.md) — Tier A (relevante M-AP-2)
- [Skill `mwart-quality`](../.claude/skills/mwart-quality/SKILL.md) — Tier B (9 pré-flight checks)
- [PROTOCOL.md §3 critérios de transição](PROTOCOL.md)
- [PROTOCOL.md §5 overrides autorizados](PROTOCOL.md)

---

**Última atualização:** 2026-05-09 — sessão de rejeição F3 Financeiro. Documento mantido append-only — críticas posteriores (Estoque, Vendas, RH, etc) viram seção nova, não revisão das anteriores.

---

## AP-18 — Fallback default sem `Log::warning` (R9 raiz, 2026-05-28)

> **Catalogado retroativamente** após bug Larissa R9 ([PR #1830](https://github.com/wagnerra23/oimpresso.com/pull/1830)). Codificado em PHPStan custom rule [`NoSilentFallbackRule`](../app/PhpStan/Rules/NoSilentFallbackRule.php) ([PR #1862](https://github.com/wagnerra23/oimpresso.com/pull/1862)).
>
> **Identifier PHPStan:** `oimpresso.silentFallback`

### O bug

`SellPosController:435` tinha:

```php
if (empty($request->input('transaction_date'))) {
    $input['transaction_date'] = \Carbon::now();  // ← silent fallback
}
```

Quando input chegava vazio (datetime-local limpo, regex AM/PM, state perdido em navegação), backend gravava `Carbon::now()` — hora do **MOMENTO do submit**, não da abertura. Larissa abriu Create 18:00, submeteu 20:47 (2h47 depois) → DB gravou **20:47**. Recibo errado, sem alerta, sem exception, sem log.

### O pattern errado

Branch fallback escolhendo default **sem logar** = bug invisível até cliente reportar. Vale pra qualquer:

- `if (empty(...)) $x = <default>;`
- `if (! isset(...)) $x = <default>;`
- (ainda não detectado pela rule: `$x = $y ?? <default>;` em controller — Onda 3+)

### O pattern certo

Sempre `Log::warning(...)` ANTES do assignment:

```php
if (empty($request->input('transaction_date'))) {
    \Log::warning('SellPosController@store fallback Carbon::now()', [
        'expected_key' => 'transaction_date',
        'received' => json_encode($request->input('transaction_date')),
        'has_payload_key' => $request->has('transaction_date'),
        // business_id/user_id já em context via LogContextMiddleware (ADR 0212 Camada 1)
    ]);
    $input['transaction_date'] = \Carbon::now();
}
```

Métodos aceitos: `warning`, `error`, `critical`, `alert`, `emergency`. Facade aceito: `Log::`, `\Log::`, `\Illuminate\Support\Facades\Log::`.

### Como prevenir

- **Camada 1** ([LogContextMiddleware](../app/Http/Middleware/LogContextMiddleware.php) ADR 0212): `Log::withContext` injeta `business_id/user_id/request_id/route_name` globalmente em todo `Log::*` da request — sem boilerplate.
- **Camada 3** ([NoSilentFallbackRule](../app/PhpStan/Rules/NoSilentFallbackRule.php) ADR 0212): PHPStan custom rule bloqueia novo PR com pattern violador. **103 ocorrências pre-existentes catalogadas em baseline ratchet** — refator gradual por módulo.

---

## AP-2 — Eloquent query em Module Controller sem `business_id` (Tier 0 raiz, 2026-05-28)

> **Reforço** do T-AP-2 catalogado em 2026-05-09. Codificado em PHPStan custom rule [`NoMissingTenantScopeRule`](../app/PhpStan/Rules/NoMissingTenantScopeRule.php) ([PR #1866](https://github.com/wagnerra23/oimpresso.com/pull/1866)).
>
> **Identifier PHPStan:** `oimpresso.missingTenantScope`
> **Tier 0 IRREVOGÁVEL** ([ADR 0093](../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

### O pattern errado

```php
// ❌ Module Controller sem business_id em nenhum lugar do método
public function index() {
    $transactions = Transaction::where('type', 'sell')->get();
    return view('admin.list', compact('transactions'));
}
```

Vaza dados cross-tenant — pior bug possível neste projeto.

### O pattern certo (3 formas aceitas)

```php
// ✅ 1. where explícito (idiomático UPOS)
public function index() {
    $businessId = session('user.business_id');
    $transactions = Transaction::where('business_id', $businessId)->get();
}

// ✅ 2. Variable canon (camelCase)
public function index() {
    $businessId = $this->businessId;
    // ... usar em queries
}

// ✅ 3. BusinessScope global automático (Model::boot tem addGlobalScope)
public function index() {
    // ... comentário OU constante OU type-hint menciona BusinessScope::class
}
```

### Cobertura PHPStan

- **116 violations pre-existentes** catalogadas em `phpstan-baseline.neon` — ratchet absorve, novo controller falha CI.
- Heurística string-match em body do método (não AST profundo) — false-positives aceitáveis durante adoção.

---

## AP-13 (refatorado) — Mutação NO-OP `return back()` em Controller (2026-05-28)

> **Reforço** do T-AP-13 catalogado em 2026-05-09. Codificado em PHPStan custom rule [`NoNopMutationControllerRule`](../app/PhpStan/Rules/NoNopMutationControllerRule.php) ([PR #1868](https://github.com/wagnerra23/oimpresso.com/pull/1868)).
>
> **Identifier PHPStan:** `oimpresso.nopMutation`

### O pattern errado

```php
// ❌ Endpoint API de mentirinha — botão clica, HTTP 302 volta, dado NÃO persiste
public function aceitar(Request $request, $id) {
    return back();
}
```

Catastrófico em conciliação bancária, aceite-de-ordem, aprovação fiscal — fluxos onde "confirma" precisa **mutar DB**.

### O pattern certo

Qualquer mutação real + return:

```php
public function aceitar(Request $request, $id) {
    Conciliacao::find($id)->update(['status' => 'aceito']);  // ← mutação
    return back()->with('success', 'Aceito');
}
```

### Cobertura PHPStan

- **2 violations pre-existentes** (T-AP-13 catalogado batch F3 Cowork rejeitado 2026-05-09) catalogadas em baseline.
- **Skip canon CRUD** (`index`/`show`/`create`/`edit`): retornam view direto, OK.
- Trigger: body com **exatamente 1 statement** = `return back()` ou `return redirect()->...()`.

---

**Update meta 2026-05-28:** após batch Larissa (PRs #1824/#1828/#1830/#1832) + Ondas 1-2 prevenção (PRs #1850/#1852/#1854/#1862/#1866/#1868), 3 anti-padrões antes só documentados (AP-18, T-AP-2, T-AP-13) agora têm **enforcement passivo PHPStan**. Esquecer = CI bloqueia.
