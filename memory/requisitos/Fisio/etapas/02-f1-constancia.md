# Etapa 02 — F1 Constância: portal, lembrete WhatsApp, check-in, jogo e painel de risco

> **Decisão posterior de [W] (2026-10-18): o driver é `whatsmeow`** (ADR 0437 §13.1). As seções sobre API oficial da Meta (categorias, preço por template, aprovação de template, Flows) valem só se o canal for trocado para `meta_cloud`. Com whatsmeow não há template nem custo por mensagem; em troca valem as mitigações de banimento da ADR §13.1 (um número por clínica, teto por hora, aquecimento, alerta de canal caído, alternativa `wa.me`). O resto desta especificação (horário-âncora, check-in em 3 toques, jogo, painel de risco) não muda.

> Pesquisa feita em 2026-10-17. Fontes numeradas em §7, citadas como [n].
> Durante a pesquisa o limite de buscas web da sessão acabou. Os pontos que ficaram sem verificação estão marcados **não confirmado**. Repetir essas buscas antes de fechar o PR.

---

## 1. Objetivo da etapa e meta de nota

**Objetivo.** Fazer o paciente cumprir o programa nos dias prescritos e mostrar ao fisioterapeuta, sem trabalho extra, quem fez, quem está com dor e quem está sumindo. Tudo isso dentro da ADR 0437: não virar SaMD, consentimentos separados e cada check-in gerando registro de telemonitoramento (Res. COFFITO 619/2025).

**Nota na grade.** A grade atual não tem uma linha "constância/adesão", e é essa linha que define o produto. Proponho incluí-la.

| linha | hoje | meta F1 | por quê (estimativa minha) |
|---|---|---|---|
| Constância/adesão (nova) | 0 (não existe) | **9** | Physitrack tem app e lembrete, mas não tem WhatsApp oficial nem jogo com regras clínicas. Kynesia e Vedius têm WhatsApp, mas nenhum mede adesão com jogo e painel de risco explicável. O "10" fica reservado para quando houver dado próprio de que a adesão sobe (ver §5). |
| Maturidade | 1 | 3 | Uma vertical em produção com testes, consentimentos e trilha de auditoria, mas ainda sem piloto pago. |

---

## 2. O que os melhores fazem: copiar e evitar

### 2.1 Evidência de adesão que dá forma ao produto

| achado | número | o que fazer no produto |
|---|---|---|
| **ADHERE (Bennell et al., JMIR 2020)**: SMS semi-interativo, artrose de joelho, 24 semanas | Até 5 SMS por semana, espaçando com o tempo (autorrelato semanal até a semana 8, depois quinzenal). 66% de resposta. Quem fez os 3 dias pedidos: 40% no grupo SMS contra 24% no controle. Diferença de 0,6 dia/semana. Mensagens em linguagem de 5ª série. Quem fazia ≤2 sessões escolhia uma barreira entre 9 e recebia resposta específica para ela. [1] | Copiar quase literalmente: lembrete que **diminui de frequência** quando o hábito firma, pergunta de autorrelato com botão, menu de barreiras ("faltou tempo", "dor", "esqueci"…) com resposta pronta escrita pelo profissional ou pela clínica. |
| Mesmo estudo: a adesão melhorou, mas dor e função **não** mudaram | sem diferença nos desfechos secundários [1] | Não prometer resultado clínico no marketing. A promessa é constância. |
| **Implementação de intenção** ("quando e onde vou fazer"), metanálise em doentes crônicos | SMD 0,24 em atividade física. Efeito **maior em idosos** e **quando quem entrega é o profissional de saúde** (melhor que só web). Lembrete não modificou o efeito. [2] | No primeiro acesso, o paciente escolhe a âncora ("depois do café", "antes do banho") e o horário. O fisioterapeuta pode preencher isso na consulta, o que é mais forte. A âncora vai no texto do lembrete. |
| **Tempo para virar hábito** (Singh et al., 2024) | Mediana de 59 a 66 dias. Varia de 4 a 335. Hábitos **de manhã** e **escolhidos pela pessoa** ficam mais fortes. [3] | O episódio de fisioterapia (6–12 semanas, estimativa) mal chega ao hábito formado. O jogo precisa sustentar a constância até lá e depois sair de cena (§3.4, "fase manutenção"). Sugerir a manhã como padrão, mas a escolha é do paciente. |
| **Notificação puxa ação imediata, não retenção** (Drink Less, ensaio microrrandomizado, 2023) | A notificação multiplicou por 3,5 a chance de abrir o app na hora seguinte. O efeito não caiu em 30 dias, mas o tempo até o abandono não mudou entre as políticas de notificação. [4] | O lembrete serve para o dia de hoje. A retenção vem do vínculo com o profissional (painel, parabéns) e do progresso visível. |
| **Habituação** (HeartSteps) | O efeito da sugestão de caminhada sobre os passos nos 30 minutos seguintes **cai ao longo do estudo** (interação negativa com o ponto de decisão). [5] | Variar o texto (pool de 6 a 10 variantes por template) e reduzir a frequência para quem já é constante. Não mandar lembrete a quem já fez o check-in do dia. |
| Dor alta e incapacidade predizem não adesão (BrJP 2023, SUS, ombro) | Não aderentes tinham dor média de 7,6, contra 1,6 dos aderentes. SPADI foi o único preditor mantido (OR 0,86 por ponto). Amostra pequena: só 6 não aderentes. [6] | Dor alta entra no painel de risco como sinal **para o profissional**, nunca como conduta para o paciente. |
| Apps com vídeo personalizado (Davergne et al., JMIR 2023, 10 ECRs) | Função física: SMD 0,35. Adesão: 3 de 6 estudos positivos, evidência muito baixa. 9 de 10 apps tinham comunicação com o terapeuta. [7] | A comunicação com o profissional é o padrão do mercado. O botão "mandar parabéns" e a mensagem do profissional precisam estar a um toque. |

### 2.2 Gamificação: evidência, efeito rebote e ética

| achado | número | lição |
|---|---|---|
| Metanálise (Mazeas et al., JMIR 2022), 16 ECRs, 2.407 pessoas | g = 0,42 durante a intervenção, mas só **g = 0,15** cerca de 14 semanas depois de acabar [8] | O efeito existe e encolhe quando o jogo para. Desenhar a saída: o jogo vira "modo manutenção" na alta. |
| Metanálise de gamificação em doença cardiovascular (JMIR Serious Games 2025), 6 ECRs | g = 0,32. Follow-up g = 0,20. **Feedback** foi o elemento mais importante (0,71), seguido de avatar (0,59) [9] | Prioridade de construção: feedback claro de progresso (barra, "3 de 4 dias") antes de medalhas. |
| STEP UP (Patel, JAMA IM 2019), 602 adultos | Pontos com **aversão à perda** (começa com 70 pontos na segunda e perde 10 por dia sem meta), níveis azul→platina, "recomeço" semanal. Os três braços venceram o controle, mas no follow-up só a **competição** manteve efeito [10] | Perda de pontos funciona, mas em reabilitação pune o paciente com dor. **Não copiar a perda.** Competição está vetada pela ADR §6 (dado de saúde). Copiar o **recomeço semanal** e os níveis. |
| Efeitos adversos da gamificação em apps de saúde (Rocha e Silveira, PUCRS, JBCS 2024) | 22 efeitos adversos: perda de motivação intrínseca, pressão, desânimo após falha, punição exagerada, trapaça, uso excessivo, dependência. Os elementos mais associados foram **medalhas (39 menções)** e **pontos (34)**, depois níveis (19), prêmios (18) e comparação social (16) [11] | Cada elemento precisa ter ligação com o objetivo de saúde. Nada de punição. Medalhas poucas e com significado. Atenção a idosos. Profissional de saúde no desenho. |
| Duolingo | Sequência de 7 dias deixa o usuário **3,6×** mais propenso a terminar o curso. Poder equipar **2 "congelamentos"** aumentou os usuários ativos diários em 0,38%. "Amuleto de fim de semana": +4% de retorno na semana seguinte e −5% de sequência perdida. Aposta de sequência: +14% de retenção no dia 7. Quem maratona abandona mais que quem mantém ritmo [12][13] | Proteção da sequência com estoque de no máximo 2. Folga planejada sem penalidade (aqui a folga já é o dia não prescrito). Desafio opcional ("aposta" sem moeda). Premiar ritmo, não volume. |
| Finch | Metas reais dão energia ao bichinho, há recompensa adiada (aventura), missões diárias e tom não punitivo [14] | Tom de voz gentil. Recompensa adiada ("sua trilha avançou"). Como o Finch trata o dia perdido: **não confirmado**. |
| Strava (sequência semanal) | **não confirmado** (a página de suporte acessada era de outro recurso) | A ideia de "semana completa" é útil para idoso, mas está sem fonte. Uso como proposta minha. |

**Conclusão ética.** O jogo premia cumprir o prescrito e registrar com honestidade. Não pune, não compara pacientes e não usa recompensa aleatória de "caça-níquel". A recompensa variável aceita é conteúdo educativo surpresa ("você sabia?") escrito ou aprovado pelo profissional, sem moeda e sem loja na F1.

### 2.3 WhatsApp: o que o mercado faz e o risco do módulo atual

- **Concorrentes locais** (Vedius, Kynesia) usam WhatsApp para lembrete. Se usam a API oficial: **não confirmado**.
- **O módulo `Whatsapp` do oimpresso** já tem dois drivers atrás do `ChannelDriverFactory`: `whatsmeow` (conexão comum por QR code, não oficial) e `meta_cloud` (`MetaCloudDriver`, API oficial). A FAQ do WhatsApp diz que apps não oficiais violam os termos e podem levar a **banimento temporário ou permanente** [15]; com dado de saúde, o banimento derruba o lembrete de todos os pacientes daquela clínica de uma vez.
- **Decisão de [W] (ADR 0437 §13.1 e §14 nº 5):** o Fisio usa o driver `whatsmeow`, um número por clínica, sempre via `ChannelDriverFactory`, com as mitigações da §13.1. Trocar para `meta_cloud` é só configuração, porque o driver oficial já existe no módulo; nada de integração nova nem adapter próprio do Fisio.

---

## 3. Especificação melhorada

### 3.1 WhatsApp Business Platform: regras que valem para o desenho

**Preço (Brasil, código +55)**

| item | regra | fonte |
|---|---|---|
| Modelo | Cobrança **por mensagem de template entregue** desde 01/07/2025, conforme a categoria e o país do destinatário | Meta [17] |
| Categorias | Marketing, Utilidade, Autenticação | [17][18] |
| Utilidade dentro da janela de 24h | **Grátis até 30/09/2026** | [17] |
| **A partir de 01/10/2026** | Mensagens de serviço (texto livre dentro da janela) passam a ser cobradas, com **1.000 grátis por número por mês**. O template de utilidade dentro da janela passa a ser cobrado desde a primeira mensagem. Mensagem recebida continua grátis | fornecedores [19][20][21]. Na página oficial de preços acessada essa mudança **não apareceu**: confirmar no painel da Meta |
| Tarifa de utilidade no Brasil | Fornecedores divergem: **US$ 0,0068** [21][22] ou **US$ 0,008** [19][23] por mensagem. A tabela oficial em BRL **não foi lida** | conflito, **não confirmado** |
| Tarifa de marketing no Brasil | US$ 0,0625 [22] a US$ 0,075 [19] | **não confirmado** |
| Faturamento em real | Desde 01/07/2026, quem tem o Brasil como país de faturamento cria WABA em BRL, faturada pelo Facebook Brasil. Migração obrigatória até 30/06/2027. Depois disso, WABA fora de BRL deixa de entregar | Meta [17] |
| Desconto por volume | Utilidade e autenticação ficam mais baratas com o volume mensal, somado por portfólio | Meta [17] |

**Custo estimado por paciente (estimativa minha, não é preço):** 4 dias prescritos por semana, com um template de utilidade por dia prescrito, dá cerca de 17 mensagens por mês. A US$ 0,0068–0,008 e câmbio de R$ [redacted Tier 0] (premissa), são **R$ [redacted Tier 0] por paciente por mês**, mais as mensagens de serviço do check-in conversado, que cabem nas 1.000 grátis por número em clínica pequena. É compatível com o "≈ R$ [redacted Tier 0] por mensagem" da ADR.

**Opt-in (Meta):** a pessoa precisa dizer que aceita receber mensagens **daquele negócio identificado pelo nome**. O canal é livre (site, SMS, presencial, papel). A Meta recomenda opt-in por categoria e opt-out fácil, e pode auditar o fluxo [24][25]. No produto, o consentimento `whatsapp` (`fisio_consents`) já cumpre isso se o texto disser o nome da clínica e do profissional e listar o que será enviado (programa, lembretes, avisos do profissional). Precisa ser registrado **antes** do primeiro template.

**Política de conteúdo:** proíbe telemedicina e troca de informação de saúde **onde a lei local proibir** [24]. No Brasil, a Res. 619/2025 e a Lei 14.510 permitem telessaúde com consentimento, então o uso é viável. Mesmo assim: **não pôr dado clínico no corpo do template** (nem diagnóstico, nem nome de condição). O lembrete fala de "seus exercícios", nunca de "sua prótese de quadril".

**Categoria e reprovação (Meta, oficial) [18]:**
- Utilidade = mensagem ligada a um serviço específico do usuário, não promocional. Vira marketing quando mistura promoção, quando o texto é vago ("Parabéns!") ou quando tem persuasão, upsell ou **lembrete de renovação**. Logo, renovar pacote (F3) é marketing.
- Desde **09/04/2025**, um template enviado como utilidade que a Meta julga marketing é **aprovado como marketing**, sem reprovação. Isso custa ~9× mais sem avisar ninguém. O sistema precisa ler `category` contra `correct_category` e o webhook `template_category_update`.
- Recategorização com 1 dia de aviso. Para quem recebeu alerta de uso indevido, sem aviso.
- Revisão ou recurso em até **60 dias**.

**O que fazer quando o template é reprovado ou recategorizado (regra do produto):**
1. Cada template tem **2 variantes aprovadas** (A/B), com texto diferente e a mesma função.
2. Webhook de status ou categoria → se a variante ativa sair de `APPROVED`/`UTILITY`, troca automaticamente para a outra e alerta o admin do oimpresso (não o fisioterapeuta).
3. Se as duas caírem, os envios entram no **fallback humano**: o painel mostra "lembretes de hoje não saíram — enviar pelo seu WhatsApp", com link `wa.me/55…?text=` já preenchido. Quem envia é o profissional, do aparelho dele, sem risco de banimento por automação. O PWA push (§3.6) é o segundo fallback.
4. Corrigir o texto e reenviar como template novo. Não editar o reprovado.

**Limites de envio:** o limite conta **usuários únicos alcançados por template em 24h** fora da janela. Começa em 250 para portfólio novo, passa a 2.000 com verificação do negócio e depois a 10 mil, 100 mil e ilimitado conforme qualidade e volume. Desde out/2025 o limite é **por portfólio**, então somar números não soma capacidade. Qualidade baixa não rebaixa mais o nível, mas segura a subida [26] (fonte de fornecedor, **confirmar na Meta**).

**Consequência de arquitetura (decisão para [W]):**

| opção | prós | contras |
|---|---|---|
| **A. Número único "Fisio · oimpresso"** (um WABA do oimpresso) | Rápido. Um processo de verificação. Ideal para o freemium (≤5 pacientes) | Começa com 250 usuários/dia. O limite e a qualidade são compartilhados entre todas as clínicas. As 1.000 mensagens de serviço grátis são divididas. A marca no cabeçalho do chat é do oimpresso, e o profissional entra só como variável no texto |
| **B. Número da clínica** (Embedded Signup, oimpresso como Tech Provider) | Marca do profissional no chat. Qualidade e limite isolados. 1.000 de serviço grátis por clínica | Mais configuração. Requisitos de Tech Provider **não confirmados** nesta pesquisa |

Decidido por [W] (ADR 0437 §13.1(a) e §14 nº 5): **um número por clínica**, nunca compartilhado entre clínicas. A tabela acima fica como registro da pesquisa; a opção A não vale. O Fisio não tem interface própria de envio: chama o módulo `Whatsapp` pelo `ChannelDriverFactory`, que resolve o canal conectado de cada `business_id`.

**Botões e janela de 24h:**
- Mensagem interativa com botões de resposta: **até 3 botões, 20 caracteres cada** [27]. Se templates de utilidade aceitam botão de resposta rápida junto com botão de URL na mesma mensagem: **não confirmado**. Testar no cadastro do template.
- Quando o paciente toca um botão de resposta, abre a janela de 24h. As perguntas seguintes vão como mensagem de serviço (texto e botões livres, sem aprovação), que fica grátis até 1.000 por número por mês depois de 01/10/2026.

### 3.2 WhatsApp Flows: dá para fazer o check-in todo dentro do WhatsApp?

**Tecnicamente, sim.** O Flows tem: radio com 1 a 20 opções (cabe dor de 0 a 10 com 11 opções), checkbox (exercícios feitos, sinais de alerta), dropdown, campo de texto, área de texto (600 caracteres por padrão), opt-in, imagem (3 por tela) e renderização condicional (`If`/`Switch`). Não há **slider** [28]. A Meta recomenda uma tarefa por tela, no máximo 10 opções por tela e tarefa que termine em até 5 minutos. Desde a versão 5.1, as respostas aparecem na mensagem de retorno, exceto os campos marcados `sensitive` [29]. O Flow pode ir num template com botão `flow` [30]. A documentação só mostra o exemplo com categoria MARKETING; se é aceito em **UTILIDADE**, **não está confirmado**.

**Recomendação: não usar Flows na F1. Usar check-in rápido por botões mais o portal.**

| critério | Flows | botões + portal |
|---|---|---|
| Toques para "fiz tudo, dor 3, sem alerta" | 3 a 4 | 3 |
| Mostrar vídeo do exercício | não (só imagem) [28] | sim |
| Jogo (XP, nível, trilha) | não | sim |
| Dado de saúde trafegando pela Meta | a resposta inteira (dor e sinais de alerta) | só o toque "Fiz tudo"/"Fiz parte". Dor e alerta ficam no portal |
| Registro de telemonitoramento (Res. 619) | possível pelo webhook | nativo |
| Risco de categoria e aprovação | mais um ativo para a Meta aprovar | só templates simples |

O Flows fica como **opção F1.5**, atrás de decisão de [W] e de revisão LGPD do trânsito de dor e sinais de alerta pela Meta. Ganha força se a medição mostrar que o paciente não abre o link (meta em §5).

### 3.3 Lembrete: regras

| regra | valor | base |
|---|---|---|
| Quem recebe | Só paciente com consentimento `teleatendimento` e `whatsapp` ativos, programa ativo e dia prescrito hoje | ADR §7 |
| Horário | Escolhido pelo paciente no primeiro acesso, ou pelo profissional na consulta. Padrão 08:00. Janela permitida de 07:00 a 21:00 no horário do paciente | [2][3]. A janela é escolha minha (sossego) |
| Âncora | Texto curto ("depois do café"), opcional, inserido no lembrete | implementação de intenção [2] |
| Já fez hoje | Não envia | [5] (habituação) |
| Frequência adaptativa | Semanas 1–3: todo dia prescrito. Se a adesão de 14 dias ficar ≥ 85%, o paciente passa a "lembrete leve" (dias alternados, e o dia sem lembrete continua valendo para a sequência). Se a adesão cair abaixo de 60% por 7 dias, volta ao diário | espaçamento do ADHERE [1]. Os limites numéricos são proposta minha |
| Teto | No máximo 1 template por paciente por dia, mais 1 "sentimos sua falta" depois de 3 dias prescritos sem registro, no máximo 1 por semana | fadiga. Proposta minha |
| Variação | Pool de 6 a 10 textos aprovados por função, em rodízio sem repetir 2 dias seguidos | [5] |
| Barreiras | Depois de 2 dias prescritos sem registro, a mensagem de serviço (com a janela aberta) oferece 3 botões: "Faltou tempo", "Senti dor", "Esqueci". Cada um leva à resposta escrita pela clínica e registra o motivo para o profissional. "Senti dor" leva **só** à instrução estática do profissional e notifica o profissional | [1]. ADR §5 |
| Opt-out | "PARAR" ou botão "Não quero lembretes" revoga o consentimento `whatsapp` (versão e canal registrados). O portal continua funcionando | [24][25] |
| Idempotência | Chave `(business_id, patient_id, date, kind)` única no log de envio | handoff T-09 |

**Templates (rascunho, categoria utilidade, sem dado clínico):**

1. `fisio_programa_pronto`: "Olá, {{1}}! Aqui é {{2}}, fisioterapeuta (CREFITO {{3}}). Seu programa de exercícios está pronto. Toque no botão para abrir." Botão de URL dinâmica: `/p/{{token}}`.
2. `fisio_lembrete_dia_a` e `_b`: "{{1}}, hoje é dia dos seus exercícios com {{2}} (cerca de {{3}} min). Você combinou: {{4}}." Botões de resposta: "Já fiz" / "Vou fazer agora" / "Hoje não dá". Mais o botão de URL "Abrir exercícios", se a combinação for aceita (**não confirmado**).
3. `fisio_sentimos_falta`: "{{1}}, faz alguns dias que não vemos seu registro. Tudo bem por aí? Toque para registrar como você está." Sem tom de culpa.
4. `fisio_recado_profissional`: "{{1}}, {{2}} deixou um recado sobre seu programa. Toque para ver." O recado em si fica no portal. Assim evita o "Parabéns!" vago, que vira marketing [18].

### 3.4 Jogo de constância: regras refinadas da ADR §6 (com números)

Os números abaixo são **proposta minha**, calibrada pela evidência de §2.2. Devem ser parâmetros de configuração com estes valores padrão e testados por regra.

**Unidade: dia prescrito.** Cada paciente tem `sessions_per_week` = *n* (1–7).

**XP do dia prescrito (máximo de 100 por dia, normalizado):**
- `exercicio_concluido`: 70 × (itens feitos ÷ itens prescritos). Cada item conta no máximo `times_per_day` vezes. Exceder o prescrito dá 0.
- `registro_do_dia`: 30, **sempre**, com qualquer dor, inclusive dor ≥ 7 e sinal de alerta.
- Normalização semanal: o XP do dia é multiplicado por 4 ÷ *n*. Assim, quem tem 7 dias por semana não sobe de nível quase duas vezes mais rápido que quem tem 4 (teto de 400 por semana em qualquer prescrição).
- Missões e desafio somam até **+100 por semana** (abaixo). Teto semanal: **500 XP**.

**Níveis (10, nomes da ADR), curva levemente crescente:**

| nível | nome | XP acumulado | semanas até chegar (100%) | semanas (70%) |
|---|---|---|---|---|
| 1 | Primeiro Passo | 0 | 0 | 0 |
| 2 | Ritmo | 150 | 0,3 | 0,4 |
| 3 | Constância | 400 | 0,8 | 1,1 |
| 4 | Base Firme | 800 | 1,6 | 2,3 |
| 5 | Equilíbrio | 1.300 | 2,6 | 3,7 |
| 6 | Força Leve | 2.000 | 4 | 5,7 |
| 7 | Movimento Livre | 2.900 | 5,8 | 8,3 |
| 8 | Corpo Forte | 4.000 | 8 | 11,4 |
| 9 | Mestre do Movimento | 5.400 | 10,8 | 15,4 |
| 10 | Inabalável | 7.000 | 14 | 20 |

Lógica da curva: subir de nível na primeira semana (feedback rápido, [9]), chegar ao nível 7 ou 8 num episódio típico de 8 a 12 semanas (estimativa) e deixar o 10 para quem passa da mediana do hábito (59–66 dias, [3]). **Nível nunca desce.**

**Sequência (`dias prescritos seguidos`):**
- Conta só dia prescrito com registro (com ou sem exercício). Registrar dor sem conseguir fazer **mantém** a sequência, porque o registro honesto é cumprir o combinado.
- **Escudo:** ganha 1 a cada 7 dias prescritos seguidos, com estoque máximo de **2** (como os 2 congelamentos do Duolingo [12]). O dia prescrito perdido consome um escudo automaticamente.
- **Pausa protegida:** com dor ≥ limiar do programa ou sinal de alerta, a sequência congela sem gastar escudo até o profissional reativar ou passarem 7 dias.
- Quando a sequência quebra: aparece "Melhor sequência: 18 · Dias cumpridos no total: 41". O **total nunca zera** (evita a "punição exagerada" de [11]). Não há texto de perda, só "Recomece hoje".
- **Semanas completas** (≥ 80% dos dias prescritos da semana): segunda métrica, mais tolerante, destacada para pacientes com marcador `risco_queda`, idosos ou poucos dias por semana. Proposta minha, inspirada em sequência semanal (Strava, **não confirmado**).

**Missões diárias (3 por dia prescrito, +10 XP cada, só contam até o teto semanal):**
Sorteadas de um pool, **sempre dentro do prescrito**:
- "Faça {{exercício X}} do seu programa"
- "Registre como foi hoje"
- "Faça no horário que você combinou (±1h)" (reforça a implementação de intenção)
- "Veja a dica do {{profissional}}" (conteúdo educativo do profissional)
- "Faça todas as séries de {{exercício Y}}"

Proibidas: "faça mais", "bata seu recorde de repetições", "dois dias seguidos sem descanso".

**Desafio semanal opcional (+40 XP):** na segunda, o paciente aceita ou não "Cumprir {{n−1 ou n}} dos {{n}} dias desta semana". Não aceitar não tira nada. Não cumprir não tira nada. É a "aposta" do Duolingo sem moeda [13].

**Medalhas (12 na F1, poucas e com significado [11]):**
Primeiro registro · 1ª semana completa · 4 semanas completas · Sequência 7 · Sequência 21 · Sequência 42 · Horário combinado 10 vezes · Programa concluído (alta) · Voltou depois de pausa · Registro honesto (10 registros com dor anotada) · Nível 5 · Nível 10.

**Recompensa variável (ética):** ao concluir o dia, há 1 chance em 3 de aparecer um card "Você sabia?" do conteúdo do profissional ou da biblioteca. Sem moeda, sem loja, sem sorteio de prêmio. Moedas `--vip` ficam fora da F1.

**Saída do jogo (alta):** quando o programa vai para `concluido`, o portal entra em "modo manutenção": lembrete semanal opcional (consentimento continua valendo), sem missões, com nível e medalhas mantidos. Responde ao efeito que encolhe quando o jogo para [8][10].

**Ledger (`fisio_game_ledger.reason`, enum fechado):** `exercicio_concluido`, `registro_do_dia`, `missao`, `desafio_semanal`, `medalha` (0 XP), `escudo_ganho` (0), `escudo_usado` (0), `pausa_protegida` (0). Sem UPDATE. Nível, sequência e escudos são derivados.

### 3.5 Check-in ideal

**Meta: 3 toques no caminho feliz e ≤ 30 segundos.**

1. **"Como foi hoje?"** Três botões grandes: `Fiz tudo` · `Fiz parte` · `Hoje não consegui`. "Fiz parte" abre a lista do dia com checkbox já marcado por item. Se o paciente marcou itens ao longo do dia na tela Hoje, esse passo vem pré-preenchido.
2. **Dor agora (0–10).** 11 botões numerados em grade de 2 linhas, cada um com alvo de no mínimo 48 px. Âncoras de texto: 0 "Sem dor", 1–3 "Leve", 4–6 "Moderada", 7–9 "Forte", 10 "Pior possível". A cor vem só de token, num gradiente neutro (sem vermelho que assuste), e o número é sempre visível.
   - **Não usar a FPS-R (escala de faces):** ela é para crianças de 4 a 16 anos, e uso comercial ou em web exige licença da Mapi Research Trust [31]. Faces próprias, se um dia houver, precisam de desenho original.
   - Escala numérica com âncora verbal é a recomendação para idoso: **não confirmado nesta pesquisa** (busca esgotada). Validar com 5 pacientes acima de 65 anos no piloto.
3. **"Notou algo diferente hoje?"** Botão grande `Não, tudo igual` (o caminho feliz termina aqui) e `Sim` abre a lista de sinais de alerta **escolhida pelo profissional no programa** (padrão por região):
   - geral: inchaço, vermelhidão ou calor novos · febre · queda · dor que acorda à noite ou não melhora em repouso · formigamento ou dormência novos
   - lombar: perda de força nas pernas · alteração para urinar ou evacuar · dormência na região íntima
   - pós-operatório: secreção ou abertura no corte · panturrilha inchada e dolorida
   Texto: "Vamos avisar {{profissional}}." O paciente vê **só** a instrução estática escrita pelo profissional (`stop_instruction`) e um aviso fixo, igual para todos: "Se for uma emergência, ligue 192 (SAMU)". **Pendente de [W]:** a ADR 0437 §14 nº 9 segura esse aviso fixo até haver parecer regulatório, e em conflito vale a ADR; até lá ele não entra no produto. **A conferir:** se o aviso fixo de emergência precisa de leitura regulatória. Ele não é calculado por dor, então entendo que fica fora do P10 da RDC 657, mas isso não está confirmado.
4. Opcional e recolhido: esforço (3 botões: "Fácil", "Na medida", "Difícil") e comentário de texto (até 280 caracteres).

**Depois do envio:** animação de XP e sequência (se não houver alerta), card "Você sabia?" e "Até {{próximo dia prescrito}}".
Com dor ≥ limiar ou sinal de alerta: **não** há animação de jogo. Aparece a tela com a instrução do profissional e "Seu registro foi enviado. Sua sequência está protegida." Isso cumpre a ADR §6.

**Registro de telemonitoramento:** cada check-in gera `fisio_records` do tipo `telemonitoramento`, com `modality=assincrono` e `channel=portal|whatsapp_botao`, corpo resumido (itens, dor, alertas) e autor "registro do paciente". A evolução assinada continua sendo do profissional (Res. 414).

**Check-in pelo WhatsApp (sem abrir o link)** — vale só com o canal `meta_cloud`. Com o driver `whatsmeow` (decisão da ADR 0437 §13.1) não há template, e botões de resposta interativos não são garantidos [não confirmado]; nesse caso o check-in é sempre pelo link do portal: "Já fiz" no lembrete registra `done=todos` com `pain=null` e responde (mensagem de serviço) com 3 botões "Dor 0–3", "Dor 4–6", "Dor 7–10". Na faixa 7–10, envia o link do portal para detalhar e notifica o profissional. O XP de `registro_do_dia` só entra com dor registrada (honestidade, faixa aceita). A faixa é dado menos preciso: guardar `pain_band` em vez de `pain`. Trafega pela Meta um dado de saúde mínimo (a faixa de dor), o que precisa constar no consentimento `whatsapp`.

**Áudio do paciente:** fica **fora da F1** (o handoff proíbe guardar áudio). Na Cloud API, o áudio chega ao webhook e teria de ser armazenado e transcrito. Isso fica para a F4 (ditado e voz), com consentimento próprio e prazo de guarda definido. Enquanto isso, áudio recebido gera resposta automática: "Por aqui eu não consigo ouvir áudio. Toque para registrar no seu programa" + link. O arquivo não é baixado.

### 3.6 Portal: link web, PWA e offline

| tema | fato | decisão |
|---|---|---|
| Instalar no Android | Chrome gera WebAPK e o próprio PWA pode disparar o convite de instalação [32] | Depois do 3º check-in, mostrar "Adicionar à tela inicial" (Android) |
| Instalar no iOS | Não existe convite de instalação. O usuário usa Compartilhar → Adicionar à Tela de Início. Só funciona em modo `standalone`, e cada instalação tem armazenamento isolado [32] | Folha de instruções ilustrada, só para quem pedir. Não é pré-requisito |
| Push no iOS | Exige iOS/iPadOS 16.4 ou mais novo, o site **adicionado à Tela de Início**, manifest com `display: standalone` e pedido de permissão por gesto do usuário [33]. O Declarative Web Push existe a partir do iOS 18.4 [34], mas se dispensa a instalação no iOS **não está confirmado** | WhatsApp continua sendo o canal principal. Push é canal secundário opcional para quem instalou (reduz custo e serve de fallback). Mudanças do iOS 26 para web apps: **não confirmado** |
| Acesso | Link HMAC + PIN com lockout (padrão OficinaAuto) | **Novo:** "Lembrar este aparelho por 30 dias" (cookie httpOnly assinado, ligado ao token, revogável pelo profissional). Sem isso o paciente digita o PIN todo dia, que é o atrito nº 1 da ADR §2. O PIN volta a ser pedido em aparelho novo, depois de 30 dias ou depois de revogação |
| Offline | — | Service worker guarda o programa do dia (texto, passos, miniaturas). Vídeo só com o paciente tocando "baixar". Check-in feito offline vai para fila no IndexedDB com `idempotency_key = patient+date` e é enviado ao reabrir. Background Sync no iOS: **não confirmado**, por isso a fila não depende dele |
| Leveza | — | Primeira carga ≤ 200 KB de JS na rota pública (meta minha), sem shell da clínica |

### 3.7 Painel de risco de abandono: explicável e sem virar SaMD

**Enquadramento.** O painel mede **adesão e engajamento** para o profissional, a partir do que o paciente registrou (P11: registrar dor para o profissional avaliar não é SaMD). O painel **não** sugere conduta, não muda o programa e não fala com o paciente. Os nomes na interface são "Risco de abandono" e "Precisa de atenção", nunca "risco clínico" ou "piora".

**Duas filas separadas:**
1. **Alerta (sempre no topo, sem pontuação):** sinal de alerta marcado ou dor ≥ limiar do programa nas últimas 72h. Notificação imediata ao profissional (e-mail ou WhatsApp do profissional, com o opt-in dele).
2. **Risco de abandono:** pontos por regra, com o motivo explícito.

| regra (janela) | pontos | base |
|---|---|---|
| Sem registro há ≥ 4 dias prescritos | 3 | handoff (fila "sem registro há ≥ 4 dias") |
| Adesão de 7 dias < 50% | 2 | [1] (definição de adesão) |
| Dor ≥ 7 em 2 registros seguidos | 2 | [6] |
| Dor subiu ≥ 2 pontos contra a média da 1ª semana e persistiu em 2 registros | 2 | handoff |
| Adesão caiu ≥ 30 pontos percentuais contra a semana anterior | 1 | proposta |
| Barreira "Senti dor" ou "Esqueci" escolhida 2 vezes em 7 dias | 1 | [1] |
| 3 lembretes seguidos com status entregue e não lido (webhook) | 1 | proposta |
| Não abriu o portal 48h depois do envio do programa | 1 | proposta |

**Faixas:** 0–1 verde · 2–3 amarelo · ≥ 4 vermelho. Os pesos são **estimativa minha**, inspirados nos preditores citados. O sistema registra, sem usar, o desfecho de cada paciente ("abandono" = sem registro por 14 dias com programa ativo, ou alta sem registro final) para calibrar os pesos na F3 com dado próprio.

**Apresentação ao profissional:**
- Linha da fila: nome, faixa colorida por token e **os 1 a 3 motivos em texto** ("4 dias sem registro · dor 8 e 7 nos dois últimos"), mais nível e sequência.
- Ações a um toque: `Mandar recado` (template `fisio_recado_profissional`) · `Parabéns` · `Abrir prontuário` · `Visto, lembrar em 3 dias` (adiamento registrado).
- Card "Por que este paciente está aqui?" com as regras que dispararam e os registros de origem (com link). Nada de "caixa-preta".
- KPIs do topo: adesão média de 7 dias, pacientes ativos, dor média de 7 dias (só na visão do profissional) e % que abriu o portal.

### 3.8 Dados: acréscimos ao handoff

| tabela/campo | conteúdo |
|---|---|
| `fisio_patients` + | `reminder_time` (time), `reminder_anchor` (string 60), `reminder_mode` (`diario`/`leve`/`desligado`), `timezone`, `red_flag_set` (json, região) |
| `fisio_checkins` + | `pain_band` (nulo, `0-3`/`4-6`/`7-10`) quando vier do WhatsApp, `channel`, `effort`, `barrier` (enum), `client_idempotency_key` |
| `fisio_message_log` (novo, ou reuso do log do módulo Whatsapp com `context=fisio`) | business_id, patient_id, kind, template_name, template_variant, category_cobrada, wa_message_id, status (`sent`/`delivered`/`read`/`failed`), error_code, sent_at, `unique(business_id, patient_id, date, kind)` |
| `fisio_wa_templates` (global do oimpresso) | name, variant, language, category_pedida, category_atual, status, last_webhook_at |
| `fisio_risk_snapshots` | business_id, patient_id, date, score, band, reasons (json), outcome (preenchido depois) |
| `fisio_portal_devices` | business_id, token_id, device_hash, remembered_until, revoked_at |
| `fisio_game_ledger.reason` | enum de §3.4 |

---

## 4. Riscos e o que fica fora

| risco | mitigação |
|---|---|
| Banimento do número no transporte não oficial (whatsmeow) [15] | Risco aceito por [W] (ADR 0437 §13.1). Mitigações: um número por clínica, teto por hora com intervalo aleatório, só paciente com consentimento de WhatsApp, alerta ao profissional quando o canal cair, link do portal copiável como alternativa. Trocar para `meta_cloud` é configuração |
| Template recategorizado como marketing sem reprovação (custo ~9×) [18] | Checagem de `correct_category` por webhook e job diário, troca de variante, alerta ao admin |
| Mudança de preço em 01/10/2026 (serviço e utilidade na janela) [19][20] | Custo por paciente calculado no log e exibido no admin. Teto mensal por business, configurável |
| Limite de 250 usuários/dia em portfólio novo [26] | Verificação do negócio antes do piloto. Opção B (número da clínica) para os pagos |
| Dado de saúde trafegando pela Meta | Template sem dado clínico. Só faixa de dor pelo botão. Flows fora da F1. Consentimento `whatsapp` cita isso |
| Jogo gerando culpa ou pressão [11] | Sem perda de XP, total nunca zera, escudos, pausa protegida, tom gentil, teste com idosos |
| Efeito do jogo e do lembrete diminui com o tempo [5][8] | Variação de texto, lembrete leve, modo manutenção |
| Aviso fixo "ligue 192" ser lido como conduta | Texto igual para todos e independente de dado. Pedir parecer junto da consulta Anvisa da F2 |
| PIN diário afastando paciente | "Lembrar aparelho 30 dias" com revogação |

**Fora da F1:** WhatsApp Flows (F1.5, decisão de [W] mais LGPD) · áudio do paciente (F4) · moedas e loja · ranking · push como canal principal · cálculo automático de conduta · gráfico de dor para o paciente (classe I, ADR §5) · número próprio por clínica, se [W] escolher a opção A no lançamento · renovação de pacote por WhatsApp (marketing, F3).

---

## 5. Métricas de sucesso (piloto de 8 semanas, metas minhas)

| métrica | meta | referência |
|---|---|---|
| Paciente abriu o portal até 48h depois do envio | ≥ 80% | se ficar < 60%, reavaliar Flows |
| Check-in nos dias prescritos, semana 4 | ≥ 60% | a não adesão de base é de 30–50% (ADR §1) |
| Pacientes com ≥ 3 de 4 dias na semana 8 | ≥ 40% | ADHERE: 40% contra 24% [1] |
| Resposta ao botão do lembrete | ≥ 50% | ADHERE: 66% de resposta [1] |
| Check-in em ≤ 3 toques | ≥ 70% dos check-ins | — |
| Opt-out de lembrete | < 5% em 8 semanas | ADHERE: 2 de 56 [1] |
| Qualidade do número | Verde em todas as semanas | [26] |
| Custo WhatsApp por paciente ativo por mês | ≤ R$ [redacted Tier 0] | estimativa §3.1 |
| Profissional abre o painel | ≥ 3 vezes por semana | — |
| "Parabéns" enviado por profissional por semana | ≥ 1 por paciente ativo em 50% das clínicas | feedback positivo do profissional [7] |
| Precisão do vermelho (abandonou em 14 dias) | só medir na F1 e calibrar na F3 | §3.7 |

---

## 6. Tarefas para o Claude Code (refinam T-07 a T-10 do handoff)

| id | tarefa | depende de | DoD |
|---|---|---|---|
| T-07a | Portal: token HMAC + PIN + lockout + **lembrar aparelho 30 dias** (`fisio_portal_devices`) + revogação pelo profissional | T-06 | Testes: PIN errado 5× bloqueia; cookie de outro token não vale; revogação derruba o aparelho lembrado; expiração |
| T-07b | Consentimentos no portal: teleatendimento obrigatório e WhatsApp com texto que nomeia clínica, profissional e o que será enviado (incluindo faixa de dor via botão) | T-07a | Sem `teleatendimento`, o portal não abre o programa. Sem `whatsapp`, nenhum envio. Versão, IP e canal gravados |
| T-07c | Check-in em 3 passos (§3.5), sinais de alerta por região, instrução estática, registro `telemonitoramento` e fila offline com chave de idempotência | T-07b | Caminho feliz em 3 toques (teste E2E). Dor ≥ limiar não mostra jogo. Check-in duplicado na mesma data faz merge sem duplicar. Gera 1 `fisio_records` por check-in |
| T-08a | Ledger e regras de XP (70/30, normalização 4/n, teto de 500 por semana, nada por exceder) | T-07c | Testes por regra da §3.4. Excesso dá 0. Dor 9 dá os 30 do registro |
| T-08b | Níveis (tabela §3.4), sequência por dia prescrito, escudos (máx. 2), pausa protegida, total que nunca zera, semanas completas | T-08a | Testes: dia não prescrito não quebra a sequência; escudo consumido; pausa não gasta escudo; nível nunca desce |
| T-08c | Missões do pool permitido, desafio semanal opcional, 12 medalhas, card "Você sabia?", modo manutenção na alta | T-08b | Nenhuma missão fora do prescrito (teste percorre o pool). Recusar o desafio não tira XP |
| T-09a | Envio do Fisio pelo módulo `Whatsapp` via `ChannelDriverFactory` (driver `whatsmeow`, um número por clínica), com teto por hora e intervalo aleatório | T-07b | Sem disparo para business sem canal conectado ou paciente sem consentimento; canal caído gera alerta ao profissional (teste) |
| T-09b | Catálogo de templates (§3.3), 2 variantes cada, cadastro, webhook de status e `template_category_update`, job diário que compara `category` com `correct_category`, troca automática de variante e alerta ao admin | T-09a | Simulação de recategorização troca a variante e cria alerta |
| T-09c | Job de lembrete: horário e âncora do paciente, janela 07–21h, pula quem já fez, modo leve/diário automático, teto diário, "sentimos sua falta" semanal, rodízio de texto, log idempotente com custo estimado | T-09b, T-08b | Um envio por paciente por dia (teste de corrida). Nada para business sem módulo. Troca de modo pelos limites 85%/60% |
| T-09d | Respostas por botão: "Já fiz" → check-in com faixa de dor; menu de barreiras; "PARAR" revoga; áudio recebido gera resposta padrão sem download | T-09c, T-07c | `pain_band` gravado. Revogação versionada. Nenhum arquivo de áudio persistido |
| T-09e | Fallback humano: painel lista os envios falhos do dia com link `wa.me` pré-preenchido | T-09c | Template indisponível → item aparece no painel |
| T-10a | Motor de risco por regras (§3.7) com motivos em texto, `fisio_risk_snapshots` diário e registro de desfecho | T-08b, T-09d | Cada regra tem teste. Os números batem com check-ins de fixture. Motivos legíveis |
| T-10b | Painel: fila de alerta separada, fila de risco com 1–3 motivos, ações de um toque (recado, parabéns, adiar 3 dias), KPIs incluindo "% abriu o portal" | T-10a | `casos.md` coberto. Nenhum texto "risco clínico" (lint de copy) |
| T-13 | PWA da rota pública: manifest, ícones, service worker do programa do dia, convite de instalação Android após o 3º check-in, push opcional para instalados (iOS 16.4+) | T-07c | Programa abre sem rede. Check-in offline sincroniza ao voltar. JS público ≤ 200 KB |

Fora desta lista, por decisão pendente de [W]: Flows (F1.5) e número próprio por clínica (opção B), se não entrar no lançamento.

---

## 7. Fontes

1. Bennell et al., ADHERE, JMIR 2020 — https://www.jmir.org/2020/9/e21749
2. Metanálise de planos específicos em doentes crônicos, Front. Public Health 2022 — https://pmc.ncbi.nlm.nih.gov/articles/PMC9160833
3. Singh et al., *Healthcare* 2024, tempo para formar hábito — https://digital.library.adelaide.edu.au/items/fe6ba93e-1d6b-42b8-9ee7-a231a64ff006
4. Bell et al., notificações no Drink Less, JMIR mHealth 2023 — https://doaj.org/article/20ae0374687e46a4a4dfcd659b192528
5. Reanálise do HeartSteps (arXiv 2024) — https://arxiv.org/pdf/2410.15049
6. Santello et al., BrJP 2023 — https://scielo.br/j/brjp/a/K35tR9D6cdT3NX8mywhXYZz/?lang=en
7. Davergne et al., JMIR 2023 — https://www.jmir.org/2023/1/e45207 (e Argent et al., JMIR mHealth 2018 — https://mhealth.jmir.org/2018/3/e47)
8. Mazeas et al., JMIR 2022 — https://doaj.org/article/9754f1fd9dd84266b5e5a30336858ad8
9. Gamificação em doença cardiovascular, JMIR Serious Games 2025 — https://games.jmir.org/2025/1/e64410
10. STEP UP, resumo ACC — https://acc.org/latest-in-cardiology/articles/2019/11/26/08/23/key-takeaways-regarding-gamification-to-increase-physical-activity
11. Rocha e Silveira, JBCS 2024 — https://journals-sol.sbc.org.br/index.php/jbcs/article/view/4310
12. Duolingo, sequência e hábito — https://blog.duolingo.com/how-duolingo-streak-builds-habit
13. Duolingo, aposta e amuleto — https://blog.duolingo.com/how-streaks-keep-duolingo-learners-committed-to-their-language-goals/
14. Finch, análise de design — https://screensdesign.com/showcase/finch-self-care-pet
15. FAQ do WhatsApp sobre apps não oficiais — https://faq.whatsapp.com/1217634902127718
16. Evolution API v2 + Cloud API — https://doc.evolution-api.com/v2/en/integrations/cloudapi
17. Meta, preços da WhatsApp Business Platform — https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing
18. Meta, categorização de templates — https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/template-categorization
19. AiSensy, mudança de 01/10/2026 (Brasil) — https://m.aisensy.com/blog/pt/atualizacao-preco-whatsapp-api-outubro-2026/
20. respond.io, mudança de 2026 — https://respond.io/blog/whatsapp-pricing-change-2026.md
21. Whapi.Cloud, conta após 01/10/2026 — https://whapi.cloud/blog/pt/how-to-calculate-whatsapp-api-bill-after-october-1-2026-with-examples
22. ChatMaxima, tarifas de serviço por país — https://chatmaxima.com/blog/whatsapp-service-message-pricing-october-2026/
23. Message Central, preços no Brasil — https://www.messagecentral.com/blog/whatsapp-business-api-pricing-in-brazil
24. WhatsApp Business Messaging Policy — https://whatsappbusiness.com/policy/
25. Meta, obtenção de opt-in — https://developers.facebook.com/documentation/business-messaging/whatsapp/getting-opt-in
26. AiSensy, limites de envio 2026 — https://m.aisensy.com/blog/whatsapp-message-limits-guide/
27. Meta, botões de resposta — https://developers.facebook.com/documentation/business-messaging/whatsapp/messages/interactive-reply-buttons-messages
28. Meta, componentes de Flows — https://developers.facebook.com/documentation/business-messaging/whatsapp/flows/guides/components.md
29. Meta, boas práticas de Flows — https://developers.facebook.com/documentation/business-messaging/whatsapp/flows/guides/bestpractices.md
30. Meta, Flows em templates — https://developers.facebook.com/documentation/business-messaging/whatsapp/flows/guides/flows-templates.md
31. IASP, Faces Pain Scale – Revised — https://www.iasp-pain.org/resources/faces-pain-scale-revised/
32. web.dev, instalação de PWA — https://web.dev/learn/pwa/installation
33. WebKit, Web Push no iOS 16.4 — https://webkit.org/blog/13878/web-push-for-web-apps-on-ios-and-ipados/
34. WebKit, Declarative Web Push — https://webkit.org/blog/16535/meet-declarative-web-push/

**Não confirmados (repetir as buscas):** tabela oficial em BRL e a mudança de 01/10/2026 na página da Meta · template de utilidade com resposta rápida + URL na mesma mensagem · Flow em template de utilidade · requisitos de Tech Provider/Embedded Signup · escala de dor preferida por idosos · sequência semanal do Strava · tratamento do dia perdido no Finch · iOS 26 e web apps · Background Sync no iOS · uso de API oficial por Vedius e Kynesia.
