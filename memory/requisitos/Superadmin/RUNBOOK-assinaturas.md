---
id: requisitos-superadmin-runbook-assinaturas
title: "RUNBOOK — /superadmin/superadmin-subscription (Assinaturas · DataTables → Inertia)"
module: Superadmin
tela: superadmin/Assinaturas/Index
owner: W
status: ativo
last_validated: "2026-08-20"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
spec_ref: memory/requisitos/Superadmin/SPEC.md
---

# RUNBOOK — `/superadmin/superadmin-subscription` (assinaturas, Inertia/React)

F1 do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para a
onda **SA-O4a**. A tela servia DataTables por AJAX (`superadmin::superadmin_subscription.index`
+ `DataTables::of(...)`) e passa a `Inertia::render` com paginação **server-side**.

- **Fonte de design:** projeto Cowork `019dcfd3-…`, arquivo
  `cowork-inbox/SUPERADMIN-F1-2026-08-18.md` §1 view `assinaturas` (charter) e §2 UC-SA-008/009
  (casos). O desenho renderizado é
  [`prototipo-ui/cowork/superadmin-page.jsx`](../../../prototipo-ui/cowork/superadmin-page.jsx)
  → `ViewAssinaturas()` (L1044) e `AssinaturaForm()` (L557).
- **Page:** `Modules/Superadmin/Resources/js/Pages/superadmin/Assinaturas/Index.tsx`.
- **Rota:** `Route::resource('/superadmin-subscription', SuperadminSubscriptionsController::class)`
  → `index()`. A rota **não muda** nesta onda.

---

## 1. O vocabulário de status é o assunto desta tela (e é onde ela pode mentir)

O F1 fala em **Aprovada · Trial · Pendente · Vencida · Cancelada**. O banco fala outra língua, e
a tradução não é 1-para-1 — se ela sair errada, a tela reporta receita errada.

| `subscriptions.status` | Rótulo | Observação |
|---|---|---|
| `approved` + `end_date` no futuro (ou nula) | **Ativa** | é a "Aprovada" do F1 |
| `approved` + `end_date` no passado | **Vencida** | venceu sem ninguém rodar o sweep |
| `waiting` | **Pendente** | aguardando baixa |
| `declined` | **Bloqueada** | gravado por `OnCobrancaVencidaBloqueaSubscription` |
| `expired` | **Vencida** | gravado por `SubscriptionLifecycleService::expire()` |
| `cancelled` | **Cancelada** | gravado por `SubscriptionLifecycleService::cancel()` |

Duas coisas que essa tabela deixa explícitas e que o F1 não previu:

1. **`trial` NÃO é um status.** No protótipo é; no banco, período de teste é a coluna
   `trial_end_date`. "Em trial" é **derivado**: assinatura viva cujo `trial_end_date` ainda não
   passou. É por isso que o KPI de trial desta tela conta por data, não por `status`.
2. **`declined` (Bloqueada) existe em produção e não existe no F1.** O F1 tem 4 KPI e o quarto é
   *"Vencidas ou canceladas"* — `declined` não cabe em nenhum dos quatro. A tela mostra o rótulo
   na coluna (o dado não some) e o KPI declara o recorte. Empurrar `declined` para dentro de
   "cancelada" seria falsificar: bloqueio por inadimplência e cancelamento a pedido são eventos
   comerciais diferentes.

**Dono único do mapa.** Este mapa vivia copiado em dois controllers
(`SuperadminController::rotuloAssinatura` e `BusinessController::rotuloDeAssinatura`, idênticos).
Esta onda extraiu para `Modules/Superadmin/Support/RotuloAssinatura.php` e apontou os dois para
lá — uma terceira cópia nesta tela seria a divergência garantida
([§5 proibicoes 2026-08-02](../../proibicoes.md), *"corrigir UMA de N implementações
duplicadas"*). O `enum` cru **nunca** chega ao `.tsx`.

## 2. Por que a query mudou

O legado montava o join e devolvia tudo ao DataTables, que ordenava e paginava no cliente. Duas
trocas:

| Antes | Agora | Por quê |
|---|---|---|
| `DataTables::of($query)` sem `paginate` | `paginate(20)->withQueryString()` | ordenação e página passam a ser do servidor |
| Coluna `action` com HTML de botão embutido na query | ações no `.tsx` | HTML dentro de query é o que tornava a coluna intraduzível |

O join é `subscriptions → business → packages`, os dois 1-para-1 a partir da assinatura — **não
há o risco de multiplicação de linha que a SA-O2 teve** (lá o negócio tinha N locais e N
assinaturas; aqui a assinatura tem um negócio e um pacote). `paginate()` conta certo sem
subquery escalar.

## 3. Os 3 filtros (paridade com o F1) + ordenação

| Filtro | Query string | Implementação |
|---|---|---|
| Pacote | `pacote=<id>` | `where('p.id', …)` — **mesmo contrato** do filtro da SA-O2 |
| Status | `status=ativa\|trial\|pendente\|vencida\|cancelada\|bloqueada` | vocabulário de TELA, traduzido para o enum na query |
| Criada em | `periodo=7d\|30d\|mes` | `whereDate('subscriptions.created_at', '>=', …)` |

O filtro de status recebe o rótulo, não o enum: quem monta a URL é o front, e o front não conhece
`declined`. A tradução mora em `RotuloAssinatura::filtro()`, ao lado do mapa de leitura — os dois
sentidos no mesmo dono, ou eles divergem.

Valor fora da lista vira `null` e não chega à query (mesmo `opcaoValida()` da SA-O2).

**Ordenação** (`ordem=` + `dir=`): o F1 pede colunas ordenáveis. São 5 permitidas
(`criado · negocio · status · inicio · preco`), whitelisted — coluna fora da lista cai no default
`subscriptions.id desc`. Sem whitelist, `orderBy($request->input())` é injeção.

## 4. Quando esta tela quebra (sintomas)

- **KPI de trial em zero com trials existindo** — `trial_end_date` nulo. O legado só preenche a
  coluna quando o pacote tem `trial_days > 0` **e** a assinatura passou por
  `_get_package_dates()`; assinatura criada à mão fica sem.
- **"Vencidas" cresce sozinha e nada muda de status** — é o esperado: o rótulo é derivado da
  data. Só o `expire()` grava `expired`, e ele roda por sweep. A tela não escreve status por
  leitura — se escrevesse, abrir uma lista viraria escrita em massa.
- **Total certo, páginas erradas** — alguém reintroduziu um join 1-para-N (o candidato é
  `business_locations`, se um dia a coluna de cidade entrar aqui).
- **Filtro some ao paginar** — falta `withQueryString()` no back, ou o front parou de mesclar os
  filtros atuais no `irPara()`.

## 5. Smoke prod (R1 — evidência, não narração)

```bash
curl -sv https://oimpresso.com/superadmin/superadmin-subscription 2>&1 | grep '^< HTTP'
```

Esperado: `302` para `/login` sem sessão. Autenticado como superadmin: `200`, e o `data-page`
traz `"component":"superadmin/Assinaturas/Index"`.

Regressão adjacente (não podem mudar):

```bash
curl -sv https://oimpresso.com/superadmin 2>&1 | grep '^< HTTP'
```

```bash
curl -sv https://oimpresso.com/superadmin/business 2>&1 | grep '^< HTTP'
```

```bash
curl -sv https://oimpresso.com/superadmin/packages 2>&1 | grep '^< HTTP'
```

## 6. Tier 0 — invariantes

- **Cross-tenant é intencional** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
  §exceções Superadmin): a lista mostra assinaturas de TODOS os negócios por desenho. Nenhuma
  onda adiciona escopo de tenant aqui.
- **Esta onda é LEITURA.** Nenhuma rota de escrita é adicionada ou alterada. As ações do F1
  (mudar status, editar vigência, cancelar) são a SA-O4b e passam pelo
  `SubscriptionLifecycleService` — **nunca** por `update(['status' => …])` direto, que é o que o
  legado faz e o que dispensa o audit trail.
- **Nenhum valor em R$ entra em log, PR, commit ou arquivo** — e esta tela tem uma coluna de
  preço inteira. O `.tsx` formata o número que vem do payload; não existe literal monetário no
  código nem nos fixtures.
- O enum de `subscriptions.status` **nunca** aparece cru na tela (§1).

## 7. O que NÃO entrou nesta onda (e por quê)

| Peça do F1 | Situação |
|---|---|
| **Ações do kebab** (status · datas · cancelar) | **SA-O4b** — precisam do `SubscriptionLifecycleService` no caminho de escrita, e o cancelamento carrega motivo (R3 + `cancel_reason`) |
| **Seleção múltipla + BulkBar** | as duas ações do F1 são "baixar comprovantes" e "exportar" — nenhuma existe no backend (linhas abaixo) |
| **"Baixar comprovante"** | **não há comprovante** no sistema: `payment_transaction_id` é uma string do gateway, não um documento. Medido em 2026-08-20: zero rota, zero storage, zero geração de PDF em `Modules/Superadmin/` |
| **"Exportar" / "Lançar assinatura"** | `create`/`store` existem no resource, mas `store()` exige `can('subscribe')` — permissão diferente da que guarda esta tela (`can('superadmin')`). É decisão [W], não migração |
| **Paginação 6/página** | produção usa **20**, igual à SA-O2. Divergência declarada no contrato, não escondida — decisão [W] em aberto |
| **Subtítulo com total aprovado em R$** | a tela mostra **contagens**, sem receita. MRR tem dono (`SubscriptionRepository::mrrBaselineCached`, do RecurringBilling) e a regra R1 do F1 está sendo verificada em sessão paralela; um segundo oráculo de receita aqui seria régua duplicada |

## 7.1 Dois achados de medição desta onda (não consertados aqui, e por quê)

O type-check das três telas do módulo trouxe dois fatos que valem registro — ambos **fora** do
escopo desta onda, ambos decisão [W]:

1. **O subtítulo do `PageHeader` nunca renderizou em Visão geral nem em Negócios.** As duas
   passam `subtitle=`, e a prop do componente é `description=` — React descarta a desconhecida e
   nada aparece. A tela desta onda usa `description` e mostra o subtítulo. **Não corrigi as
   irmãs de propósito:** corrigir faz surgir uma linha de texto que hoje não existe, o que muda
   o pixel e invalida as **duas** baselines de `visual-regression` já aprovadas. Isso é troca de
   aparência, não conserto silencioso.
2. **`tsc --noEmit` do repositório NÃO cobre `Modules/**`.** O `tsconfig.json` inclui só
   `resources/js/**`, então nenhuma das telas Inertia hospedadas dentro de módulo é
   type-checkada — rodar o comando padrão e ver silêncio aqui não prova nada (foi como o
   defeito 1 sobreviveu a duas ondas). A verificação desta onda foi feita com um `tsconfig`
   temporário estendendo o do repo, e com **controle positivo** (`--listFiles` contando os 9
   arquivos do módulo dentro do programa). Estender o `include` oficial é decisão de escopo do
   repositório inteiro.

E uma dívida declarada, não um achado: o módulo usa `@/Components/shared/PageHeader`, que está
**`@deprecated`** em favor do canon `@/Components/PageHeader` (v3.8, ADR 0189/0190). Esta tela
seguiu as irmãs para não ficar a única visualmente diferente das três. O ratchet
`pageheader-gate` não pega isso porque só observa `resources/js/**` — o módulo é ponto cego
dele. Migrar as três de uma vez, com aprovação visual, é onda própria.

## 8. Refs

- Protótipo: [`prototipo-ui/cowork/superadmin-page.jsx`](../../../prototipo-ui/cowork/superadmin-page.jsx) `ViewAssinaturas()` L1044
- Charter/casos: ao lado do `.tsx`
- Contrato: `prototipo-ui/contrato/superadmin-assinaturas.contract.json`
- Irmãos: [RUNBOOK-negocios.md](RUNBOOK-negocios.md) · [RUNBOOK-dashboard.md](RUNBOOK-dashboard.md)
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
