---
id: modules-superadmin-pages-superadmin-dashboard-index-charter
page: /superadmin
component: Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx
related_prototype: prototipo-ui/cowork/superadmin-page.jsx
owner: wagner
status: live
last_validated: "2026-08-19"
smoke: "2026-08-19 — render prod OK (Chrome MCP, sessao WR2 Sistemas, https://oimpresso.com/superadmin): segmented Hoje/Semana/Mes/Ano com Mes ativo + 'Janela rolante — encerra em 19/08/2026'; 3 KPIs vivos (novas assinaturas, 7 novos cadastros, 6 sem assinatura); Cadastros recentes com 5 negocios reais e status 'Ativa'; sidebar AppShellV2 preta (UI-0023). Rota sem sessao = 302 -> login; regressao adjacente /pricing 200 e /superadmin/usuarios 302. Build do Vite serve superadmin/Dashboard/Index.tsx. RESSALVA medida no mesmo smoke: o grafico de tendencia renderiza com todas as barras em ZERO (package_price 0 nos pacotes recentes) e a serie traz 11 pontos, nao 12 — o service agrupa por mes existente e pula Oct-2025. Nao e regressao da SA-O1; entra na SA-O1b."
related_us: [US-SUPER-011]
parent_module: Superadmin
related_adrs: [104, 93, 374]
tier: B
charter_version: 1
---

# Page Charter — /superadmin

> **Histórico:** criado em 2026-08-19 na onda SA-O1 (Blade → Inertia), nasceu `draft` e foi a
> `live` no mesmo dia, depois do deploy e do smoke registrado no campo `smoke:` do frontmatter.
> Os **Non-Goals** e os **Anti-hooks** abaixo foram **transportados** do F1 do Cowork
> (`cowork-inbox/SUPERADMIN-F1-2026-08-18.md`), não inferidos por mim, e **[W] ratificou em
> 2026-08-19** — o que os habilita a virar Pest GUARD.
>
> Backend: `Modules\Superadmin\Http\Controllers\SuperadminController@index` (rota `GET /superadmin`).
> **Acesso em 2 camadas** (medido 2026-08-19 — o F1 dizia "Bouncer" e estava errado):
> o middleware `App\Http\Middleware\Superadmin` compara o **username** com
> `config('constants.administrator_usernames')`; só depois o controller checa a permissão
> Spatie `superadmin`. F1 registrada em
> [`RUNBOOK-dashboard.md`](../../../../../../../memory/requisitos/Superadmin/RUNBOOK-dashboard.md).
> Âncora de design: `ViewVisao()` em `prototipo-ui/cowork/superadmin-page.jsx` (L599-757).

---

## Mission

Responde **uma** pergunta: *"a plataforma está crescendo ou vazando?"*. É a tela de pulso do
negócio-SaaS — quantos negócios entraram, quantos assinaram, quanto entra por mês e quem está
prestes a sair. Não administra nenhum negócio específico: para isso existem as telas de
Negócios e Assinaturas.

Persona única: [W], no escritório, 1440px. Nenhuma outra persona entra aqui — admin de negócio
toma 403.

---

## Goals — Features (faz)

O que a tela entrega **hoje** (medido no smoke de 2026-08-19, não prometido):

- Segmented de período (Hoje/Semana/Mês/Ano) recalculando os KPIs por **janela rolante**, com
  a janela dita em texto — sem recarregar a página (partial reload do Inertia).
- **4** KPIs do período: novas assinaturas (R$), novos cadastros, sem assinatura e **MRR**
  (este entrou na SA-O1b).
- Tendência mensal de assinaturas — barra, último mês destacado, **série contínua**: mês sem
  assinatura aparece zerado em vez de sumir do eixo (SA-O1b).
- Cadastros recentes — 5 linhas, com negócio, status da assinatura e data.
- Vocabulário PT-BR fechado: negócio, assinatura, pacote, MRR, trial. O enum do banco
  **nunca** aparece na tela (mapa no RUNBOOK §2).

**O MRR diz por que está zero.** A regra R1 conta só recorrência vigente e paga; quando o
total dá zero o card distingue as duas causas possíveis — "nenhuma assinatura recorrente
vigente" × "N assinaturas vigentes sem preço no pacote". Sem isso o card parece quebrado, que
é o estado real de hoje: **nenhum dos pacotes tem preço cadastrado** (medido em prod
2026-08-19 — 13 vigentes, 13 sem preço).

Alvo do F1 ainda **não** entregue — está aqui pra não se perder, não como promessa cumprida:

- Blocos funil trial→pago / churn / receita-por-pacote / fila "Vencendo ou vencido": sem query
  no backend (churn depende da migration `cancel_reason`, aprovada por [W] em 2026-08-19).
- Coluna "dono" nos cadastros recentes.

## Non-Goals — Features (NÃO faz)

> Do F1 §Non-goals. Cada item vira Pest GUARD quando [W] ratificar.

- **Não é BI**: nenhum gráfico de série longa além dos 12 meses.
- **Não faz cobrança**: gateway é `Modules/PaymentGateway`; aqui só o registro e o status.
- **Não edita dado operacional do cliente** (produto, OS, venda).
- **Sem exclusão em lote de negócios** — só desativação, e isso é da tela de Negócios.

---

## UX targets

- Uma tela, uma pergunta: nada aqui exige abrir outra aba para ser entendido.
- Todo bloco que mostra número diz **de onde ele vem** no rodapé do card (a regra R1 do F1 —
  "gratuitos e avulsos ficam fora do MRR" — é dita na tela, não só no código).
- Bloco sem dado real **não é renderizado com mock**: ou tem query, ou não aparece.
- Sem emoji. Sentence case. Plural PT-BR correto (1 pagante / 2 pagantes).

---

## Contrato visual

Travado por [`prototipo-ui/contrato/superadmin-dashboard.contract.json`](../../../../../../../prototipo-ui/contrato/superadmin-dashboard.contract.json)
(ADR 0286), verificado no CI por `contrato-de-tela.mjs` — âncora `data-contract` + **copy literal** +
ordem. Fonte da copy: o §3 do F1 [CC] (`cowork-inbox/SUPERADMIN-F1-2026-08-18.md`), ancorado em
`prototipo-ui/cowork/superadmin-page.jsx` — a mesma âncora do `related_prototype`.

| Seção | Copy travada |
|---|---|
| `superadmin.dashboard.periodo` | Hoje · Semana · Mês · Ano |
| `superadmin.dashboard.kpis` | Novas assinaturas · Novos cadastros · Sem assinatura · Receita recorrente (MRR) |
| `superadmin.dashboard.tendencia` | Tendência mensal de assinaturas |
| `superadmin.dashboard.recentes` | Cadastros recentes |

⚠️ **4 das 10 seções que o F1 pede pra view `visao`.** Ficaram de fora as 6 que produção não tem
e que o §1 do [RUNBOOK-dashboard](../../../../../../../memory/requisitos/Superadmin/RUNBOOK-dashboard.md)
já registra como gap: funil trial→pago, churn 30 d com motivos, receita por pacote, fila
"Vencendo ou vencido" e "O que fazer primeiro". Contrato que nasce vermelho não trava nada, só
ensina a ignorar o gate.

> **Por que só agora:** o F1 estava no Cowork desde 2026-08-18 e era **invisível** ao repositório
> — o detector de frescor do espelho só olhava `.jsx/.html/.css/.js` ([#6019](https://github.com/wagnerra23/oimpresso.com/pull/6019)).
> A onda foi construída de uma cópia colada no chat: as copy acima bateram por acerto, não por
> contrato. Agora batem por contrato.

---

## Automation hooks (faz)

- Recalcula os KPIs ao trocar o período, por partial reload (`only:`), sem full page load.
- Props caras entram por `Inertia::defer` com skeleton — regra de `Inertia::defer` default.

## Anti-hooks (NÃO faz automaticamente)

> Do F1 + medições de 2026-08-19. Cada item vira Pest GUARD quando [W] ratificar.

- **Não expira, cancela nem cobra nada sozinha.** É tela de leitura. Mudança de estado de
  assinatura passa pelo `SubscriptionLifecycleService`, nunca por `->update(['status' => ...])`.
- **Não aplica escopo de tenant nas queries.** O cross-tenant aqui é intencional
  (ADR 0093 §exceções Superadmin) — Wagner enxerga todos os negócios. Adicionar `business_id`
  scope quebraria o produto.
- **Não inventa número que o banco não tem.** Enquanto funil, churn e receita-por-pacote
  não tiverem query, os blocos ficam fora da tela (ver RUNBOOK §1).

  > **MRR saiu desta lista em 2026-08-20**, com aprovação [W] — reconciliação do que o
  > [#5981](https://github.com/wagnerra23/oimpresso.com/pull/5981) já tinha entregue. Ele ganhou
  > query real (`SuperadminDashboardService::calcularMrr`, que **delega** ao
  > `SubscriptionRepository::mrrBaselineCached`) e chega às props legitimamente. O mesmo
  > encolhimento já estava no `UC-SADASH-05` e no teste de contrato desde 19/08; o charter é que
  > ficou pra trás — obedecê-lo como estava cobraria a remoção do card que [W] pediu.
  >
  > Os outros três seguem fora, e o `UC-SADASH-05` prova por asserção (`missing('funil')`,
  > `missing('churn')`, `missing('receitaPorPacote')`). Precisão sobre o churn: o **insumo** dele
  > já chega — `calcularMrr` devolve `canceladas` (saídas em 30 dias) e o card de MRR mostra
  > "N ativas · M canceladas em 30 dias". O que falta é o **bloco** de churn, não a fonte.

---

## Pendências (SA-O1b em diante)

Fechado em 2026-08-19: ratificação [W] dos Non-Goals/Anti-hooks · deploy · smoke real
(evidência no campo `smoke:`) · `status: live`.

Em aberto:

- **Blocos sem query**: funil trial→pago, churn, receita por pacote, fila "Vencendo ou vencido".

  > **Corrigido em 2026-08-20 (medição do #5981):** o churn **não** depende da migration
  > `cancel_reason`. Ela nasceu em `subscriptions` — a tabela do licenciamento legado, que nunca
  > churnou (medido em prod 19/08: 126 linhas, 0 cancelamentos) — enquanto
  > `rb_subscriptions.churn_reason` já existe desde 2026-05-16, com caminho de escrita completo
  > (`AssinaturaService::cancelar()` grava; `CancelSubscriptionRequest` exige). Está 0/52
  > preenchido por **história**, não por defeito: 50 dos 52 cancelamentos são anteriores à coluna
  > e vieram da migração do legado. Destino das 2 colunas ociosas em `subscriptions`: decisão [W].
- **Nenhum pacote do licenciamento legado tem preço** (medido em prod 2026-08-19: 75 pacotes
  `packages` ativos, todos com `price` 0; 13 assinaturas vigentes, 13 sem preço). **A medição
  continua verdadeira; a conclusão que ela sustentava foi REFUTADA em 2026-08-19.** Ela estava
  certa sobre a tabela e errada sobre QUAL tabela sustenta a receita: [W] apontou que "os preços
  já estão nos pacotes ativos, são preços reais", e a medição seguinte achou a fonte —
  `rb_plans`/`rb_subscriptions` (`Modules/RecurringBilling`), com os planos valorados e
  assinaturas ativas. `packages`/`subscriptions` está zerado porque **não cobra ninguém**.

  Consequências, item a item:

  - **MRR não mostra mais zero** — mostrava por ler a fonte errada, e o #5981 consertou.
  - **A pergunta de produto deste item está RESPONDIDA**: a receita é cobrada, e pelo
    RecurringBilling. Ela não precisa mais de decisão.
  - **DOIS blocos ainda têm o bug que o MRR tinha** (varrido em 2026-08-20: `package_price`
    aparece em 2 métodos do service, e os 2 chegam à tela):

    | símbolo | bloco na tela | efeito hoje |
    |---|---|---|
    | `SuperadminDashboardService::buildMonthlyRevenueChart()` | gráfico de tendência | barras em zero |
    | `SuperadminDashboardService::statsForPeriod()` → `new_subscriptions` | KPI **"Novas assinaturas"** | **sempre zero** |

    O segundo é o mais enganoso: o `Index.tsx` renderiza esse valor com `brl()` e a nota "soma
    do valor contratado na janela" — ou seja, um card de **dinheiro** que soma a coluna do
    licenciamento legado, que está zerada e não cobra ninguém. É a mesma fonte errada do MRR,
    no card ao lado, ainda não corrigida.

    Isso **deixa de ser** "ausência de dado legítima" e passa a ser dívida NOMEADA. Vale
    também para `receita por pacote` e `funil` quando ganharem query.

    ⚠️ **Corrigir isto é mudança de VALOR** (`proibicoes.md` §REGRA MESTRE): exige dupla
    confirmação do cálculo por dois caminhos independentes + tabela antes→depois apresentada
    a [W] **antes** de aplicar. Não é conserto de passagem — é decisão [W], como o #5981 foi.
- **113 assinaturas `approved` com vigência vencida** que ninguém expira — o
  `findOverdueApproved()` não tem invocador. Afeta todo KPI que recorta por vigência.
- Decisões D1/D2/D3 do F1 — impersonar, `pages`/`pricing` no CMS, `subscription/pay` de quem é.
