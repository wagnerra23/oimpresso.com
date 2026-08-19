---
id: modules-superadmin-pages-superadmin-dashboard-index-charter
page: /superadmin
component: Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx
related_prototype: prototipo-ui/cowork/superadmin-page.jsx
owner: wagner
status: draft
last_validated: "2026-08-19"
parent_module: Superadmin
related_adrs: [104, 93, 374]
tier: B
charter_version: 1
---

# Page Charter — /superadmin (DRAFT)

> **Status:** criado em 2026-08-19 na onda SA-O1 (Blade → Inertia).
> Os **Non-Goals** e os **Anti-hooks** abaixo foram **transportados** do F1 do Cowork
> (`cowork-inbox/SUPERADMIN-F1-2026-08-18.md`), não inferidos por mim — e **[W] ratificou em
> 2026-08-19**, o que os habilita a virar Pest GUARD.
>
> Segue `draft` por um motivo só, e não é falta de aval: o gate `charter-live-signal` exige
> **sinal de prod** pra `status: live`, e esta tela ainda não foi ao ar. Promove a `live` o PR
> curto que vier depois do deploy, carregando a evidência do smoke — marcar `live` antes seria
> afirmar o que ainda não é verdade.
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

- Segmented de período (Hoje/Semana/Mês/Ano) recalculando os KPIs por **janela rolante**, com
  a janela dita em texto — sem recarregar a página (partial reload do Inertia).
- 4 KPIs do período: novas assinaturas (R$), novos cadastros, sem assinatura, MRR.
- Tendência mensal de vendas — 12 meses, barra, último mês destacado.
- Cadastros recentes — 5 linhas, com negócio, dono, pacote, status da assinatura e data.
- Vocabulário PT-BR fechado: negócio, assinatura, pacote, MRR, trial. O enum do banco
  **nunca** aparece na tela (mapa no RUNBOOK §2).

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

## Pendências antes de `status: live`

1. ~~[W] ratifica os Non-Goals e os Anti-hooks~~ — **feito em 2026-08-19**.
2. **Sinal de prod**: deploy + smoke real de `/superadmin` (o `charter-live-signal` exige, e é
   o único item que ainda segura o `live`).

Independentes do `live`, seguem em aberto:

- Blocos da SA-O1b: MRR, funil trial→pago, churn (depende da migration `cancel_reason`),
  receita por pacote, fila "Vencendo ou vencido".
- Decisões D1/D2/D3 do F1 — impersonar, `pages`/`pricing` no CMS, `subscription/pay` de quem é.
