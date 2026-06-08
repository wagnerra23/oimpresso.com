# Recalibração pricing — sinal real R$ [redacted Tier 0]-850/m — 2026-05-09

**Status**: proposed (Wagner valida)
**Sinal**: Martinhão da Caçamba (R$ [redacted Tier 0]/m, ERP de 26 anos, está trocando agora) + Gold Comunicação (R$ [redacted Tier 0]/m, trocou pelo Mubisys recentemente)
**Domínio:** pricing comercial — tier ICP comunicação visual médio porte
**Alinhamento ADR 0105:** ambos são clientes pagantes do mercado real (não hipóteses); Gold já reportou troca; Martinhão está em compra ativa. Sinal qualificado, não wish.

---

## 1) Pricing atual oimpresso (ref: `memory/sales/2026-05/06-pricing-tiers.md`)

| Tier | Mensal | Setup | Compromisso | Multi-business | Users | NFe/m |
|---|---|---|---|---|---|---|
| Starter | R$ [redacted Tier 0] | R$ [redacted Tier 0] | mensal | 1 | até 3 | 100 manual |
| Pro | R$ [redacted Tier 0] | R$ [redacted Tier 0] ¹ | 12 meses | 1 | até 10 | 500 auto |
| Enterprise | R$ [redacted Tier 0] | R$ [redacted Tier 0] | 24 meses | até 5 | ilimitado | ilimitado |

¹ Setup Pro reduzido R$ [redacted Tier 0] → R$ [redacted Tier 0] default na Rodada 3 (pesquisa horizontais 05).

**Gap detectado:** ticket real validado R$ [redacted Tier 0]-850/m **fica num vácuo entre Pro (R$ [redacted Tier 0] +39%) e Enterprise (R$ [redacted Tier 0] -43%)**. Pro está R$ [redacted Tier 0]-250/m abaixo do que dois prospects ICP estão pagando hoje. Enterprise está R$ [redacted Tier 0]/m acima — fora da janela.

---

## 2) Tabela 4 cenários

| Métrica | A — não-mexer | B — Pro→R$ [redacted Tier 0] | C — +Pro Plus R$ [redacted Tier 0] | D — add-ons modulares |
|---|---|---|---|---|
| Receita marginal por cliente vs hoje | 0 | +R$ [redacted Tier 0]/m | +R$ [redacted Tier 0] a R$ [redacted Tier 0]/m (depende de qual tier escolhe) | +R$ [redacted Tier 0]-300/m (depende de quantos add-ons) |
| Risco perder prospect Bling-graduado (R$ [redacted Tier 0]-450/m) | nulo | médio (Pro fica 4x Bling) | baixo (Pro segue R$ [redacted Tier 0]) | baixo (Pro segue R$ [redacted Tier 0]) |
| Risco perder prospect Mubisys/Zênite-graduado (R$ [redacted Tier 0]-850/m) | **alto** (Pro parece "barato demais p/ ser sério" + Enterprise parece overkill) | baixo | nulo (sweet spot) | baixo |
| Esforço mudança (deck, site, ROI calc, sales material) | 0 | médio (1-2 dias) | alto (3-5 dias — novo tier exige reposicionar tudo) | alto (3-5 dias — sales script + billing config) |
| Capacidade time absorver (5 pessoas, [F] e [W] mexem em deck) | OK | OK | tensão (mais 1 tier = mais 1 onboarding template) | tensão (cobrança proporcional + add-on toggle no checkout) |
| Alinhamento ADR 0105 (cliente como sinal) | OK (sinal não exige ação imediata) | OK (capta sinal direto) | OK (capta sinal e abre upsell sem forçar) | OK (modular = ativa só quando cliente pede) |
| Risco confusão cliente | nulo | baixo | médio (4 tiers = "qual é meu?") | alto (cliente compõe tier, dificulta comparação) |
| Sinal pro mercado/concorrente | nenhum | "oimpresso subiu 33%" | "oimpresso lança Pro Plus" | "oimpresso virou modular" |
| Reversibilidade (se conversão cair) | n/a | média (rebaixar é constrangedor) | alta (descontinua tier silenciosamente) | alta (desativa add-on bundle) |
| Janela ROTA LIVRE (cliente piloto, R$ [redacted Tier 0] legacy) | preserva | preserva (Pro mantém R$ [redacted Tier 0] só pra ela; novos R$ [redacted Tier 0]) — cria 2 SKUs internos | preserva (ela segue Pro) | preserva (ela segue Pro sem add-on) |

**Receita marginal:** ranges, não números fechados — não inventar projeção sem amostra estatística.

---

## 3) Análise por cenário

### Cenário A — Não mexer

**Tese:** ainda é cedo. 2 sinais (Martinhão+Gold) é amostra mínima. Pricing tem inércia comercial (ROTA LIVRE legacy preservada, deck Rodada 3 já circulando, ROI calculator escrito).

**Problema:** Mubisys tem **1.800 empresas ativas** alegadas (research 02). Se o ticket médio Mubisys é R$ [redacted Tier 0]/m, o mercado já validou que comunicação visual média paga **R$ [redacted Tier 0]-850/m por ERP vertical**. Manter Pro em R$ [redacted Tier 0] = sinaliza "produto inferior" pra prospect Mubisys-graduado que está acostumado a pagar mais. Conta Azul Performance vai a R$ [redacted Tier 0]/m e atende empresa >R$ [redacted Tier 0]M/ano sem ser vertical — oimpresso vertical com IA cobrar R$ [redacted Tier 0] é **subprecificação ativa**.

**Veredicto:** seguro no curto prazo, deixa dinheiro na mesa no médio. Receita marginal estimada: 0.

### Cenário B — Subir Pro pra R$ [redacted Tier 0]

**Tese:** alinhamento direto com ticket real. R$ [redacted Tier 0] fica R$ [redacted Tier 0] abaixo de Gold (R$ [redacted Tier 0]) e R$ [redacted Tier 0] abaixo de Martinhão (R$ [redacted Tier 0]) — **prospect sente "estou pagando menos que pago hoje"** (gancho psicológico forte na troca).

**Problema #1 — Bling graduado:** prospect saindo de Bling Titânio T2 (R$ [redacted Tier 0]/m) ou T3 (R$ [redacted Tier 0]/m) vai sentir Pro R$ [redacted Tier 0] como **2.3x-4x** o que paga hoje. Esse era exatamente o segmento que setup R$ [redacted Tier 0] default na Rodada 3 tentou descomplicar. Subir pra R$ [redacted Tier 0] reverte parte do trabalho.

**Problema #2 — ROTA LIVRE legacy:** ela paga Pro R$ [redacted Tier 0] (com 50% off em troca de case). Subir pra R$ [redacted Tier 0] cria 2 SKUs internos: "Pro v1 R$ [redacted Tier 0] (legacy)" e "Pro v2 R$ [redacted Tier 0] (novo)". Gerenciável mas é dívida operacional pra time pequeno.

**Problema #3 — sinal pro mercado:** subir 33% é evento comunicável. Concorrente pode reagir (Mubisys reduz R$ [redacted Tier 0] → R$ [redacted Tier 0] e nos enquadra; Zênite acelera versão web). Pricing público sobe é decisão de **uma vez só** — não dá pra subir e descer.

**Veredicto:** captura sinal mas **fricciona Bling-graduado** e queima opção. Receita marginal +R$ [redacted Tier 0]/m por novo cliente Pro, condicional a manter conversão.

### Cenário C — Adicionar tier intermediário "Pro Plus" R$ [redacted Tier 0]/m

**Tese:** preserva Pro R$ [redacted Tier 0] (entry pra Bling-graduado) **E** captura sweet spot R$ [redacted Tier 0]-850 com tier que justifica diferença concreta.

**Composição sugerida Pro Plus R$ [redacted Tier 0]:**
- Tudo do Pro
- Multi-business até 2 (Pro = 1; Enterprise = 5)
- Jana IA ilimitada (Pro = 500 perguntas/m; Enterprise = ilimitado)
- API full + webhooks (Pro = read-only)
- Suporte prioritário WhatsApp 8h (Pro = chat 24h; Enterprise = WhatsApp 4h)
- Setup R$ [redacted Tier 0] (Pro = R$ [redacted Tier 0]; Enterprise = R$ [redacted Tier 0])

**Vantagem decisiva:** Pro Plus R$ [redacted Tier 0] fica **R$ [redacted Tier 0] ACIMA de Gold (R$ [redacted Tier 0])** e **R$ [redacted Tier 0] ACIMA de Martinhão (R$ [redacted Tier 0])**. Em vez de "mais barato que pago hoje" (cenário B), pitch vira **"você paga R$ [redacted Tier 0] hoje pra um sistema legacy/sem IA — pague R$ [redacted Tier 0] pra ter NFe automática + Jana + multi-business + API"**. Diferença R$ [redacted Tier 0]-69/m é trivial vs valor entregue.

**Problema #1 — complexidade comunicação:** 4 tiers = "qual é meu?". Mitigação: Wagner faz quiz de 3 perguntas no site ("quantos func?", "1 ou múltiplas empresas?", "API/integração externa?") que recomenda tier — padrão Bling/Tiny.

**Problema #2 — risco cliente escolher errado:** prospect que devia ir pra Enterprise (multi-business 5+) escolhe Pro Plus pelo preço. Mitigação: Pro Plus capa em 2 businesses (não 5); cliente >2 businesses cai natural pra Enterprise.

**Problema #3 — esforço time:** [F] e [W] reabrem deck, slide 9 do pitch deck Rodada 3, ROI calculator, site `/precos`. 3-5 dias de trabalho.

**Veredicto:** **melhor balance** captura sinal sem queimar entry tier. Receita marginal por novo cliente Pro Plus: +R$ [redacted Tier 0]/m (R$ [redacted Tier 0] vs R$ [redacted Tier 0] baseline). Receita marginal de upsell Pro→Pro Plus em base existente: opcional (não força).

### Cenário D — Pricing dinâmico add-ons modulares

**Tese:** cliente compõe ticket. Pro R$ [redacted Tier 0] base + Jana Premium R$ [redacted Tier 0] + Multi-loja R$ [redacted Tier 0] = R$ [redacted Tier 0] — pousa naturalmente em R$ [redacted Tier 0]-900.

**Vantagem psicológica:** cliente sente "estou customizando, não pagando tier inflado" — gancho de autonomia.

**Problema #1 — cobrança complexa:** RecurringBilling precisa suportar add-on toggle, prorate em meio de mês, downgrade limpo. Hoje suporta add-on simples (já existe Jana add-on R$ [redacted Tier 0] no Starter), mas escalar pra **toggle dinâmico em qualquer tier** é trabalho de engenharia (1-2 sprints) + cobrança financeira mais complexa pra [E] gerir.

**Problema #2 — comparação difícil:** prospect compara "oimpresso R$ [redacted Tier 0]+R$ [redacted Tier 0]+R$ [redacted Tier 0]" com "Mubisys R$ [redacted Tier 0] fechado". Cognição extra. Mubisys ganha por **ter um número só**.

**Problema #3 — discounting descontrolado:** vendedor desativa add-on pra fechar = ticket real cai. Pricing modular sem disciplina vira race-to-bottom interno.

**Vantagem ADR 0105:** modular = só ativa quando cliente pede explicitamente = sinal qualificado pulando wish. Forte alinhamento principal.

**Veredicto:** elegante mas **prematuro pro tamanho do time**. Receita marginal +R$ [redacted Tier 0]-300/m por cliente, condicional a engenharia entregar billing limpo.

---

## 4) Recomendação

**Cenário C — adicionar tier intermediário "Pro Plus" R$ [redacted Tier 0]/m.**

### Justificativa quantitativa

1. **Captura ticket real validado.** Martinhão (R$ [redacted Tier 0]) e Gold (R$ [redacted Tier 0]) ficam R$ [redacted Tier 0]-69 abaixo de Pro Plus — diferença justificável pelo upgrade concreto (Jana ilimitada + multi-business + API). Cenário A deixa esses prospects irem pra Pro R$ [redacted Tier 0] e perdemos R$ [redacted Tier 0]/m por cliente em runway.
2. **Não fricciona Bling-graduado.** Pro segue R$ [redacted Tier 0] — entry tier preservado pra prospect saindo de Bling Titânio R$ [redacted Tier 0]-350. Cenário B perderia esse segmento.
3. **Reversível.** Se em 90d a conversão Pro Plus for <30% do funil esperado, descontinua silenciosamente (cliente Pro Plus já assinado mantém pricing legacy). Cenário B (subir Pro→R$ [redacted Tier 0]) é evento público e irreversível — pricing público que sobe e desce queima credibilidade.

### Por que não os outros

- **Não A:** Mubisys/Gold/Martinhão são 3 sinais convergentes (research 02 + 2 prospects ativos hoje) de que **R$ [redacted Tier 0]-900/m é janela média do mercado**. Não capturar = subprecificação ativa.
- **Não B:** queima Bling-graduado e é irreversível.
- **Não D:** complexidade billing + risco discounting interno excedem capacidade do time hoje (5 pessoas, [E] já tensiona em fechamento mensal). Reconsiderar D em 2027 quando RecurringBilling tiver maturidade.

---

## 5) Plano de implementação (Cenário C)

### O que muda em `memory/sales/2026-05/06-pricing-tiers.md`

- Adicionar coluna **Pro Plus** entre Pro e Enterprise na tabela "Resumo dos 3 tiers" (que vira 4 tiers)
- Mensal R$ [redacted Tier 0] [draft] / Setup R$ [redacted Tier 0] [draft] / Compromisso 12 meses / Multi-business até 2 / Users até 15 / Vendas/m até 1.500 / NFe/m até 1.000 auto / Suporte WhatsApp 8h / Treinamento 4h ao vivo / Migração guiada premium
- Tabela "Módulos por tier": Jana ilimitada · API full+webhooks · MemCofre · multi-business 2 · Asaas/Inter/C6 + reconciliação multi-banco
- Notas estratégicas: adicionar "Pro Plus é o tier de quem está saindo de Mubisys/Zênite — preço justifica upgrade concreto, não é Pro inflado"

### Atualizar deck slide 9, site, ROI calculator

- **Deck pitch Rodada 3 (slide 9):** trocar coluna Pro/Enterprise por Pro/Pro Plus/Enterprise. Pro Plus em destaque visual ("recomendado pra gráfica >R$ [redacted Tier 0]k/m faturamento")
- **Site `/precos`:** quiz 3 perguntas direciona pra tier — padrão Bling/Tiny
- **ROI calculator:** adicionar campo "ticket atual ERP" — se >R$ [redacted Tier 0]/m, recomenda Pro Plus; se <R$ [redacted Tier 0] recomenda Pro

### Comunicação ao prospect Martinhão

Mensagem específica abaixo (item 6).

### Cronograma

- **D+0 a D+2** ([W]): aprovar este draft + atualizar `06-pricing-tiers.md`
- **D+2 a D+5** ([F]+[W]): atualizar deck + site `/precos` + ROI calculator
- **D+5** ([W]): enviar mensagem Martinhão (e Gold se ainda não fechou Mubisys formal)
- **D+30**: medir conversão (3 prospects fecham? ticket médio cresce?)
- **D+90**: validar manter/descontinuar (>30% funil em Pro Plus = manter)

---

## 6) Mensagem específica pra Martinhão

> Olá [Martinhão], aqui é o Wagner do oimpresso.
>
> Vi que você está procurando ERP novo depois de 26 anos com o atual — troca grande, parabéns por revisitar. Sei que você tá olhando opções na faixa que você paga hoje (R$ [redacted Tier 0]/m).
>
> Acabei de finalizar o tier que cobre seu perfil exato: **Pro Plus R$ [redacted Tier 0]/m**. R$ [redacted Tier 0] a mais do que você paga, mas com:
>
> - **NFe automática a partir do boleto pago** — você não clica mais "emitir nota" depois que o cliente paga, sai sozinha em segundos
> - **Jana IA com memória** — pergunta no celular "quanto faturei essa semana?" e ela responde com SQL auditável, não inventa
> - **Multi-business até 2** — se você quiser separar pessoa física da PJ ou abrir filial, já está incluso
> - **API + webhooks** — integra com seu banco/CRM/marketplace sem ficar refém
> - **Suporte WhatsApp 8h SLA** — não é chat genérico, é meu time
>
> Posso mostrar em 25 minutos numa call. Se fizer sentido, trial 14 dias sem cartão pra você importar 5 clientes e emitir 1 NFC-e teste com seu CFOP real, antes de qualquer assinatura.
>
> [link calendário] · WhatsApp [W]
>
> — Wagner

**Opcional pra Gold (se ainda não fechou Mubisys formal):**

> Olá [Gold], aqui é o Wagner do oimpresso.
>
> Soube que você fechou com Mubisys recente em R$ [redacted Tier 0]/m. Antes que vire contrato firmado, vale 25 minutos? Tenho duas coisas que Mubisys não tem:
>
> 1. **NFe automática quando o boleto cai** — não é "emitir NFe", é o boleto pago disparar a nota sem clique humano
> 2. **API aberta** — Mubisys tem reclamação pública (fev/2023) de cliente médio porte dizendo que **não dá pra integrar com nada externo**. oimpresso nasceu API-first.
>
> Pro Plus R$ [redacted Tier 0]/m. R$ [redacted Tier 0] a mais que você fechou. Trial 14d sem cartão. Sem pressão — se ficar com Mubisys, sem stress.
>
> [link calendário] · WhatsApp [W]
>
> — Wagner

---

## 7) Métricas de validação (rastreáveis)

| Janela | Métrica | Meta | Fonte |
|---|---|---|---|
| 30 dias | Prospects qualificados que aceitam call após mensagem Pro Plus | ≥ 5 | tracking comercial Wagner |
| 30 dias | Fechamentos com Pro Plus (novos clientes) | ≥ 3 | RecurringBilling subscriptions |
| 60 dias | Conversão funil Pro Plus / Enterprise | ≥ 30% no Pro Plus | RecurringBilling + sales log |
| 90 dias | Ticket médio novos clientes (mensal) | crescer 15-25% vs Pro-only baseline | RecurringBilling MRR |
| 90 dias | Churn 30d Pro Plus | < 10% | RecurringBilling |

**Reverter se:** conversão funil cair >40% vs baseline atual (sinal de que 4 tiers confundiu prospect a ponto de abandonar).

---

## 8) Risco principal (mudar pricing nesta fase)

**Sinal pro mercado/concorrente.** Adicionar tier R$ [redacted Tier 0] é evento comunicável — Mubisys, Zênite, e horizontais (Bling/Tiny) podem reagir. Risco realista:
- **Mubisys reduz R$ [redacted Tier 0] → R$ [redacted Tier 0] e enquadra oimpresso como "premium não justificado"** — improvável (pricing Mubisys não é público hoje, mudar exige call comercial individual; reação demora 60-90d)
- **Concorrente lança feature de IA copycat** — risco real, mas Jana com memória persistente + Meilisearch hybrid + ContextoNegocio é vantagem técnica de 6-12 meses ([ADR 0035](../0035-stack-ai-canonica-wagner-2026-04-26.md))

**Mitigação:**
1. **Implementar gradual, sem post LinkedIn** de "lançamos Pro Plus". Pro Plus aparece no `/precos` site, no deck, no email pro prospect — mas nada de fanfarra pública nas primeiras 4 semanas. Concorrente que olha LinkedIn não pega.
2. **Não atualizar `pricing-tiers.md` no commit que vai pra branch pública/PR** sem flag `[draft]` — preserva opção de reverter sem registro histórico de "subimos pricing então caímos atrás" (governança ADR 0094 transparência segue: este `proposals/` é o histórico interno).
3. **Manter ROTA LIVRE em Pro v1 R$ [redacted Tier 0]** sem renegociar — cliente piloto não sente mudança.

---

## 9) Próximos passos (Wagner valida)

- [ ] Wagner aprova cenário C (ou escolhe A/B/D)
- [ ] [F]+[W] atualizam `06-pricing-tiers.md` — adicionar Pro Plus
- [ ] [W] atualiza slide 9 deck + ROI calculator + site `/precos` (quiz 3 perguntas)
- [ ] [W] envia mensagem Martinhão (e Gold se janela aberta)
- [ ] [W] cria task MCP `pricing-pro-plus-conversao-30d` pra medir D+30
- [ ] D+30 e D+90: revisão métricas — manter/descontinuar/iterar

---

**Refs:**
- [`06-pricing-tiers.md`](../../sales/2026-05/06-pricing-tiers.md) — pricing atual
- [`05-pricing-real-concorrentes-horizontais.md`](../../research/2026-05-prospeccao/05-pricing-real-concorrentes-horizontais.md) — Bling/Tiny/Conta Azul/Asaas/Iugu
- [`02-concorrentes-zenite-mubisys.md`](../../research/2026-05-prospeccao/02-concorrentes-zenite-mubisys.md) — verticais ICP
- [`14-case-study-rotalivre-anonimizado.md`](../../sales/2026-05/14-case-study-rotalivre-anonimizado.md) — caso piloto SP
- [ADR 0022](../0022-meta-5mi-ano-financeira.md) — meta R$ [redacted Tier 0]M/ano
- [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado
