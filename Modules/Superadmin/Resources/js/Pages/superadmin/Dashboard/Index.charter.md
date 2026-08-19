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
- **3** KPIs do período: novas assinaturas (R$), novos cadastros, sem assinatura.
- Tendência mensal de assinaturas — barra, último mês destacado.
- Cadastros recentes — 5 linhas, com negócio, status da assinatura e data.
- Vocabulário PT-BR fechado: negócio, assinatura, pacote, MRR, trial. O enum do banco
  **nunca** aparece na tela (mapa no RUNBOOK §2).

Alvo do F1 ainda **não** entregue — está aqui pra não se perder, não como promessa cumprida:

- 4º KPI (MRR) e os blocos funil/churn/receita-por-pacote/fila: sem query no backend.
- Série de 12 meses **contínua**: hoje o service agrupa por mês existente e **pula mês sem
  assinatura** (o smoke mediu 11 pontos, sem Oct-2025). Buraco em série temporal engana o olho.
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
- **Não inventa número que o banco não tem.** Enquanto MRR, funil, churn e receita-por-pacote
  não tiverem query, os blocos ficam fora da tela (ver RUNBOOK §1).

---

## Pendências (SA-O1b em diante)

Fechado em 2026-08-19: ratificação [W] dos Non-Goals/Anti-hooks · deploy · smoke real
(evidência no campo `smoke:`) · `status: live`.

Em aberto:

- **Blocos sem query**: MRR, funil trial→pago, churn (depende da migration `cancel_reason`,
  decisão [W] 2026-08-19), receita por pacote, fila "Vencendo ou vencido".
- **Gráfico visualmente vazio** — o smoke mostrou todas as barras em zero, porque os pacotes
  dos cadastros recentes têm `package_price` 0. O número está certo; a tela não comunica nada.
  Decidir o que a tendência deve medir quando a receita é zero.
- **Série com buraco** — 11 pontos em vez de 12 (o service pula mês sem assinatura).
- Decisões D1/D2/D3 do F1 — impersonar, `pages`/`pricing` no CMS, `subscription/pay` de quem é.
