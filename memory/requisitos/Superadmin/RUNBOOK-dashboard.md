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

O `SuperadminDashboardService` cobre 4 dos 9 blocos que o protótipo desenha. O resto **não
tem query** — e renderizar com número inventado seria pior que não renderizar.

| Bloco do protótipo | Origem real | Onda |
|---|---|---|
| KPI novas assinaturas (R$) + novos cadastros | `statsForPeriod($ini,$fim)` | SA-O1 |
| KPI sem assinatura | `countNotSubscribedBusinesses()` | SA-O1 |
| Tendência mensal (12 m) | `buildMonthlyRevenueChart()` | SA-O1 |
| Cadastros recentes (5 linhas) | query direta em `business` | SA-O1 |
| KPI MRR | ❌ sem query — R1 exige só pacote recorrente com preço > 0 | SA-O1b |
| Funil trial→pago | ❌ sem query — depende de `subscriptions.trial_end_date` | SA-O1b |
| Churn 30 d + motivos | ❌ depende da migration `cancel_reason` (decisão [W] 19/08) | SA-O1b |
| Receita por pacote | ❌ sem query | SA-O1b |
| Fila "Vencendo ou vencido" | 🟡 derivável de `findOverdueApproved()` | SA-O1b |

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

## 6. Refs

- Protótipo: [`prototipo-ui/cowork/superadmin-page.jsx`](../../../prototipo-ui/cowork/superadmin-page.jsx) `ViewVisao()`
- Charter: `Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.charter.md`
- Casos: `Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.casos.md`
- Service: [`SuperadminDashboardService`](../../../Modules/Superadmin/Services/SuperadminDashboardService.php)
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
