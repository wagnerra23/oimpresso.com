# 2026-07-28 15:20 — a investigação do RecurringBilling refutou a premissa, e a máquina de avisos não tem linha pra nada disso

> Dois workflows (45 + 31 agentes) + 2 PRs: [#4962](https://github.com/wagnerra23/oimpresso.com/pull/4962) MERGED · [#4964](https://github.com/wagnerra23/oimpresso.com/pull/4964).
> Delta do [handoff das 14:40](2026-07-28-1440-regra-do-dono-vira-teste-e-o-momento-de-ler-o-charter.md).

## ⚠️ LEIA PRIMEIRO — a premissa que eu propaguei estava ERRADA

Eu reportei a [W], duas vezes, que *"assinatura sem plano fica com `plan_id = null` e nunca
é faturada"*. **Não existe.** Medido em prod:

- `rb_subscriptions.plan_id` é **`NOT NULL` com FK** (`information_schema`, `IS_NULLABLE=NO`)
- `INSERT` com NULL reproduzido no CT 100 sob o `sql_mode` de prod → **`ERROR 1048`**
- `grep "subscription sem plan" laravel.log` → **0 ocorrências em 38 dias**
- **162 assinaturas, 109 ativas, 0 órfãs** (`LEFT JOIN rb_plans ... WHERE p.id IS NULL`)

Essa assinatura **não nasce órfã — ela não nasce**. Repassei o achado do chip sem verificar.
É a mesma classe do `toggleAutoEmission` (mesmo dia): **afirmar sobre comportamento sem ler
a fonte que responde**.

## Os dois defeitos REAIS (esses sobreviveram ao cético)

### 1. Valor negociado não chega na fatura
**4 de 5** resolvedores de valor usam `metadata['valor'] ?? plan?->valor` — é o que a **tela
mostra**, o que o **MRR soma**, o que a **edição lê** (`SubscriptionIndexPresenter:160`,
`SubscriptionRepository:77`, `AssinaturaCobrancaService:218`, `Financeiro/AssinaturaController:56`).
O 5º, `InvoiceGeneratorService:124`, usa **só** `plan->valor`.

Materializado em 1 registro: a sub 180 teve fatura emitida ~3 ordens de grandeza acima do
digitado. **Ninguém foi cobrado** (fatura `open`, 0 tentativas) — mas ela segue `active`,
logo **repete**. Valores omitidos: [redacted Tier 0].

⚠️ Um teste **verde** (`tests/Feature/Calculo/CalculoRecurringBillingTest.php`) canoniza
"plano manda" no docblock — mas é caracterização auto-declarada (trava o comportamento
ATUAL), e o helper nunca escreve `metadata`, então o caso divergente **jamais é exercitado**.

### 2. Tem data dura: **janeiro de 2027**
`InvoiceGeneratorService:138-142` — quando já existe fatura na competência, incrementa
`skipped` e **retorna ANTES de avançar `next_due_date`** (o update está na linha 173). Um
backfill pré-criou faturas até **2026-12-27**.

Portanto: **nada falta hoje**; a partir de jan/2027, **108 das 109 assinaturas ativas param
de gerar fatura** — com exit code 0 e sem alerta. Dry-run confirma:
`--date=2027-01-15 --dry-run` → `geradas=1 · puladas=108`.

## A pergunta do [W] que vale mais que os achados

> *"era para avisar aqui? qual máquina cuida dos avisos?"*

**Sim, era para avisar — no Daily Brief** ([ADR 0091](../decisions/0091-daily-brief.md)).
É a máquina de avisos do projeto: roda por `brief:generate` no schedule
(`app/Console/Kernel.php:985`), e é o que [W] lê no início de cada sessão.

**Ela é plugável**: cada flag é um `*BriefLineService`. Existem **10** hoje —
`TasksSemDonoBriefLineService` (as 680 US sem dono), `ExposicaoTier0BriefLineService`,
`ObraParadaBriefLineService`, `PlanHealthBriefLineService`, `SddBriefLineService`,
`ShippedLogBriefLineService`, `AdrPendenteBriefLineService`, `AdrReviewBriefLineService`…

**Nenhum dos 3 achados tem linha de brief.** Por isso chegaram ao [W] por mim, no chat —
canal que some quando a sessão fecha. O padrão de extensão existe e é claro; o que falta é
plugar. Candidatos naturais, se [W] quiser:
- `next_due_date` congelado há N dias em assinatura ativa (pega o jan/2027 **antes** de virar)
- módulo com nightly não-verde (pega OficinaAuto/PaymentGateway)
- comando agendado que não existe em runtime

## O que foi corrigido

### [#4962](https://github.com/wagnerra23/oimpresso.com/pull/4962) — a fixture mentia
`PlanoSemFaturaContratoTest` ficava **3 FAILED com 0 assertions úteis**, todas em
`no such column: rb_subscriptions.deleted_at`. Quem lesse a cor confirmaria a predição do
`casos.md` **sem que um caso tivesse rodado**.

Faltavam 4 coisas, cada uma achada **lendo o log que o `try/catch` do gerador engole**
(`InvoiceGeneratorService:89-95` converte QUALQUER exceção em `errors++`, então falha de
schema se disfarça de comportamento):
1. `softDeletes()` em `rb_subscriptions` e `rb_invoices`
2. os 3 contadores v9.75 em **`rb_subscriptions`** (não em `rb_invoices`, como supus)
3. `activity_log` **condicional** (o `tests/Pest.php` já provisiona — criar derruba tudo)
4. `rb_subscription_events` com as colunas **reais** `kind`/`by_actor`/`body`/`occurred_at`
   (eu tinha inventado `tipo`)

**Veredito agora** (CT 100, sqlite in-memory): `1 failed, 2 passed (9 assertions)` — com o
**controle positivo verde** provando que o motor fatura, e o vermelho isolado no caso do
valor negociado. Duas hipóteses minhas caíram no caminho: o global scope (é **no-op** sem
`auth()->check()`) e o `activity_log` (já existia).

⚠️ **NÃO** adicionei à `.github/ci-sqlite-pest.list`: ela alimenta `PHP / Pest (Unit)`, que é
**required com `enforce_admins`** — teste vermelho ali trava o merge de todos.

### [#4964](https://github.com/wagnerra23/oimpresso.com/pull/4964) — o detector de órfãos contava COMENTÁRIO como invocador
`acharInvocadores` classificava por **extensão**: `(p.endsWith('.md') ? doc : exec)`. Uma
linha `// roda via foo.mjs no cron` dentro de um `.php` contava como invocação executável.

```
ANTES:  96 scripts não-teste · 3 sem invocador executável
DEPOIS: 96 scripts não-teste · 6 sem invocador executável
```

Os 3 escondidos: `governance-audit.mjs` (comentário em `Kernel.php:461`), `hook-bites.mjs`
(comentário em `modulo-preflight-warning.mjs:69`), `reguas-cross-model.mjs` (docblock em
`critica.mjs:320`).

**O custo real:** na triagem de 27/07 ([W]: *"eu quero que ligue… faça todos"*), 13
candidatos foram julgados um a um — **esses 3 nunca chegaram à mesa**. Um deles é o
`hook-bites`, o **dead-man's-switch dos hooks**, ele próprio morto. Mesma família do
`plans-index` (2026-07-24).

Fix: `ehComentario(linha, path)` decide pelo **idioma** do arquivo. 7 asserções novas,
incluindo **controles positivos** (código real em JS/YAML continua contando — sem eles um
classificador que dissesse "tudo é comentário" passaria).

## Achados NÃO corrigidos — decisão [W]

| # | Achado | Por que não toquei |
|---|---|---|
| **D1** | fatura cobra valor do **plano** ou o **negociado**? | muda faturamento de cliente real (Tier 0 valor: exige dupla confirmação + impacto antes) |
| **D2** | "sem plano (avulso)" existe como produto? A UI **abre selecionada** nessa opção (`Index.tsx:1484`) e o banco recusa → 500 | é produto |
| **D3** | o `skipped` deve avançar `next_due_date`? | é o de prazo (jan/2027) |
| — | **OficinaAuto** (LIVE prod, Martinho biz=164): nightly com **21 failed + 49 errors** (26,3% não-verde), invisível na porta do PR | módulo em produção |
| — | **PaymentGateway** (Tier 0 dinheiro): **7 failed + 46 errors**, 5 arquivos `Settings/*` 100% error | Tier 0 |
| — | **2 comandos agendados não existem em runtime** (um é `health-probe-channels`, nos dois ambientes) + 14 de 233 não registrados | a lista nominal não chegou à síntese |

## ⚠️ Honestidade sobre a varredura de órfãos

**129 achados brutos · só 24 passaram por contraditório · 11 desses caíram (46%).** Os
outros **105 nunca viram cético** — populações de comandos, fósseis e código-morto estão
praticamente inteiras sem verificação. Não trate aqueles números como veredito.

Correção de vocabulário que o cético impôs: chamar os testes fora de lane de "órfãos" está
**errado** — eles rodam na nightly do CT 100. O rótulo honesto é **"sem mordida per-PR"**.
Essa premissa errada causou 5 das 11 refutações.

Outros buracos declarados: `route:list` **nunca rodou** (então "controller sem rota" é
análise estática, o que a §5 desaconselha); o container do CT 100 está 1 arquivo defasado;
clone raso, então **nenhuma data** veio de `git log`.

## Superfície que fica medida e declarada

O sweep de órfãos varre `scripts/governance/` **não-recursivo**: 96 não-teste de **247**
`.mjs` em `scripts/**`. Seguem sem vigilância: `scripts/tests/`, `scripts/qa/`,
`scripts/lib/`, `scripts/pr-critic/`. Expandir é outro intent (commit-discipline).

## Resíduo operacional

O container `oimpresso-staging` do CT 100 tem `Modules/TeamMcp/Tests/Feature/ForjaRoutesSmokeTmpTest.php`
— arquivo **temporário de outra sessão** que redeclara `forjaRotasAbas()` e **quebra a suíte
inteira** ao rodar por `--filter`. Contornado com path direto; não removi (é de terceiro).

## Estado

- MCP indisponível no fechamento (`Server Oimpresso MCP — Wagner unavailable`) — sem
  snapshot de `my-work`/`cycles-active` nesta rodada. Declarado, não inventado.
- 8 handoffs de 2026-07-28 no main antes deste.
