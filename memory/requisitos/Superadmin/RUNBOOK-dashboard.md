---
id: requisitos-superadmin-runbook-dashboard
title: "RUNBOOK — /superadmin (Visão geral da plataforma · Blade → Inertia)"
module: Superadmin
tela: superadmin/Dashboard/Index
owner: W
status: ativo
last_validated: "2026-08-19"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
spec_ref: memory/requisitos/Superadmin/SPEC.md
---

# RUNBOOK — `/superadmin` (visão geral, Inertia/React)

F1 do MWART (ADR 0104) para a onda **SA-O1**. A tela hoje é Blade/AdminLTE
(`superadmin::superadmin.index`) e passa a `Inertia::render`.

- **Fonte de design:** `prototipo-ui/cowork/superadmin-page.jsx` → `ViewVisao()` (L599-757).
  Desceu ao espelho em 19/08 por `--export-from` (fiel por construção); antes disso era
  LIVE-ONLY, existia no Cowork e **não** no git.
- **Contrato + casos de origem:** `cowork-inbox/SUPERADMIN-F1-2026-08-18.md` no projeto Cowork.
- **Onde a Page mora:** `Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx`
  — decisão [W] 19/08 (a Page vive no módulo, como o `Site/Pricing`). O namespace Inertia
  **não** muda com o local do arquivo (`resources/js/app.tsx` L104-138), então o call-site é
  `Inertia::render('superadmin/Dashboard/Index')` e fica na mesma família do `Usuario360`.

---

## 1. O que o backend sustenta HOJE (e o que não sustenta)

O `SuperadminDashboardService` cobre **6 dos 10 blocos** que o F1 pede pra view `visao`. O resto
**não tem query** — e renderizar com número inventado seria pior que não renderizar.

> **A contagem mudou em 2026-08-20 e não é por progresso** — é porque a fonte apareceu. O "4 de 9"
> anterior era estimativa minha lendo uma cópia do F1 colada no chat. O F1 original
> (`cowork-inbox/SUPERADMIN-F1-2026-08-18.md` §1) lista **10** blocos, e estava invisível ao
> repositório até [#6019](https://github.com/wagnerra23/oimpresso.com/pull/6019). O 6 entregue
> inclui o MRR, que passou a funcionar no [#5981](https://github.com/wagnerra23/oimpresso.com/pull/5981).

| Bloco do protótipo | Origem real | Onda |
|---|---|---|
| KPI novas assinaturas (R$) + novos cadastros | `statsForPeriod($ini,$fim)` | SA-O1 |
| KPI sem assinatura | `countNotSubscribedBusinesses()` | SA-O1 |
| Tendência mensal (12 m) | `buildMonthlyRevenueChart()` | SA-O1 |
| Cadastros recentes (5 linhas) | query direta em `business` | SA-O1 |
| Segmented de período (Hoje/Semana/Mês/Ano) | ✅ janela rolante dita em texto (UC-SA-001) | SA-O1 |
| KPI MRR | ✅ `SubscriptionRepository::mrrBaselineCached` — avulso fica de fora **por construção**, medido 2026-08-20 (ver nota R1 abaixo). ⚠️ ciclo não-mensal tem risco latente, mesma nota | SA-O1b |
| Funil trial→pago | ❌ sem query — depende de `subscriptions.trial_end_date` | SA-O1b |
| Churn 30 d | ✅ `canceladas` — saídas de 30 d em `rb_subscriptions.canceled_at` | SA-O1b |
| Motivos de churn | ❌ **a coluna certa é `rb_subscriptions.churn_reason`**, ver nota abaixo | — |
| Receita por pacote | ❌ sem query | SA-O1b |
| Fila "Vencendo ou vencido" | 🟡 derivável de `findOverdueApproved()` | SA-O1b |
| "O que fazer primeiro" (3 itens navegáveis) | ❌ sem query — depende do funil e da fila | — |

> **R1 do F1 ("MRR só soma pacote recorrente com preço > 0; gratuito e avulso entram no
> caixa do mês, nunca na recorrência") — medido em prod 2026-08-20, biz=1.** A nota anterior
> dizia *"não verifiquei se ele exclui avulso"*. Verificado, e a resposta tem duas metades
> que não se parecem.
>
> **Avulso: fica de fora por CONSTRUÇÃO, não por filtro.** No RecurringBilling avulso não é
> uma assinatura com ciclo avulso — é uma **fatura sem `subscription_id`**
> ([`Invoice.php:18`](../../../Modules/RecurringBilling/Models/Invoice.php) *"ou avulsa, sem
> subscription_id quando o operador cobrar uma única vez"*; mesmo conceito em
> [`SubscriptionCachedFieldsObserver.php:40`](../../../Modules/RecurringBilling/Observers/SubscriptionCachedFieldsObserver.php)).
> O `mrrBaselineCached` itera `rb_subscriptions`, nunca `rb_invoices`, e
> `rb_subscriptions.plan_id` é **NOT NULL** (`constrained` sem `nullable`, migration
> `2026_05_06_001001`) — logo toda assinatura tem plano recorrente e uma fatura órfã não tem
> por onde entrar na soma. Não existe filtro a auditar aqui: o avulso não está no universo.
> Contado no mesmo dia: **4.039 faturas, 0 avulsas** (`subscription_id IS NULL`).
>
> **Gratuito: 0 em prod, e inerte por aritmética.** 161 planos, **161 pagos, 0 gratuitos**;
> 109 assinaturas ativas, **109 com valor efetivo > 0** (contando o `metadata.valor`, que
> sobrepõe o `plan.valor`). Ainda que existisse, um plano de preço 0 contribuiria 0 para uma
> **soma** — a regra é satisfeita sem precisar de cláusula. `StorePlanRequest` aceita
> `valor >= 0`, então cadastrar um gratuito é possível.
>
> ⚠️ **Risco latente encontrado no mesmo cálculo — ciclo não-mensal infla até 12×.** O
> `match` de [`SubscriptionRepository::mrrBaselineCached`](../../../Modules/RecurringBilling/Repositories/SubscriptionRepository.php)
> compara os ciclos em **português** (`mensal|trimestral|semestral|anual`), mas
> `rb_plans.ciclo` é um enum em **inglês** — `['monthly','quarterly','semiannual','yearly','custom']`
> (migration `2026_05_06_001000`, única que define a coluna). Quando a assinatura não tem
> `metadata.ciclo`, o fallback lê `plan.ciclo` em inglês, nenhum braço casa, e o
> `default => $valor` soma o valor **cheio** em vez de dividir. Fatores de inflação:
> `quarterly` **3×**, `semiannual` **6×**, `yearly` **12×**.
>
> **Hoje o número exibido está correto, e isso é coincidência do dado, não do código:** os
> 161 planos são **todos `monthly`**, e para mensal o valor cheio é justamente o resultado
> certo — o caminho errado chega ao lugar certo. Das 109 ativas, 108 caem no `default`
> (`plan.ciclo='monthly'`) e 1 casa no braço `'mensal'` (`metadata.ciclo`, gravado em PT por
> `recurring-billing.store`). Confirmado por dois caminhos independentes no mesmo dia — o
> `mrrBaselineCached` real × uma réplica que aplica a regra por extenso aceitando os dois
> vocabulários: **delta exatamente zero** (e o canônico não é zero, conferido junto para não estar
> lendo um pipeline morto).
>
> **O risco é alcançável sem deploy:** `StorePlanRequest:47` aceita os cinco valores do enum
> e [`Planos/Create.tsx:31-34`](../../../resources/js/Pages/RecurringBilling/Planos/Create.tsx)
> oferece "Trimestral / Semestral / Anual / Customizado" no combo — `monthly` é só o
> *default* do formulário, não uma trava. No dia em que alguém cadastrar um plano anual, o
> MRR daquela assinatura entra 12× maior. Note que
> [`PlanController.php:366-369`](../../../Modules/RecurringBilling/Http/Controllers/PlanController.php)
> já faz a mesma divisão **em inglês, corretamente** — os dois vocabulários convivem no
> módulo, e é essa convivência que produz o defeito.
>
> Correção **não aplicada aqui**: mexe em cálculo de valor, então vale a REGRA MESTRE de
> [`proibicoes.md`](../../proibicoes.md) — dupla confirmação + antes→depois apresentado a [W]
> antes de aplicar. O antes→depois de **hoje** é vazio (0 registros mudam de valor, porque
> não há plano não-mensal), o que torna esta a janela barata para consertar: corrigir agora
> não move nenhum número existente.
>
> Reproduzir (SSH Hostinger, warm-up antes — [`how-trabalhar.md`](../../how-trabalhar.md)):
> ```sql
> SELECT business_id, ciclo, COUNT(*) FROM rb_plans WHERE deleted_at IS NULL GROUP BY 1,2;
> SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(s.metadata,'$.ciclo')),'(sem)') AS meta, p.ciclo, COUNT(*)
>   FROM rb_subscriptions s LEFT JOIN rb_plans p ON p.id=s.plan_id
>  WHERE s.status IN ('active','trialing','past_due') AND s.deleted_at IS NULL GROUP BY 1,2;
> SELECT COUNT(*) FROM rb_invoices WHERE subscription_id IS NULL AND deleted_at IS NULL;
> ```

> **Onde o gráfico de motivos tem que ler — medido em prod 2026-08-19, contra as duas
> tabelas.** A decisão SA-O0 de [W] pediu `cancel_reason` por migration, e ela foi entregue
> (`2026_08_19_000002`, aplicada). Mas a medição *posterior* mostra que ela ficou na tabela
> que nunca churna:
>
> | tabela | linhas | cancelados | coluna de motivo | preenchida |
> |---|---|---|---|---|
> | `subscriptions` (licença UltimatePOS) | 126 | **0** — 126/126 `approved` | `cancel_reason` | 0 |
> | `rb_subscriptions` (cobrança real) | 162 | **52** com `canceled_at` | `churn_reason` | 0 de 52 |
>
> `subscriptions` **nunca teve um cancelamento** na história do sistema — o ciclo de vida dela
> não roda (e o mesmo pré-flight achou 113 `approved` vencidas que nada expira). O churn que
> este dashboard reporta já vem de `rb_subscriptions`, pela mesma fonte do MRR.
>
> `rb_subscriptions.churn_reason` **já existe desde 2026-05-16 e tem caminho de escrita
> completo**: `AssinaturaService::cancelar()` grava, `CancelSubscriptionRequest` exige
> (`required` + `Rule::in`), e há teste (`D4 — cancelar() é idempotente + grava churn_reason`).
> Estar 0 de 52 é **história, não defeito**: 50 dos 52 cancelamentos são anteriores a
> 2026-05-16 — vieram da migração do legado, quando a coluna não existia. (Os outros 2 são
> posteriores e estão vazios: cancelamento que não passou pelo service. É achado do
> RecurringBilling, não deste dashboard.)
>
> **Consequência prática:** quem for desenhar o gráfico de motivos lê `churn_reason`. Ler
> `cancel_reason` produziria um gráfico permanentemente vazio com cara de funcionando. O que
> fazer com as 2 colunas ociosas — manter (custam nada, e licença pode um dia ser cancelada)
> ou derrubar por migration — é decisão de [W]; não desfaço a SA-O0 por conta própria.

**Dívida encontrada no pré-flight:** `SuperadminController::index()` **não usa** o service —
refaz a query de `not_subscribed` inline (`Business::leftjoin('subscriptions'...)`) enquanto
`countNotSubscribedBusinesses()` existe e faz o mesmo. A SA-O1 passa a chamar o service; a
query inline sai.

---

## 2. Vocabulário (trava a copy)

A UI **nunca** mostra o enum cru. Mapa fechado no F1 + medido no schema em 19/08:

| `subscriptions.status` | UI PT-BR | Tom |
|---|---|---|
| `approved` + `end_date` futura | Ativa | `success` |
| `waiting` | Pendente | `warning` |
| `declined` | Bloqueada | `danger` |
| `expired` | Vencida | `danger` |
| `cancelled` | Cancelada | `neutral` |
| sem linha em `subscriptions` | Sem assinatura | `neutral` |

⚠️ `declined` **não estava** no vocabulário do F1 e é usado em produção:
`OnCobrancaVencidaBloqueaSubscription:63` grava esse valor quando a cobrança vence.
`expired`/`cancelled` só passaram a ser graváveis no PR #5945 (o enum não os aceitava).

`business.is_active` é **outro eixo** — "Ativo/Inativo" do negócio, nunca fundido com o
status da assinatura.

---

## 3. Quando esta tela quebra (sintomas)

- **KPI zerado e sem erro:** `statsForPeriod` recebe data fora de ordem (`$ini > $fim`) —
  retorna 0 sem lançar. Conferir o segmented de período antes de culpar o banco.
- **Gráfico vazio com dados no banco:** `buildMonthlyRevenueChart` monta a chave `"Mon-YYYY"`;
  locale diferente no servidor muda a chave e o mês some do eixo.
- **403 pra quem deveria entrar:** o acesso tem **duas camadas**, e a primeira não é permissão.
  `App\Http\Middleware\Superadmin` compara o **username** com a lista
  `config('constants.administrator_usernames')` (separada por vírgula, via `.env`); só quem
  passa aí chega ao controller, que então checa a permissão Spatie `superadmin`.
  **Dar a permissão Spatie sem pôr o username na lista não abre a tela** — o 403 vem do
  middleware, antes do controller. Medido em 2026-08-19; o F1 do Cowork descrevia "Bouncer"
  e estava errado (o charter foi corrigido junto).
- **Tela em branco só em produção:** página nova de módulo exige o glob de
  `Modules/*/Resources/js/Pages/**` — se o namespace colidir com uma tela do núcleo, o gate
  `pages-colisao` acusa; em runtime o erro aparece só no console em DEV.

---

## 4. Smoke prod (R1 — evidência, não narração)

```bash
curl -sv https://oimpresso.com/superadmin 2>&1 | grep '^< HTTP'
```

Esperado: `302` para `/login` sem sessão. Autenticado como superadmin: `200` e o HTML traz
`data-page` com `"component":"superadmin/Dashboard/Index"` (é o que distingue Inertia de Blade).

Regressão adjacente (não podem mudar):

```bash
curl -sv https://oimpresso.com/pricing 2>&1 | grep '^< HTTP'
curl -sv https://oimpresso.com/superadmin/usuarios 2>&1 | grep '^< HTTP'
```

---

## 5. Tier 0 — invariantes

- **Cross-tenant é intencional aqui** (ADR 0093 §exceções Superadmin): as queries são globais
  por desenho — Wagner enxerga todos os negócios. **Nenhuma onda adiciona escopo de tenant
  nestas queries**; fazer isso quebraria o produto.
- Nenhum valor em R$ entra em log, PR ou commit (regra de redação Tier 0).
- A tela é leitura. Qualquer ação que mude estado de assinatura passa pelo
  `SubscriptionLifecycleService`, nunca por `->update(['status' => ...])`.

---

## 6. Atrito conhecido de CI — `visual-regression` cancelado

Medido em 2026-08-19, nos PRs #5955 e #5957 desta onda. O check `visual-regression` é
**required**, e um run `cancelled` no head SHA trava o merge mesmo com **0 falhas** — a
branch protection lê `cancelled` como bloqueio, enquanto `gh pr checks` mostra "pass".
O sintoma é PR `BLOCKED` com `fail=` vazio e `pending=0`.

O comentário do próprio [`visual-regression.yml`](../../../.github/workflows/visual-regression.yml)
descreve o vetor e prescreve `gh run rerun`. **Aqui isso não resolveu.** O que foi tentado,
em ordem, e o resultado:

| Tentativa | Resultado |
|---|---|
| `gh run rerun` (2×) | cancelled |
| commit vazio → SHA novo (`synchronize`) | cancelled |
| close + reopen (`reopened`, que a config diz NÃO cancelar) | cancelled |

Sinal que aponta a causa: o head SHA acumulou **dois** check-runs `visual-regression`,
ambos `cancelled` — ou seja, mais de um run nasce no mesmo commit e um cancela o outro.
O PR **não tinha label**, então o race `opened`+`labeled` que o workflow documenta não
explica este caso.

**Como sair:** o primeiro run de um PR costuma passar (foi o caso às 14:39 no #5957); o
problema aparece nos pushes seguintes. Se travar, tente um push quando a fila do PR
estiver vazia (`pending=0`) — run competindo parece ser o gatilho. Persistindo, é
diagnóstico do dono do workflow: mexer no `concurrency` está fora do escopo de quem só
passa por ali, e o próprio arquivo avisa para não desligar o cancel cegamente.

---

## 7. Refs

- Protótipo: [`prototipo-ui/cowork/superadmin-page.jsx`](../../../prototipo-ui/cowork/superadmin-page.jsx) `ViewVisao()`
- Charter: `Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.charter.md`
- Casos: `Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.casos.md`
- Service: [`SuperadminDashboardService`](../../../Modules/Superadmin/Services/SuperadminDashboardService.php)
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
