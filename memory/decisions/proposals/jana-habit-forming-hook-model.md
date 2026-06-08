# Jana Habit-Forming — Hook Model + Behavioral Patterns — 2026-05-09

> Status: PROPOSAL (não-canon, não-ADR). Discussão estratégica.
> Autor: Claude (a pedido de Wagner)
> Stack base: Modules/Copiloto (Jana IA) + Modules/Financeiro + WhatsApp Bot (planned)

---

## Premissa

Cliente que abre Jana **1x/sem** cancela em 6m (curiosidade não vira hábito, hábito não vira ROI percebido, ROI não percebido = churn).
Cliente que abre Jana **5x/sem** vira cliente vitalício e paga 3x mais — porque ela passa a ser **o lugar onde ele pensa o negócio**, não uma feature do ERP.

**Tese:** o ERP entrega *operação*. Jana entrega *consciência operacional*. Consciência diária = hábito = LTV alto.

**Ético, não predatório:** habit-forming aqui é "lembrar o cliente do dinheiro dele que está vazando". Se Jana parar de entregar valor real, o cliente percebe e sai. Não há lock-in artificial. Lock-in vem de **memória acumulada do negócio dele que ele perde se sair** — que é honesto.

---

## Mapa do Hook em Jana (visão macro)

```
TRIGGER          ACTION              VARIABLE REWARD          INVESTMENT
(externo→int)    (low friction)      (tribo/caça/self)        (sunk cost)
─────────────    ───────────────     ─────────────────────    ──────────────────
Push 8h sexta    1-tap "ver mais"    "achei R$ [redacted Tier 0]k margem"    Meta cadastrada
WhatsApp alerta  Voz no carro        "vc top 20% setor"       Categoria custom
Email semanal    Reply curto         "+14% vs mês passado"    "Ensina Jana"
                                                              Streak 30d
```

---

## 12 features Jana habit-forming

### Feature 1: Boa Sexta (Daily Trigger)

- **Hook step:** Trigger externo → vira interno após 3-4 semanas
- **Mockup (WhatsApp 8h sex):**
  > "Bom dia Larissa! Resumo da semana ROTA LIVRE:
  > Faturamento R$ [redacted Tier 0]k (+12% vs semana passada)
  > 1 alerta: cliente Y atrasou R$ [redacted Tier 0]k há 5d
  > 1 oportunidade: margem subiu 3pp em banner — replicar?
  > [Ver detalhes] [Ignorar essa semana]"
- **Variable reward:** conteúdo muda toda semana (caça + self)
- **Investment:** clique "Ver detalhes" salva preferência → próximo push é mais relevante
- **Métrica de sucesso:** tap-rate em 6h pós-push > 25%; streak de 4 sextas seguidas > 60% dos clientes ativos
- **Esforço:** 4 semanas (cron + WhatsApp template + ContextSnapshotService já existe + Brain A gera resumo)
- **Por que vira hábito:** sexta 8h = ritual cognitivo do empresário ("como foi minha semana?"). Jana ocupa esse slot mental. Após 4 semanas o cliente *espera* a mensagem.

---

### Feature 2: Alerta Inadimplência (Reactive Trigger)

- **Hook step:** Trigger externo (event-driven) — disparado por evento real no Financeiro
- **Mockup (WhatsApp tempo real):**
  > "Larissa, cliente Tinta Forte vence hoje R$ [redacted Tier 0]k e não pagou.
  > Histórico: paga sempre +3d depois do venc. Quer que eu mande régua?
  > [Sim, mandar] [Esperar +2d] [Ignorar esse cliente]"
- **Variable reward:** caça (recuperar dinheiro real) + self (sentir-se no controle)
- **Investment:** "Ignorar esse cliente" treina Jana sobre quem é confiável
- **Métrica:** % vencimentos que cliente toma ação <24h via Jana > 40%
- **Esforço:** 3 semanas (Listener `ContaReceberVencida` → Job → WhatsApp + 2 botões)
- **Por que vira hábito:** dor real (dinheiro que ia ficar perdido) → Jana = "salvador". Forte vínculo emocional.

---

### Feature 3: Voz no Carro (Action — friction zero)

- **Hook step:** Action minimum-friction
- **Mockup:** cliente dirigindo às 7h: "Jana, quanto fechei essa semana?" → resposta áudio 8s "Você fechou R$ [redacted Tier 0]k, 12% acima da semana passada, com 1 cliente atrasado de R$ [redacted Tier 0]k".
- **Variable reward:** self (sentir-se "no comando" do negócio mesmo dirigindo)
- **Investment:** após N usos voz, Jana aprende vocabulário do cliente ("fechei" = faturei; "tô no zero" = sem caixa)
- **Métrica:** % clientes com >=3 interações voz/sem; latência resposta <4s
- **Esforço:** 6 semanas (WhatsApp Voice in/out já suporta; Whisper STT; Brain A gera + TTS Polly/ElevenLabs)
- **Por que vira hábito:** zero atrito = compatível com vida real do empresário (carro, oficina, obra). Mata o argumento "não tive tempo de abrir o sistema".

---

### Feature 4: Achado da Semana (Caça — variable reward forte)

- **Hook step:** Variable reward (caça) + Trigger
- **Mockup (push qua 17h):**
  > "Achei R$ [redacted Tier 0]k em margem que você está deixando na mesa: lonas 3x4 estão saindo 18% abaixo do mercado.
  > Justificável (cliente fiel) ou ajustar?
  > [Ver clientes] [Ajustar tabela] [É de propósito]"
- **Variable reward:** caça pura — cliente vê dinheiro que não sabia existir
- **Investment:** "É de propósito" treina Jana sobre exceções (cliente XPTO sempre 18% off porque é amigo)
- **Métrica:** % achados que viram ação (ajuste/justificativa) > 35%; receita incremental atribuída a achado > R$ [redacted Tier 0]k/m por cliente médio
- **Esforço:** 8 semanas (queries analíticas + comparativo intra-cliente histórico + thresholds + sem comparar com concorrentes ainda)
- **Por que vira hábito:** dopamina pura (achei dinheiro!). Mesmo padrão que faz cliente abrir extrato bancário 3x/dia.

---

### Feature 5: Comparativo Anônimo Setor (Tribo)

- **Hook step:** Variable reward (tribo)
- **Mockup:**
  > "Você está acima da média do setor de comunicação visual em ticket médio (R$ [redacted Tier 0] vs R$ [redacted Tier 0]) mas abaixo em recompra 90d (28% vs 41%).
  > Quer ver onde os top 20% se diferenciam?"
- **Variable reward:** tribo + self
- **Investment:** quanto mais cliente usa Jana, mais o benchmark fica preciso (mais dados anônimos contribuídos)
- **Métrica:** opt-in benchmark anônimo > 60% (pré-requisito legal — LGPD); engagement em mensagens com comparativo +40% vs sem
- **Esforço:** 12 semanas (precisa massa crítica >= 30 clientes setor + agregação anonimizada + LGPD review + ADR de governança)
- **Por que vira hábito:** curiosidade competitiva é primal. Mesmo padrão Strava (kudos), Apple Health (rings).
- **Risco:** se mostrar comparativo desfavorável agressivo, deprime e queima trust. **Sempre 1 ponto fraco + 1 ponto forte.**

---

### Feature 6: Meta Mensal Vivendo (Investment + Trigger)

- **Hook step:** Investment (cliente cadastra meta) → vira Trigger interno
- **Mockup (cliente cadastra meta R$ [redacted Tier 0]k/mês no início):**
  > Push dia 20: "Larissa, faltam R$ [redacted Tier 0]k pra bater R$ [redacted Tier 0]k. Em ritmo atual: 87% (R$ [redacted Tier 0]k projetado). 3 caminhos possíveis: [..]"
- **Variable reward:** self (progresso)
- **Investment:** meta cadastrada = sunk cost; cliente revisa → ajusta → cria identidade ("eu sou aquele que bate meta")
- **Métrica:** % clientes com meta cadastrada > 70%; correlação meta-cadastrada × retention 90d
- **Esforço:** 2 semanas (UI cadastro + ContextoNegocio já tem faturamento + cron progress)
- **Por que vira hábito:** identidade. Ninguém quer ver a meta dele falhar. Jana vira "lembrete cognitivo do compromisso comigo mesmo".

---

### Feature 7: Streak de Decisões (Gamification ética)

- **Hook step:** Investment + Variable reward (self)
- **Mockup:**
  > "Você tomou 1 ação por semana com Jana há 12 semanas seguidas.
  > Top 8% dos usuários. Continue."
- **Variable reward:** self (orgulho)
- **Investment:** streak = sunk cost emocional (não querer quebrar)
- **Métrica:** % usuários com streak >= 4 semanas; correlação streak × LTV
- **Esforço:** 2 semanas (counter + threshold de "ação tomada" = clicou em algum CTA Jana na semana)
- **Por que vira hábito:** Duolingo provou que isso funciona em escala global.
- **Risco ético:** **NÃO usar guilt** ("você quebrou seu streak, que pena!"). Apenas celebrar quando tem. Streak quebra = silêncio. Jana volta na próxima sexta normal.

---

### Feature 8: "Ensina Jana" (Investment puro)

- **Hook step:** Investment
- **Mockup:**
  > "Toda terça você fatura mais. Por quê?
  > [Cliente recorrente XPTO entrega] [Promoção] [Não sei] [Outro: ___]"
- **Variable reward:** self (sentir-se ouvido pela IA)
- **Investment:** cada resposta enriquece memória persistente do cliente; quanto mais ensina, mais útil Jana fica → impossível trocar (memória = lock-in honesto)
- **Métrica:** # facts/usuário em copiloto_memoria_facts > 50 ao final de 90d; quality de respostas Jana sobe N pontos
- **Esforço:** 3 semanas (UI quick-reply + storage em Memoria + recall hybrid já existe)
- **Por que vira hábito:** efeito IKEA — cliente valoriza o que ajudou a construir. "Minha Jana sabe coisas que nenhuma outra IA sabe sobre meu negócio."
- **Lock-in honesto:** se sair, perde toda essa memória. **Não escondemos isso — é a feature.**

---

### Feature 9: Resumo Sonoro 18h (Daily review)

- **Hook step:** Trigger externo + Action zero-friction
- **Mockup (WhatsApp Voice 18h):**
  > Áudio 20s: "Bom fim de tarde. Hoje você faturou R$ [redacted Tier 0]k, gastou R$ [redacted Tier 0]k, ficou R$ [redacted Tier 0]k positivo. 1 cliente novo entrou. Amanhã tem 3 OS pra entregar. Boa noite."
- **Variable reward:** self (fechamento mental do dia)
- **Investment:** cliente pode personalizar horário, tom, conteúdo → mais setup, mais sunk cost
- **Métrica:** open-rate áudio 18h > 50%; correlação com retention 60d
- **Esforço:** 4 semanas (mesma stack voz feature 3 + cron 18h + script narrativo Brain A)
- **Por que vira hábito:** Strava daily review provou que recap é vício. Empresário ouve enquanto fecha loja.

---

### Feature 10: Resumo Mensal Surpresa (Spotify Wrapped style)

- **Hook step:** Variable reward (self + caça)
- **Mockup (1º dia do mês, push):**
  > "Seu abril em números:
  > R$ [redacted Tier 0]k faturado (mês recorde 2026)
  > Cliente que mais cresceu: Tinta Forte (+47%)
  > Produto estrela: lona 3x4 (28% do faturamento)
  > Sua palavra mais dita: 'corre' (47x)
  > [Ver tudo] [Compartilhar]"
- **Variable reward:** self forte (orgulho narrativo) + tribo (compartilhar = vira marketing orgânico)
- **Investment:** ano todo de uso = wrapped anual virá e cliente sabe disso
- **Métrica:** open-rate mensal > 80%; share-rate > 15%
- **Esforço:** 6 semanas (analytics + template visual + image generation pra share)
- **Por que vira hábito:** Spotify mostrou que isso vira evento cultural anual. Empresário compartilha no grupo de WhatsApp dos amigos = aquisição.

---

### Feature 11: Categoria Custom (Investment ajusta produto)

- **Hook step:** Investment
- **Mockup:**
  > "Você marcou esta despesa como 'Material'. Pra que tipo de material?
  > [Tinta] [Lona] [Banner] [Outro: ___]"
- **Variable reward:** caça futura (próxima Jana sabe categorizar sozinha → menos trabalho)
- **Investment:** categorias custom enriquecem schema do cliente; trocar de ferramenta = recriar tudo
- **Métrica:** # categorias custom criadas/cliente > 8 ao final 90d
- **Esforço:** 2 semanas (input + storage em copiloto_memoria_facts namespace `category_taxonomy`)
- **Por que vira hábito:** mais cliente investe, mais produto melhora pra ele = positive feedback loop.

---

### Feature 12: Pergunta-Catalisadora 7h (Trigger interno emergente)

- **Hook step:** Trigger interno (após 4 sem de uso) — emergência do hábito
- **Mockup:** depois de 4 semanas com Jana, cliente acorda e pensa "como fechei ontem?" → abre WhatsApp → Jana já está com mensagem proativa: "Bom dia Larissa! Ontem você fechou R$ [redacted Tier 0]k. Hoje tem 4 OS na agenda."
- **Variable reward:** self (controle) + caça (descoberta do dia)
- **Investment:** Jana só manda a 7h se cliente respondeu ao trigger sexta nas 4 semanas anteriores — feature ganha por mérito, não dada de bandeja
- **Métrica:** % clientes com Jana proativa 7h ativada >= 60% após mês 2; DAU sobe pra 80%+
- **Esforço:** 3 semanas (combinação features 1+2+3+9)
- **Por que vira hábito:** *aqui está o ponto de virada* — quando cliente sente desconforto se Jana não falar de manhã. Trigger virou interno. **Hook fechado.**

---

## Sequência de adoção (cliente novo)

| Fase | Janela | O que acontece | Métrica gate |
|------|--------|----------------|--------------|
| **D1 (Activation)** | 1º dia | Onboarding Jana 90s: cliente faz 1ª pergunta ("quanto faturei semana passada?") e recebe resposta com dados reais dele | TTM (time-to-magic-moment) < 3min |
| **D2-7 (Discovery)** | semana 1 | Jana faz 3-5 perguntas de "Ensina Jana" sobre o negócio (Feature 8) | >= 3 facts cadastrados |
| **W2 (Habits begin)** | semana 2 | Push diário começa (Feature 9) + Boa Sexta (Feature 1) | Open-rate >= 30% |
| **W3-4 (Streak forms)** | semana 3-4 | Streak começa (Feature 7); cliente cadastra meta (Feature 6) | Meta cadastrada >= 70% |
| **M2 (Loop locks)** | mês 2 | Achado da Semana (Feature 4) + comparativo setor (Feature 5) — variable reward forte | DAU/MAU > 30% |
| **M3 (Habit emerges)** | mês 3 | Trigger interno aparece (Feature 12) — cliente abre Jana sem precisar de push | DAU/MAU > 50% |
| **M6 (Tier upgrade)** | mês 6 | Voice (Feature 3) + Wrapped (Feature 10) desbloqueiam — cliente migra Starter→Pro naturalmente | % conversão Starter→Pro > 40% |

---

## Métricas de sucesso (engagement + financeiro)

### Engagement (90d post-launch)
- **DAU/MAU > 50%** (proxy de hábito formado)
- **Retention 30d > 75%** (vs benchmark SaaS B2B 40-60%)
- **Avg messages/user/day > 3**
- **Tap-rate notificação > 20%** (sem virar spam)
- **Streak médio > 7d**
- **NPS > 40**

### Financeiro (180d post-launch)
- **% clientes Starter→Pro > 40%** (escala natural por uso)
- **Churn mensal < 3%** (vs ~7% SaaS B2B baseline)
- **LTV/CAC > 4** (era ~2 antes do habit-forming)
- **% clientes "viciados" (DAU/MAU>70%) que abrem ticket suporte** = canary de qualidade — devem abrir MENOS, não mais

---

## Pricing tier baseado em uso (não só features)

| Tier | Preço/mês | Limites engagement | Targeting |
|------|-----------|---------------------|-----------|
| **Starter** | R$ [redacted Tier 0] | 200 queries, 1 push/dia, sem streak, sem voz | Cliente novo / dúvida / casual |
| **Pro** | R$ [redacted Tier 0] | 1.000 queries, 3 push/dia, streak, achado semanal | Cliente engajado pós-mês 3 |
| **Premium** | R$ [redacted Tier 0] | Ilimitado, voz in/out, multi-business, comparativo setor | Multi-loja / power user |
| **Enterprise** | R$ [redacted Tier 0] | + API, prioridade Brain B (Sonnet), 1:1 onboarding, SLA | Operações > R$ [redacted Tier 0]k/mês |

> **Insight pricing:** o gate certo não é "feature X bloqueada", é "uso intenso paga por si só". Cliente Starter que faz 800 queries/m vai upgradear naturalmente — Jana se paga.

---

## Riscos da habit-formation

### Risco 1: Notification fatigue (queima trust em 4 semanas)
**Mitigação:** **máximo 1 push/dia + 1 alerta event-driven**. Cliente pode ajustar pra 0 se quiser. Jana **NUNCA** manda lembrete de "venha me usar".

### Risco 2: Habit-forming predatório (cliente refém vs. cliente fã)
**Linha vermelha:** Jana só vicia se entregar valor. No dia que parar de entregar, cliente sai. **A diferença é honestidade.** Não escondemos lock-in (memória); celebramos como feature.

### Risco 3: Insights vagos viram ruído
**Mitigação:** todo push tem **1 número + 1 ação clara + 1 escape**. Sem "você está bem!". Sem "continue assim!". Sempre algo concreto.

### Risco 4: Comparativo setor agressivo deprime
**Mitigação:** 1 ponto fraco **junto com** 1 ponto forte. Sempre. E sempre acionável.

### Risco 5: Cliente percebe manipulação
**Mitigação:** **transparência radical**. Em onboarding: "Jana vai te mandar 1 mensagem por dia. Pode desligar quando quiser. Quanto mais você ensina, melhor ela fica. Se sair, você leva a memória." (LGPD-friendly + ético).

---

## Anti-padrões éticos (não fazer)

- ❌ **FOMO falso** ("seus dados expiram em 24h!", "oferta única!", "outros 4 estão vendo")
- ❌ **Guilt streak** ("você não usa Jana há 7 dias 😢", "Jana sentiu sua falta")
- ❌ **Esconder valor real atrás de gamificação** (achievement vazio, badge inútil)
- ❌ **Push spam** (>1 push/dia sem evento real)
- ❌ **Comparativo desfavorável agressivo** ("você está pior que 80% do setor")
- ❌ **Lock-in artificial** (não exportar dados, não permitir delete LGPD)
- ❌ **Dark pattern de cancelamento** (cancelar via SAC, etc) — *cancelamento sempre 1-clique*
- ❌ **Insights inventados** (Brain A alucinar dado que não existe pra parecer útil)

> **Princípio guia:** "se eu fosse a Larissa e descobrisse o truque, eu acharia justo?". Se não, não faz.

---

## Esforço total + sequenciamento

**Fase 1 (8 semanas — MVP habit loop):**
- Feature 1 (Boa Sexta) — 4 sem
- Feature 6 (Meta vivendo) — 2 sem
- Feature 8 (Ensina Jana) — 3 sem (paralelo)
- Feature 9 (Resumo 18h texto) — 2 sem (paralelo)

**Fase 2 (8 semanas — variable reward forte):**
- Feature 2 (Alerta inadimplência) — 3 sem
- Feature 4 (Achado da semana) — 8 sem
- Feature 7 (Streak) — 2 sem (paralelo)
- Feature 11 (Categoria custom) — 2 sem (paralelo)

**Fase 3 (12 semanas — escala + premium):**
- Feature 3 (Voz) — 6 sem
- Feature 9 (Resumo 18h voz) — 4 sem
- Feature 10 (Wrapped mensal) — 6 sem (paralelo)
- Feature 5 (Comparativo setor) — 12 sem (gating: massa crítica)
- Feature 12 (Trigger interno 7h) — 3 sem (combina anteriores)

**Total:** ~28 semanas (7 meses) pra suite completa.
**Quick win:** Fase 1 entrega DAU/MAU >30% e churn -2pp em 8 semanas.

---

## Conexão com canon existente

- **Modules/Copiloto** — Memoria persistente + recall hybrid já dão Feature 8 + 11 quase de graça
- **Modules/Financeiro** — Feature 2 (inadimplência), 4 (achado margem), 6 (meta) batem direto
- **WhatsApp Bot** — pré-requisito Fase 1 (não existe canon ainda — gerar ADR proposta separada)
- **Brain A (gpt-4o-mini)** — gera resumos textuais (Features 1, 9, 10) sem custar
- **Brain B (Sonnet)** — premium tier pra análises complexas (Feature 4 escalonado)
- **ContextSnapshotService** — alimenta todas features data-driven
- **ADR 0091 (Daily Brief)** — protótipo interno do mesmo padrão; replicar pro cliente
- **ADR 0094 (Constituição v2)** — princípio "loop fechado por métrica" justifica esse roadmap

---

## Próximo passo

1. Wagner valida tese (vale priorizar habit-forming agora vs. features funcionais novas?)
2. Se sim → criar ADR canon "Jana Habit-Forming Strategy" (supersedes nada; adiciona pillar)
3. Criar US no MCP pras 4 features Fase 1 (`tasks-create` no cycle apropriado)
4. Smoke MVP em ROTA LIVRE (biz=4, Larissa) — ela é o cliente perfeito de teste (já é heavy user, dá feedback honesto)
5. Medir 8 semanas → decidir Fase 2

---

**Última atualização:** 2026-05-09
**Autor:** Claude (proposal — aguarda Wagner aprovar pra virar ADR)
