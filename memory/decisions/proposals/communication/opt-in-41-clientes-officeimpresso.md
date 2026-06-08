# Comunicação opt-in oimpresso Insights — 41 clientes — 2026-05-09

> **Autor:** Wagner (1ª pessoa) | **Status:** draft pra revisão dupla
> **Produto referenciado:** oimpresso Insights (snapshot financeiro mensal + benchmark setorial anônimo + análise IA Jana). Spec mãe: `memory/decisions/proposals/PRODUTO-OIMPRESSO-INSIGHTS-MASTER-SPEC.md`
> **Tom global:** Wagner-direto, PT-BR brasileiro, 26 anos de relação como trunfo principal — nunca minimiza histórico, sempre oferece saída honesta.

---

## Categorização (Wagner aprova nominal antes de disparar)

> ⚠️ **Lista nominal preservada apenas em planilha local de Wagner** — neste documento, identificadores anonimizados conforme restrição (`memory/proibicoes.md` → PII). Mapeamento `Cliente_NN ↔ razão_social` mantido fora do git em `~/Documents/oimpresso/opt-in-mapeamento-2026-05-09.xlsx`.

### 8 Saudáveis Ativos (Versão A) — alta probabilidade aceitar

| Código | Sinal de saúde | GMV proxy | Última atividade |
|---|---|---|---|
| Cliente_A1 | uso semanal, NFC-e ativa | alto | <7d |
| Cliente_A2 | uso semanal, multi-loja | alto | <7d |
| Cliente_A3 | uso quinzenal | médio-alto | <14d |
| Cliente_A4 | uso semanal | médio | <7d |
| Cliente_A5 | uso quinzenal, beta-tester histórico | médio | <14d |
| Cliente_A6 | uso semanal | médio-alto | <7d |
| Cliente_A7 | uso semanal | alto | <7d |
| Cliente_A8 | uso quinzenal | médio | <14d |

### 2 Inativos com GMV histórico alto (Versão B) — reaproximação

| Código | Última atividade | GMV histórico |
|---|---|---|
| Cliente_B1 | 90-150d | alto histórico |
| Cliente_B2 | 90-180d | alto histórico |

### 20 Churn Provável (Versão C) — fechamento honesto / win-back

| Faixa | Quantidade | Última atividade |
|---|---|---|
| Cliente_C01..C20 | 20 | >180d sem login ou sem transação |

### 11 Outros — microempresa GMV baixo

Comunicar via lote standard simplificado (subset Versão A enxuta — sem call telefônica, só email + WhatsApp). Não detalhado neste doc — usar template Versão A com supressão do bloco "benchmark setorial" e CTA único "ativar grátis 30d".

---

## Versão A — Saudáveis Ativos (8 clientes)

### Email

**Assunto:** [Nome do cliente], um experimento novo pra quem é parceiro há 26 anos

**Corpo (HTML simples):**

```html
<p>Oi [primeiro_nome],</p>

<p>Aqui é o Wagner, da WR Sistemas. Pulando direto ao ponto: depois de 26 anos
fornecendo o OfficeImpresso pra você, lancei algo novo que acho que faz
diferença real no seu dia.</p>

<p>Chama <strong>oimpresso Insights</strong>. É um relatório mensal automático
que mostra:</p>

<ul>
  <li>Sua receita, despesa e inadimplência do mês — sem você abrir
  relatório nenhum</li>
  <li>Como você está vs. a média de outras gráficas do mesmo porte
  (totalmente anônimo dos dois lados)</li>
  <li>Análise da Jana, nossa IA, apontando 1 ou 2 coisas pra olhar com
  carinho no mês</li>
</ul>

<p>Tem 3 níveis de participação — você escolhe. Pode pegar só o seu,
pode entrar no benchmark setorial, ou pode liberar dado anonimizado pra
relatórios de mercado (esse último paga você de volta).</p>

<p>Não muda nada do que você já usa. Não cobro a mais por isso na fase 1.
Você decide o nível na tela e pode sair quando quiser.</p>

<p><strong>👉 <a href="https://oimpresso.com/insights/opt-in?c=[token]">
Vê os 3 níveis e decide aqui</a></strong></p>

<p>Qualquer coisa, me responde esse email ou WhatsApp direto: 41 99999-9999.</p>

<p>Abraço,<br>
Wagner Rocha<br>
WR Sistemas — oimpresso.com</p>
```

**Texto puro fallback:**

```
Oi [primeiro_nome],

Aqui é o Wagner, da WR Sistemas. Pulando direto ao ponto: depois de 26 anos
fornecendo o OfficeImpresso pra você, lancei algo novo que acho que faz
diferença real no seu dia.

Chama oimpresso Insights. É um relatório mensal automático que mostra:
- Receita, despesa e inadimplência do mês, sem você abrir relatório nenhum
- Comparação com a média de gráficas do mesmo porte (anônimo dos dois lados)
- Análise da Jana (nossa IA) apontando 1-2 coisas pra olhar no mês

Tem 3 níveis: só seu, com benchmark, ou liberando dado anonimizado pra
mercado (esse último paga você de volta). Você escolhe.

Não cobro a mais na fase 1. Pode sair quando quiser.

Vê os 3 níveis aqui: https://oimpresso.com/insights/opt-in?c=[token]

Qualquer coisa, responde esse email ou WhatsApp 41 99999-9999.

Abraço,
Wagner Rocha
WR Sistemas — oimpresso.com
```

### WhatsApp

```
Oi [primeiro_nome], aqui é o Wagner da WR (26 anos te atendendo).

Lancei um relatório mensal automático que mostra a saúde financeira da sua
gráfica + benchmark anônimo com gráficas parecidas + 1 análise da nossa IA.

Você escolhe o nível de participação — não muda nada do que já usa, e na
fase 1 não cobro a mais.

Dá uma olhada (2 min): https://oimpresso.com/insights/opt-in?c=[token]

Se preferir, te ligo terça ou quarta. Qual horário?
```

### Call telefônica (30-45s)

**Abertura (10s):**
> "[Primeiro_nome], é o Wagner da WR. Tudo bem? Tô ligando rapidinho — 5 minutos no máximo — pra te falar de uma coisa nova que acho que faz sentido pro seu negócio. Tem 5 minutos agora ou prefere que eu volte mais tarde?"

**Pitch (30s — só se aceitou conversar):**
> "Então — depois de 26 anos rodando o OfficeImpresso aí, eu lancei um relatório mensal automático chamado oimpresso Insights. Ele mostra sua receita, despesa e inadimplência do mês sem você precisar abrir relatório. E mostra como você tá comparado com gráficas parecidas — totalmente anônimo. Tem 3 níveis de participação, você escolhe. Não muda nada do que você usa hoje, e na fase 1 eu não cobro a mais. Tô ligando porque você é dos primeiros que quero ter dentro. Posso te mandar o link pra olhar?"

**Perguntas SPIN (discovery):**

1. **(Situation)** "Hoje quando você quer saber quanto faturou no mês, como você olha?"
2. **(Problem)** "Já aconteceu de você descobrir tarde que um cliente tava atrasando muito? Ou que uma máquina tava custando mais do que rendia?"
3. **(Implication)** "Quanto de tempo por semana você gasta abrindo relatório, jogando em planilha, fazendo conta? E em final de mês, como é?"
4. **(Need-payoff)** "Se chegasse no seu e-mail dia 5 de cada mês um resumo de 1 página com 'receita X, despesa Y, inadimplência Z, e olha esses 2 pontos de atenção' — isso te ajudaria?"
5. **(Closing soft)** "E se eu te mostrasse como você tá comparado com a média de gráficas do seu porte — sem ninguém saber que é você nem você saber quem são os outros — isso te interessaria?"

**Objeções esperadas + resposta:**

1. **"Não preciso de mais relatório, já tenho minha planilha"**
   → "Entendo perfeitamente. A diferença é que esse aqui chega pronto, sem você fazer nada — e tem o comparativo com gráficas parecidas, que planilha não dá. Se depois de 1 mês você achar que não agrega, você desliga em 1 clique e nada muda no resto. Quer testar 30 dias sem compromisso?"

2. **"Quanto custa?"**
   → "Na fase 1, fase de validação com vocês que são parceiros antigos, não cobro a mais. Quando virar produto comercial, vai ter um valor — ainda tô calibrando, mas vai ser bem proporcional ao que ele te economiza em tempo. E quem tá comigo agora trava o preço da fase 1 quando virar comercial."

3. **"Meu dado vai pra concorrente?"**
   → "Não. Eu te explico exatamente como funciona: o benchmark é totalmente anônimo dos dois lados — ninguém vê seu nome, e você não vê o nome dos outros. É só média e faixa do setor. E tem um nível 3 opcional onde você libera dado anônimo pra relatórios de mercado, e nesse caso você é pago pelo dado. Você escolhe o nível. Se quiser ficar só no nível 1 — só seu — também tá ótimo."

---

## Versão B — Inativos GMV Alto (2 clientes)

### Email

**Assunto:** [Nome], faz tempo — uma pergunta honesta

**Corpo (texto puro principal — HTML idêntico, sem floreio):**

```
Oi [primeiro_nome],

É o Wagner, da WR Sistemas. Vi que você não tá usando muito o OfficeImpresso
nos últimos meses e queria entender, sem pressão nenhuma:

- Mudou alguma coisa aí na operação?
- Tá usando outro sistema agora?
- Ou simplesmente foi parando e nunca voltou?

Pergunto porque a gente já trabalha junto há um tempão e eu prefiro saber
o que aconteceu — independente de você voltar ou não — do que ficar
imaginando.

Se quiser, posso te mandar de graça um snapshot dos últimos 30 dias do que
seu sistema gerou (receita, despesa, principais clientes) — só pra você
ter o número na mão, mesmo que seja pra fechar a conta com a gente.
Sem compromisso.

WhatsApp direto comigo: 41 99999-9999. Pode mandar áudio se for mais fácil.

Abraço,
Wagner
```

### WhatsApp

```
Oi [primeiro_nome], Wagner aqui da WR.

Vi que você sumiu do sistema. Sem pressão pra voltar — só queria entender
se mudou de sistema, mudou de operação, ou foi acumulando.

Se quiser, te mando de graça um resumo dos últimos 30 dias do seu — só
pra você ter o número na mão. Sem compromisso.

Pode mandar áudio se for mais fácil.
```

### Call telefônica (30-45s)

**Abertura (15s):**
> "[Primeiro_nome], Wagner aqui da WR. Tô ligando porque vi que você sumiu do sistema nos últimos meses e queria saber, sem nenhuma pressão de venda: aconteceu alguma coisa? Quero só entender, mesmo. Tem 3 minutinhos?"

**Pitch — escutar primeiro, oferecer depois (30s só se cliente abriu):**
> "Então olha — eu não vou te empurrar nada. Mas posso te oferecer uma coisa que talvez ajude: deixa eu rodar um snapshot dos últimos 30 dias seus, te mando por email, e você usa pra qualquer coisa — pra decidir se volta, pra fechar conta, pra negociar com outro sistema, o que for. Sem custo, sem amarra. Topa?"

**Perguntas SPIN (foco discovery, não venda):**

1. **(Situation)** "Hoje, como tá rodando aí? Tá usando outro sistema, parou de operar, mudou modelo?"
2. **(Problem)** "Quando você parou de usar o nosso, foi porque faltou alguma coisa, ou outro sistema te ofereceu algo que faltava aqui?"
3. **(Implication)** "Independente de voltar ou não, você sente falta de alguma informação que tinha aqui e não tem mais?"
4. **(Need-payoff)** "Se a gente tivesse algo que te resolvesse [DOR_QUE_ELE_CITOU] hoje, valia uma conversa?"
5. **(Soft close ou learning fechado)** "Posso te perguntar diretamente: você se incomoda de eu te ligar daqui 6 meses pra ver como você tá, mesmo que seja só pra bater papo?"

**Objeções esperadas + resposta:**

1. **"Já migrei pra outro sistema, não vou voltar"**
   → "Faz total sentido. Só pra eu aprender — qual sistema, e o que pesou na decisão? Sua resposta me ajuda a melhorar pra outros clientes. E se um dia você quiser voltar, a porta tá aberta — sem ressentimento nenhum."

2. **"Parei de operar / fechei a empresa"**
   → "Poxa, sinto muito. Que bom que você atendeu pra eu saber. Posso te ajudar com algo? Exportar histórico, fechar conta de forma limpa? Fica à vontade."

3. **"Nem lembro mais como funciona"**
   → "Tranquilo, isso é normal. Posso te dar 30 minutos de papo guiado por telefone ou vídeo, sem custo, só pra você ver como tá hoje. Aí se fizer sentido você decide. Topa?"

---

## Versão C — Churn Provável (20 clientes)

### Email

**Assunto:** [Nome], 1 pergunta sincera (sem pitch)

**Corpo:**

```
Oi [primeiro_nome],

É o Wagner, da WR Sistemas. Vou ser direto: você não usa o OfficeImpresso
faz mais de 6 meses, e eu queria fazer 1 pergunta sincera, sem nenhum
pitch de vendas embutido:

Você ainda usa o sistema, ou já migrou pra outro?

Pergunto porque:
1. Se já migrou, eu queria entender pra qual e por quê — sua resposta
   ajuda eu melhorar pra outros clientes.
2. Se ainda usa pouquinho, posso saber o que travou.
3. Se quer fechar a relação de forma limpa, eu te ajudo a exportar
   histórico e desligar a conta sem dor.

Não tô tentando te trazer de volta. Se rolar de você se interessar por
algo novo (a gente tá lançando uma análise mensal automática chamada
oimpresso Insights), aí eu te conto — mas só se você pedir.

5 minutinhos de papo, qualquer dia. Pode responder esse email ou
WhatsApp 41 99999-9999.

Abraço,
Wagner
```

### WhatsApp

```
Oi [primeiro_nome], Wagner aqui da WR.

Pergunta sincera, sem pitch: você ainda usa o OfficeImpresso, já migrou
pra outro sistema, ou parou de operar?

Quero só entender — sua resposta me ajuda. Se quiser fechar a conta de
forma limpa também te ajudo.

Pode responder por áudio se for mais rápido 🙏
```

### Call telefônica (30-45s)

**Abertura (15s):**
> "[Primeiro_nome], Wagner aqui da WR Sistemas. Tô ligando bem rapidinho — sem pitch, prometo. Você não usa o sistema faz uns meses e eu queria só saber: já migrou pra outro, parou de operar, ou só foi acumulando? Pode ser bem honesto, não tô tentando vender nada agora."

**Pitch — só se ele perguntar "e tem novidade?" (30s):**
> "Olha, tem sim, mas eu só conto se você pedir. Lancei uma análise mensal automática chamada oimpresso Insights. Se um dia te interessar voltar, te mando o link. Por enquanto eu só queria fechar a relação de forma honesta com você, do jeito que merece — 26 anos é muita coisa pra deixar morrer no silêncio."

**Perguntas SPIN (puro learning, não venda):**

1. **(Situation)** "Hoje você usa qual sistema, ou tá tocando no braço/excel?"
2. **(Problem)** "O que te fez sair do nosso? Foi preço, foi feature que faltou, foi atendimento, foi outro sistema te oferecer algo melhor?"
3. **(Implication)** "Faz sentido pra mim — e o sistema atual tá te resolvendo bem?"
4. **(Need-payoff implícito)** "Se eu te perguntar daqui 6 meses 'mudou algo?', você se incomoda?"
5. **(Closing learning)** "Posso te pedir um favor? Em 1 frase: o que faltou no nosso pra você ficar?"

**Objeções esperadas + resposta:**

1. **"Não quero papo de vendedor"**
   → "Justo, e eu juro que não é. Sua resposta cabe em 1 frase no WhatsApp se preferir. Pode ignorar a ligação e responder lá. O importante é eu aprender."

2. **"Já migrei pra [Sistema_X]"**
   → "Que ótimo que você me conta. Só pra eu fechar a aprendizagem: o que pesou na decisão? Preço, feature, atendimento, indicação? Sua resposta ajuda muito."

3. **"Parei de operar"**
   → "Sinto muito de verdade. Posso ajudar com algo na transição — exportar histórico, fechar conta limpa? Fica à vontade pra pedir."

---

## Sequenciamento (ordem cronológica — 4 semanas)

### Semana 1 (12-18/maio/2026) — Saudáveis Ativos
- **Dia 1-2 (seg-ter):** 8 saudáveis recebem email Versão A. Wagner assina pessoalmente, envio escalonado (não disparar 8 simultâneos — parece automação).
- **Dia 3-5 (qua-sex):** WhatsApp follow-up Versão A (3 dias depois do email) — só pra quem não respondeu.
- **Dia 6-7 (sáb-dom):** Wagner faz call top 3 (Cliente_A1, Cliente_A2, Cliente_A7 — maior GMV). Conversa franca, escuta antes de pitchar.

### Semana 2 (19-25/maio/2026) — Inativos GMV Alto
- **Dia 8-10 (seg-qua):** 2 inativos recebem email Versão B. Spaced delivery — 1 por dia.
- **Dia 11-14 (qui-dom):** Wagner liga ambos pessoalmente. Call Versão B é puro escutar — pitch só se cliente abrir espaço.

### Semana 3 (26/maio-01/jun/2026) — Churn Provável (lote)
- **Dia 15-17 (seg-qua):** 20 churn recebem email Versão C em lote escalonado (4-5 por dia, evitando flag de SPF/spam). WhatsApp Versão C também escalonado pra evitar block API WhatsApp Business.
- **Dia 18-21 (qui-dom):** Aguarda respostas espontâneas. Não fazer follow-up agressivo.

### Semana 4 (02-08/jun/2026) — Tabulação + decisão
- **Dia 22-24:** Tabular respostas em planilha (`opt-in-respostas-2026-06-04.xlsx`) — 4 colunas: aceitou_opt_in_nivel, recusou_educadamente, churn_confirmado_sistema_x, win_back_potencial.
- **Dia 25-28:** Lista A (aceitaram) → onboarding Insights. Lista B (recusaram) → respeito + nota auto-mem. Lista C (win-back potencial) → próximo programa Q3/2026.
- **Sex 06/jun:** Retro `cycles-close --rollover` + ADR session log em `memory/sessions/2026-06-06-opt-in-officeimpresso-retro.md`.

---

## Métricas alvo

| Categoria | Métrica | Alvo |
|---|---|---|
| Saudáveis ativos (8) | aceita opt-in nível 1 (individual) | ≥80% (≥7 de 8) |
| Saudáveis ativos (8) | aceita opt-in nível 2 (benchmark) | ≥50% (≥4 de 8) |
| Saudáveis ativos (8) | aceita opt-in nível 3 (DaaS externo) | ≥25% (≥2 de 8) |
| Inativos GMV alto (2) | responde WhatsApp ou email | ≥50% (≥1 de 2) |
| Inativos GMV alto (2) | aceita snapshot grátis 30d | ≥30% (≥1 de 2) |
| Churn provável (20) | responde (qualquer canal) | ≥15% (≥3 de 20) |
| Churn provável (20) | volta a usar trial | ≥5% (≥1 de 20) |
| **Saúde de marca** | Reclame Aqui pós-comunicação | **0 reclamações novas** |
| **Saúde de marca** | opt-out / unsubscribe | ≤10% lista total (≤4) |

---

## Riscos

### Risco 1: Versão errada chega a cliente errado
**Cenário:** Cliente saudável recebe Versão C ("você sumiu") → trust quebrado em 1 email.
**Mitigação:**
- Lista nominal anonimizada com mapping em planilha local (não no git)
- **Revisão dupla obrigatória:** Wagner + Eliana[E] revisam mailing list cada categoria antes do disparo
- Disparo escalonado (não batch único) — permite abortar se erro detectado
- Email assinado pessoalmente (não no-reply) — cliente responde, Wagner vê erro rápido

### Risco 2: Cliente sentir invadido (especialmente Versão C)
**Cenário:** 20 clientes churn recebem email + WhatsApp + ligação no mesmo período → marca de stalking.
**Mitigação:**
- **Versão C: APENAS email + WhatsApp.** Sem call cold pra churn. Call só se cliente responder e pedir.
- Tom respeitoso explícito ("sem pitch", "1 frase basta", "pode ignorar")
- Opt-out trivial em todo email (link 1 clique "não quero mais ouvir da WR")
- WhatsApp Business API com lista de contatos válidos somente (Brasil LGPD Art. 7º — base legítima por relação contratual histórica documentada)

### Risco 3: WhatsApp marca como spam (block API)
**Cenário:** Disparo lote 20+ WhatsApp em sequência → Meta bloqueia número WR Business.
**Mitigação:**
- WhatsApp Business **API oficial** (não pessoal) — número dedicado opt-in
- Disparo escalonado: máx 5 mensagens/hora, máx 20/dia
- Template aprovado pelo Meta antes (categoria "utility" ou "service")
- Plano B: se bloqueio acontecer, migrar pra email-only nos próximos 90d

### **🚨 PRINCIPAL RISCO DE COMUNICAÇÃO QUE PODE QUEBRAR TRUST**

**Risco-mãe: Cliente sentir que 26 anos de relação foi "monetizada" — que Wagner virou vendedor depois de ter sido parceiro técnico.**

**Sintomas concretos do dano:**
- Cliente A1 (saudável, 26 anos) recebe email A, lê "comparação com mercado", pensa "aaah o Wagner agora vai vender meu dado", responde frio ou silencia
- Vira boca-a-boca negativo no setor (gráficas se conhecem em SP/PR — Capterra-comm-visual mapeado em `reference_concorrentes_com_visual.md`)
- Reclame Aqui ganha post "WR Sistemas mudou, agora é só venda" — destrói brand equity de 26 anos em 1 semana

**Mitigação (Tier 0 — não-negociável):**

1. **Email Versão A abre com humano, não com produto.** Primeiras 2 frases falam de relação, não de feature. Já está assim no draft acima — não inverter ordem em revisão.

2. **Nível 3 (DaaS externo) NUNCA é default.** Sempre opt-in explícito + tela mostra exatamente o que sai do dado dele + quanto ele recebe de volta. Skill `multi-tenant-patterns` Tier A + ADR 0093 já cobrem isolamento técnico — comunicação tem que refletir isso em palavras.

3. **Wagner faz call pessoal nos top 3 saudáveis** (Semana 1 dia 6-7). Não delegar pra Maiara/Felipe/Luiz. 26 anos = voz Wagner.

4. **Se algum cliente reagir mal**, Wagner responde pessoalmente em <24h, oferece reverter sem registro, e atualiza este doc com lesson learned.

5. **Auditar tom em revisão dupla:** antes de disparar, Wagner + Eliana[E] leem cada versão em voz alta perguntando "isso soa como amigo de 26 anos ou como vendedor de SaaS?". Se segundo, reescreve.

---

## Tom global (checklist final pré-disparo)

- [x] Wagner é dono, fala em 1ª pessoa ("eu lancei", "eu não cobro", "tô ligando")
- [x] Honesto sobre evolução de produto ("fase 1 de validação", "ainda calibrando preço")
- [x] Nunca minimiza histórico (cada versão menciona "26 anos" ou "tempão" ou "parceria")
- [x] Sempre oferece saída ("pode sair quando quiser", "pode ignorar", "sem pressão")
- [x] PT-BR brasileiro coloquial ("rapidinho", "topa?", "poxa")
- [x] Sem firula corporativa ("sinergias", "leveraging", "best-in-class") — proibidos
- [x] CTA único e específico por canal (não 3 CTAs no mesmo email)
- [x] Opt-out trivial visível em email (1 clique)

---

**Próximos passos:**
1. Wagner + Eliana[E] revisam lista nominal (planilha local) — mapping Cliente_NN ↔ razão_social
2. Wagner aprova drafts (este doc) ou pede revisão
3. Tasks-create no MCP: `tasks-create title:"Disparar email Versão A — 8 saudáveis" cycle:current owner:W`
4. Configurar WhatsApp Business API (número dedicado) se ainda não estiver pronto
5. Configurar token opt-in URL (`/insights/opt-in?c=[token]`) — backend Modules/Insights
6. Deploy: ver `memory/decisions/proposals/PRODUTO-OIMPRESSO-INSIGHTS-MASTER-SPEC.md` US relacionada

**Refs:** ADR 0093 (multi-tenant Tier 0), `proposals/PRODUTO-OIMPRESSO-INSIGHTS-MASTER-SPEC.md`, `reference_clientes_ativos.md`, `cliente_rotalivre.md` (não-cliente alvo aqui mas referência de tom Wagner-Larissa).
