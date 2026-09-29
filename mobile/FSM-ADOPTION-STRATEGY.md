# FSM-ADOPTION-STRATEGY.md — Como tornar o Oimpresso indispensável

> **Protocolo FSM aplicado**: combinação do **Fogg Behavior Model** (BJ Fogg, Stanford — `B = MAP`: Behavior = Motivação × Ability × Prompt) com **Field Service Management** (padrões industriais usados por ServiceNow, Salesforce FSM, AutoLeap, Shopmonkey).
>
> Objetivo: fazer mecânicos e operadores de produção **escolherem o app** todo dia — não por obrigação, mas porque o trabalho fica mais fácil sem ele.

---

## 0. Premissas (diagnóstico)

### Quem são os usuários reais
- **Mecânico de bancada** (40-55 anos, mão graxenta, celular pessoal, óculos em cima da cabeça). Vive em movimento, não vai parar 5 min pra preencher formulário.
- **Operador de produção CV** (impressor, plotterista, acabamento — 25-45 anos). Trabalha em fluxo, precisa de status visual rápido e zero atrito.
- **Estoquista** (qualquer idade, foco em precisão). Quer escanear, conferir e seguir.

### O que eles odeiam em sistema
1. Digitar texto longo
2. Procurar menu (>3 cliques pra ação principal)
3. Travar com erro técnico no meio do serviço
4. Ser obrigado a usar quando o "papel funcionava"
5. Sentir que o sistema serve "o gerente lá em cima", não eles

### Lei zero
> **Se o app exige mais esforço que NÃO usar, ele perde.**

Não competimos com outros apps. Competimos com **post-it amarelo + whatsapp do gerente + caderno**. Pra vencer o caderno, precisamos ser **mais rápido que ele**.

---

## 1. Fogg Behavior Model — B = M·A·P

> **Behavior = Motivation × Ability × Prompt**
>
> Para acontecer, os 3 precisam coincidir no mesmo momento. Falha em qualquer um, comportamento NÃO acontece.

### Eixo A — **Ability** (facilidade) — *o maior alavancador*

Fogg sugere: **simplificar mais é mais eficaz que motivar mais**. As 6 dimensões da Ability (simplificar):

| Dimensão | Como aplicar no app |
|---|---|
| **Tempo** | Toda ação de chão de fábrica em ≤3 toques. Avançar OP = 1 toque. |
| **Esforço físico** | Botões grandes (mínimo 56dp), uma mão. Sem teclado nas ações primárias. |
| **Esforço mental** | Decisões binárias (verde/vermelho), nunca textão. Defaults inteligentes (mecânico só clica "OK"). |
| **Ciclos sociais** | Não precisa pedir pra ninguém. Mecânico nunca depende de gerente pra liberar ação rotineira. |
| **Dinheiro** | Plano grátis pro operador (só dono paga). Operador não percebe o custo, só o benefício. |
| **Rotina** | Cabe no fluxo existente. Não obriga novo ritual; encaixa no que ele já faz. |

**Anti-padrão atual**: nosso modal de produto tem 6 abas. Mecânico vai abandonar na primeira. Fix: tela de execução pra mecânico tem **3 botões**: ✅ Pronto · 🔴 Problema · 📷 Foto.

### Eixo M — **Motivation** (motivação)

3 motivadores principais (Fogg):

| Motivador | Aplicação |
|---|---|
| **Sensation** (prazer/dor) | Foto antes/depois (orgulho do trabalho). Som suave quando OP avança. Toque háptico ao concluir. Pequenas vitórias visíveis. |
| **Anticipation** (esperança/medo) | "Cliente está esperando aprovar arte. Avise quando estiver pronto." (transparência) + "Sua OS está há 2 dias sem update — atualize antes do gerente perguntar." (constructive nudge) |
| **Belonging** (aceitação/rejeição) | Ranking discreto de mais rápidos no mês. Avatar do mecânico no card da OS — ele é DONO daquilo. |

### Eixo P — **Prompt** (gatilho)

Três tipos:

1. **Sparks** — pra quem TEM ability mas FALTA motivação. Ex: "Seu cliente João aprovou a arte às 14h. Hora de imprimir!" (notificação)
2. **Facilitators** — pra quem TEM motivação mas FALTA ability. Ex: tutorial inline na primeira vez que usa o scanner GTIN.
3. **Signals** — pra quem TEM ambos, só precisa do lembrete. Ex: badge vermelho na aba Tarefas com count.

**Regra do Fogg**: nunca dispare prompt sem antes garantir que ability+motivation já existem. Spam de notificação destrói adoção.

---

## 2. Hook Model (Nir Eyal) — Como criar hábito

> Trigger → Action → Variable Reward → Investment → (loop)

Para criar o ciclo:

### Trigger
- **Externo (semana 1-2)**: notificação push em horário do turno ("Bom dia. Você tem 3 OPs hoje").
- **Interno (mês 2+)**: usuário pega o celular **antes** da notificação porque desenvolveu o hábito ("vou ver minhas OSs do dia"). Esse é o momento de vitória — o app virou parte do ritual matinal.

### Action
- A **menor unidade de comportamento** possível. Não "preencher formulário", mas **"tocar Pronto"**. Não "criar checklist DVI", mas **"foto 1 → próximo → foto 2..."** com swipe.

### Variable Reward
> Reward previsível mata o hábito. Reward variável vicia (princípio do caça-níquel, mas usado pro bem).

Variáveis sugeridas:
- **Reward of the Tribe** — like/elogio do cliente automático ("Cliente João adorou as fotos da entrega!")
- **Reward of the Hunt** — encontrar a OS certa por escanear placa em 2 segundos é gratificante toda vez. Pequena dopamina.
- **Reward of the Self** — completion bar do dia ("8 de 10 OPs hoje 🔥"). Streak de dias com 100% concluído.

### Investment
- Sobe foto → vira "patrimônio dele" no histórico. Quanto mais investe, menos disposto a abandonar.
- Vai criando vocabulário próprio (templates de queixa, atalhos favoritos). Switching cost cresce.
- Histórico do veículo do cliente Maria mostra "última troca de óleo: você mesmo, há 18 meses" → mecânico se reconhece, sente protagonismo.

---

## 3. Field Service Management — Padrões da indústria

Estudei como AutoLeap, Shopmonkey, Tekmetric (mecânica) e Printavo, shopVOX (CV) ganharam tração com chão-de-fábrica. Padrões comuns:

### 3.1 Início do turno — "Meu dia em 5 segundos"
Ao abrir o app pela manhã, mecânico vê **uma única tela** com:
- Lista linear de OSs do dia (não kanban — kanban é pro gerente)
- Ordenada por prioridade automática (urgentes em cima)
- Cor verde/amarelo/vermelho por prazo
- Toque na OS = abrir execução direto

Anti-padrão: pedir pra escolher empresa, idioma, papel toda vez. **Login Persistente + tela do dia em <2 segundos**.

### 3.2 Captura em fluxo — "Tudo é foto/voz, nada é texto"
- **Câmera onipresente**: botão `📷` flutuante em toda tela de OS/OP. Foto categoriza automaticamente (entrada se status=recepção, durante se em execução, saída se pronto).
- **Voz para nota**: tap-and-hold pra ditar queixa/diagnóstico. Whisper transcreve. Mecânico não digita.
- **Scanner**: peça/produto/placa/QR — câmera, não teclado.
- **Templates rápidos**: "Troca de óleo + filtro 10W40 — R$ 220" como botão único.

### 3.3 Status como ação física — "Slide pra avançar"
- Avançar OP: **swipe horizontal** no card (gesture nativo + haptic). Não menu suspenso.
- Marcar peça como "instalada": tap longo no item.
- Foto vira "evidência" imediata: o botão "marcar como pronto" pede foto antes (forcer constructive).

### 3.4 Visibilidade do impacto — "Cliente já viu"
- Após enviar foto via WhatsApp, mostra status real:
  - `⌛ Enviado` → `✅ Entregue` → `👁 Visualizado` → `🗨️ Cliente respondeu`
- Mecânico VÊ que o trabalho dele tem reflexo imediato no cliente. Cria orgulho.

### 3.5 Modo offline robusto — "Não me faz esperar"
- Toda ação funciona offline (fila local). Sync silencioso quando conectar.
- Indicador discreto "trabalhando offline · 3 ações na fila". Sem dialog modal.

### 3.6 Atalhos pessoais — "Aprende do meu jeito"
- O sistema observa quais OSs ele abre, quais peças usa, qual a frequência. Em 1 semana sugere:
  - "Você executa muito troca de óleo. Quer template?"
  - "Mecânico João do turno tarde. Atalho pra OSs do João?"

---

## 4. Aha Moments por jornada

### Mecânico (semana 1)
- **Dia 1**: abre OS por placa em 2 segundos via scanner. "Pô, mais rápido que perguntar pro gerente."
- **Dia 3**: termina OS, tira 3 fotos, envia ao cliente em 10 segundos. Cliente responde "que rápido!". Mecânico mostra pro colega.
- **Dia 7**: vê histórico do carro do Sr. José — "esse cara veio aqui 4 vezes esse ano, sempre eu". Sente protagonismo.
- **Dia 14**: pega o celular ANTES da notificação. Hábito instalado.

### Operador de produção CV (semana 1)
- **Dia 1**: ao chegar, vê fila do dia ordenada. Não precisa perguntar "o que faço primeiro?".
- **Dia 2**: marca OP como concluída via swipe + 1 foto. Cliente recebe foto via WhatsApp automaticamente. Cliente elogia.
- **Dia 4**: sobe foto da prova durante impressão, cliente aprova pelo link público no portal — sem ligação telefônica.
- **Dia 10**: vê % do dia concluído em tempo real. Competição saudável com colegas (ranking opcional).

### Estoquista (semana 1)
- **Dia 1**: faz inventário escaneando códigos de barras. 1 hora em vez de 4.
- **Dia 3**: configura alerta de estoque mínimo. Sistema avisa antes de faltar — ele "previu" o problema, vira herói pro gerente.
- **Dia 5**: gera etiqueta com QR direto do app pra colar na prateleira.

---

## 5. Features que criam dependência (priorizadas)

### Nível 1 — Foundation de adoção (sem isso, abandonam)

| # | Feature | Impacto | Esforço |
|---|---|---|---|
| 1.1 | **Scanner GTIN/QR/placa** universal | 🔥🔥🔥 | M |
| 1.2 | **Voz → texto** em campos longos (queixa, diagnóstico) | 🔥🔥🔥 | P |
| 1.3 | **Câmera onipresente** com categorização automática | 🔥🔥🔥 | P |
| 1.4 | **Swipe para avançar** OP/OS (gesture + haptic + som) | 🔥🔥 | P |
| 1.5 | **Tela do dia** personalizada por papel ao abrir | 🔥🔥🔥 | M |
| 1.6 | **Login persistente** (biometria + token longo) | 🔥🔥 | P |
| 1.7 | **Offline-first robusto** com sync silencioso | 🔥🔥 | já 80% feito |

### Nível 2 — Geram orgulho/protagonismo

| # | Feature | Impacto | Esforço |
|---|---|---|---|
| 2.1 | **Histórico do cliente/veículo** rico (mostra "última vez você atendeu") | 🔥🔥 | M |
| 2.2 | **Foto antes/depois** automática lado a lado + envio WhatsApp 1 clique | 🔥🔥🔥 | M |
| 2.3 | **Confirmação visual** quando cliente lê/responde | 🔥🔥 | M (Z-API webhook) |
| 2.4 | **Streak diário** ("12 dias 100% concluído") + medalha discreta | 🔥 | M |
| 2.5 | **Avatar nos cards** ("essa OS é minha") | 🔥 | P |

### Nível 3 — Variable reward / dopamina

| # | Feature | Impacto | Esforço |
|---|---|---|---|
| 3.1 | **Ranking semanal** opt-in (mais rápido, mais OS no prazo, sem retrabalho) | 🔥🔥 | M |
| 3.2 | **Achievements**: "Primeiro DVI", "10 OSs sem retrabalho", "Pé na estrada" (uso fora do horário comum) | 🔥 | M |
| 3.3 | **Som + haptic** em conclusão (configurável, brincar com sound design) | 🔥 | P |
| 3.4 | **Reação do cliente** mostrada no card (👍 quando aprovou, 💬 quando comentou) | 🔥🔥 | M |
| 3.5 | **Surprise & delight**: na primeira vez que faz X, um confete sutil | 🔥 | P |

### Nível 4 — Investment (switching cost)

| # | Feature | Impacto | Esforço |
|---|---|---|---|
| 4.1 | **Atalhos personalizados** ("meus templates de queixa") | 🔥🔥 | M |
| 4.2 | **Notas pessoais** no veículo/cliente (só você vê) | 🔥 | P |
| 4.3 | **Histórico pessoal**: "minhas 100 OSs do mês — top 5 problemas atendidos" | 🔥🔥 | M |
| 4.4 | **Reputação interna**: badge "Especialista em Vauxhall" depois de N OSs do mesmo modelo | 🔥 | M |

---

## 6. Anti-padrões a evitar

### ❌ "Vou colocar uma tela de relatório bonita"
Mecânico não quer relatório. Ele quer **DOER MENOS**. Relatório é pro dono. Feature pro mecânico tem que **economizar tempo dele**, não dar visibilidade pro chefe (mesmo que isso seja benefício colateral).

### ❌ "Treinamento de 2 horas pra ensinar a usar"
Se precisa treinar mais de 5 minutos, perdeu. O design tem que ensinar sozinho. Onboarding inline (tooltip na primeira vez) > documentação.

### ❌ "Vou usar gamificação com pontos e níveis"
Funciona quando bem feito (Strava). Geralmente vira childish e ofende profissional de chão-de-fábrica que tem 40 anos de oficina. **Variable reward via reconhecimento social** (cliente elogiou, gerente viu) > pontos arbitrários.

### ❌ "Notificações constantes mantêm engajamento"
Falso. Vira ruído, app é silenciado, hábito morre. **Notificação só com sinal real**: cliente aprovou, peça chegou, prazo apertando. Frequência baixa, conteúdo alto.

### ❌ "Quanto mais features melhor"
Cada feature adicional dilui as core. Mecânico tem 6-10 ações que faz 90% do tempo. Otimize essas 10 ao máximo. O resto pode esperar.

### ❌ "Vou copiar o web no mobile"
Web é pro escritório. Mobile é pro piso. Mobile DELETA features que não fazem sentido na mão. Não escala interfaces, REDUZ.

### ❌ "Forçar uso bloqueando alternativas"
Não funciona com chão-de-fábrica. Eles vão usar caderno escondido. O app tem que **vencer competindo**, não bloqueando.

---

## 7. Métricas de adoção (KPIs)

### Métricas de uso (lagging)
- **DAU/MAU** por papel — meta: >70% pra mecânico/operador em 30 dias
- **Sessões por dia** por usuário — meta: >5 (significa hábito formado)
- **Time-to-first-completed-action** após login — meta: <8 segundos

### Métricas de comportamento (leading)
- **% de OSs com foto** — meta: >80%
- **% de avanços de status feitos via swipe** (vs. tap) — meta: >60% (mostra que aprendeu gesture)
- **% de uso de voz** em campos longos — meta: >40%
- **% de scanner usado** ao abrir OS — meta: >70%
- **Taxa de abandono no onboarding** — meta: <15%

### Métricas de retenção
- **W1 retention** (volta na semana 1) — meta: >80%
- **W4 retention** — meta: >60%
- **W12 retention** — meta: >50% (hábito instalado)

### Sinal de hábito instalado
> **Usuário abre o app ANTES de receber notificação push.** Esse é o momento de vitória definitivo.

Trackear via: ratio entre `app_open_organic` (sem push prévio em 5min) e `app_open_pushed` (depois de push). Quando organic ultrapassa 50%, hábito está formado.

---

## 8. Roadmap em 90 dias

### Mês 1 — Foundation de adoção (Sprints 1-4)
Foco: instalar os 7 itens do Nível 1. Sem isso, nada mais importa.
- Sprint 1: scanner universal + câmera onipresente
- Sprint 2: voz para texto em queixa/diagnóstico/observações
- Sprint 3: swipe-to-advance em OP/OS + haptic + som de confirmação
- Sprint 4: tela do dia personalizada + login persistente biométrico

### Mês 2 — Orgulho e protagonismo (Sprints 5-8)
Foco: Nível 2 + começar Nível 3.
- Sprint 5: histórico rico do cliente/veículo + foto antes/depois
- Sprint 6: webhook Z-API pra ler/respondido + reação visual no card
- Sprint 7: avatar do mecânico no card + atribuição clara de "minhas OSs"
- Sprint 8: streak diário + completion bar do turno

### Mês 3 — Investment e diferenciação (Sprints 9-12)
Foco: Nível 4 + polish.
- Sprint 9: atalhos personalizados + templates favoritos
- Sprint 10: notas pessoais no veículo/cliente + histórico pessoal
- Sprint 11: reputação interna ("especialista em X") + achievements sutis
- Sprint 12: medição de KPIs + ajustes baseados em uso real

### Mês 4+ — Iterar baseado em dados
Após 90 dias, métricas dirão onde investir. Provavelmente:
- Refinar onboarding com base em abandono real
- Adicionar features pedidas que NÃO emergiram da nossa pesquisa
- Polir interactions de maior fricção observada

---

## 9. Para mecânicos especificamente

### O que vai fazer eles dizerem "agora não trabalho sem"

1. **Buscar OS por placa em <2s via scanner** — substitui "ô gerente, qual a próxima?"
2. **Histórico do carro completo** — "esse Gol já veio aqui em 2024, problema de embreagem". Mecânico vira detetive, valoriza-se.
3. **Cliente vê foto antes/depois no WhatsApp automaticamente** — fim de "preciso passar lá pra ver" e ligações.
4. **Aprovação digital com assinatura** — não precisa mais imprimir orçamento, esperar cliente vir, papel sumindo na pilha.
5. **Histórico pessoal** — "eu atendi 87 OSs esse mês". Reconhecimento de PRODUÇÃO real, mesmo que gerente não veja.
6. **Voz pra digitar diagnóstico** — mecânico com mão graxenta não digita. Fala.
7. **Modo offline real** — oficina no fim do mundo com WiFi instável não atrapalha.

### O que vai fazer eles ODIAREM o app (e que NÃO vamos fazer)

1. ❌ Pedir cadastro complexo do cliente toda vez
2. ❌ Tela com 20 campos pra criar uma OS simples
3. ❌ Confirmação modal "tem certeza?" em ação rotineira
4. ❌ Animação chata que demora 800ms a cada tela
5. ❌ Erro técnico em inglês no meio do serviço
6. ❌ Forçar login a cada hora
7. ❌ Push notification a cada update interno do gerente

---

## 10. Para produção CV especificamente

### O que vai fazer eles dizerem "agora não trabalho sem"

1. **Fila do dia ordenada por prazo** — fim de "qual prioridade?". Operador SABE.
2. **Aprovação de arte por link público** — cliente aprova no celular dele, sem ligação. Tempo médio de aprovação cai de 4h pra 20min.
3. **Foto durante impressão** — operador sobe foto antes de cortar, cliente confirma cor/textura. Reimpressão por divergência → quase zero.
4. **Anexos da venda já aparecem na produção** — fim de "cadê a arte? o vendedor mandou?". Tudo já está lá quando OP cai.
5. **Status visível pro vendedor** — operador não é interrompido pra "como tá a OP 1247?". Vendedor olha sozinho.
6. **Histórico do trabalho dele** — "esse mês imprimi 340 m² de banner". Reconhecimento concreto.
7. **Câmera onipresente** — qualquer problema vira foto + comentário, vai direto pra discussão.

### Padrão de tela de execução

```
┌──────────────────────────────┐
│ ←  OP 1247 · João Silva     │
│                              │
│ [ARTE FINAL]                 │ ← thumb grande
│ banner-2x1m-frente.pdf  ⇄   │ ← swipe pra próxima arte
│                              │
│ ──────────────────────────── │
│                              │
│ STATUS    [Em produção  ↻]  │
│                              │
│ ITEM                  TIME   │
│ Banner 2x1m frente    00:42 │
│ Banner 2x1m verso    --:--  │ ← cronômetro em cada item
│                              │
│ ┌─────────┐  ┌──────────┐   │
│ │  📷     │  │  🎤       │   │ ← câmera + voz onipresente
│ │ Foto    │  │ Nota voz │   │
│ └─────────┘  └──────────┘   │
│                              │
│ ━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│ → Deslize pra próxima etapa  │ ← swipe pra avançar (gesture)
└──────────────────────────────┘
```

3 ações totais visíveis. Nenhum menu. Nenhum modal.

---

## 11. Estratégia de implementação

### Princípios de execução

1. **Lançar para 1 cliente de cada perfil**: 1 oficina mecânica + 1 gráfica CV. Observar uso real por 2 semanas antes de generalizar.
2. **Métricas desde o dia zero**: instrumentar tudo (PostHog, Mixpanel, ou simples eventos no `audit_log`). Sem dados, é opinião.
3. **Iteração semanal**: cada sexta, revisar métricas de adoção da semana, ajustar a próxima.
4. **Não pedir feedback em formulário** — usuário de chão-de-fábrica não responde survey. Observar uso, perguntar pessoalmente, gravar uso real (com permissão).
5. **Manter feature flags**: tudo do nível 3+ entra atrás de flag. Liga gradual conforme valida.

### Quem decide
- Estratégia: você (dono do produto)
- Tática (UX): designer + dev sênior
- Hipótese: cliente real (entrevista + observação)
- Validação: métrica (não opinião)

### Cuidados éticos

Gamificação tem dark patterns (gambling, FOMO, dark Nudges). Linhas que não cruzamos:

- ❌ Notificação ansiogênica ("seu colega já fez 5 OSs, você 2!")
- ❌ Streak punitivo ("se perder hoje, volta a zero")
- ❌ Variable reward que vira slot machine (recompensa aleatória pura)
- ❌ Manipular emocionalmente o usuário

✅ Reconhecimento honesto > pressão
✅ Reward de pertencimento (tribo) > reward arbitrário
✅ Transparência (saber o que mede) > obscuridade

---

## 12. Resumo executivo — TL;DR

**Para mecânicos/produção usarem de verdade, o app precisa:**

1. **Resolver problema real do dia deles** (não problema do gerente)
2. **Ser MAIS RÁPIDO que o método atual** (caderno, post-it, WhatsApp manual)
3. **Funcionar em qualquer condição** (offline, mão graxenta, pressa)
4. **Dar reconhecimento concreto** (cliente elogiou, % do dia concluído)

**Para criar dependência (de forma ética):**

1. **Investment** (notas, atalhos, histórico) — switching cost cresce naturalmente
2. **Variable reward social** (reação do cliente, % concluído) — dopamina honesta
3. **Identity** ("sou o mecânico do Gol do Sr. José") — protagonismo real
4. **Hábito embutido no fluxo** (não outra tarefa, parte do trabalho)

**3 features que mais movimentam ponteiro nos primeiros 30 dias:**

1. 🥇 **Scanner universal** (placa/GTIN/QR) — economia diária visível
2. 🥈 **Foto antes/depois → WhatsApp automático** — orgulho + cliente vê
3. 🥉 **Histórico do cliente/veículo rico** — protagonismo individual

**Tempo realista pra ver tração**: 60-90 dias com 1 cliente piloto rodando duro. Métrica de sucesso: usuário pega o celular **antes** da notificação push em mais de 50% das aberturas.

---

## 13. Próximas perguntas pra você responder

Antes de tudo:

1. **Quem é o piloto?** 1 oficina + 1 gráfica disponíveis pra testar 30 dias com feedback semanal?
2. **Qual o orçamento mensal pra IA/Voice/Vision?** Voice-to-text via Whisper API custa ~$0.006/min. 100 mecânicos × 50min/dia = $300/mês.
3. **Z-API premium liberado?** Webhooks de leitura/resposta exigem plano pago.
4. **Análise de uso ok?** PostHog ou Mixpanel free tier basta. Precisa do aceite do operador (LGPD).
5. **Som/haptic é OK?** Algum cliente prefere silêncio? Configurar como opt-in seguro.

---

Documento revisado em 17 de maio de 2026. Status: **proposta estratégica — aguardando piloto pra validar hipóteses**.
