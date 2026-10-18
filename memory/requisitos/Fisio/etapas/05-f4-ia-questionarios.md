# Etapa 05 — F4 Velocidade e Inteligência + Questionários

> Módulo Fisio (ADR 0437). Pesquisa feita em 2026-10-17. Toda afirmação factual tem URL na §7. "Não confirmado" quer dizer que procurei e não achei fonte. Números marcados **[estimativa]** são cálculo meu, não preço publicado.
> **Limite de pesquisa:** a cota de buscas na web acabou no meio da etapa. Por isso ficaram sem confirmação: preços da Mapi (ODI/NDI/KOOS), licença do ICIQ, licença de Lysholm/CAIT/PRWE para software e as referências das validações brasileiras, que citei de memória e marquei como "a conferir". Dá para fechar essas lacunas numa rodada curta de pesquisa.

---

## 1. Objetivo e meta de nota

| item da grade | hoje | Physitrack | meta F4 | condição para chegar lá |
|---|---|---|---|---|
| IA (velocidade de documentação e de montagem) | 6 | escriba de IA em acesso antecipado, só nos EUA e só em inglês | **8** | ditado PT-BR estruturado na Res. 414 + busca semântica + "repetir programa" |
| Questionários (PROMs) | 6 | 9 | **8** sem licença paga · **9** com licença QuickDASH + Mapi | 5 → 12–14 instrumentos, envio automático, MCID para o profissional |

**Objetivo da etapa:** cortar o tempo de documentação e de montagem do programa sem cruzar a linha da RDC 657/2022. Na prática, a IA **escreve o que o profissional disse** e **encontra o que o profissional pediu**, e não decide nada. Para os questionários, o objetivo é cobrir todas as regiões do corpo com instrumento validado em PT-BR e ter o reteste automático.

---

## 2. O que os melhores fazem: copiar e evitar

### 2.1 Escribas de IA e voz

| produto | o que faz | preço | PT-BR | leitura para nós |
|---|---|---|---|---|
| **Vedius** (BR) | prontuário por voz com IA e assistente por voz para consultar dados do paciente; alega aderência à Res. 414 | R$ 79,90/mês; R$ 69,90 por colaborador | sim | é o concorrente direto em voz. O site não diz onde o áudio é processado. A LGPD aparece só como "adequado" |
| **Kynesia** (BR) | evolução por voz ou texto, 50 laudos por IA/mês no Flow, **"IA diagnóstica com clusters"** e **"sugestão de testes ortopédicos"** | grátis / R$ 49,90 / R$ 89,90 | sim | copiar a evolução por voz. **Evitar** a "IA diagnóstica" e a sugestão de testes: pela P&R da Anvisa (P45, P57) isso tem cara de SaMD. Os links de Privacidade e Termos do site apontam para "#" |
| **Physitrack EMR** | escriba que redige a nota seção por seção durante o atendimento, sugestões de exercício que o profissional aprova antes de chegarem ao paciente, agente de tarefas administrativas; diz que a IA não decide e não inventa conteúdo | software US$ 41,99–59,99/usuário/mês; transcrição é add-on de US$ 19/mês com 100 notas (+US$ 5 a cada 25). No Brasil, R$ 118 (fundador) ou R$ 169 | **não**: ditado em outros idiomas é recurso futuro e o lançamento é só nos EUA (acesso antecipado desde 28/09/2026, cobrança a partir de 01/01/2027) | janela de 6 a 12 meses **[estimativa]** antes de chegar escriba PT-BR do Physitrack. A "sugestão de exercício com aprovação" é exatamente a P57 (SaMD classe II no Brasil, mesmo com aprovação humana) |
| **Heidi Health** | escriba ambiente, mais de 200 modelos de nota, "Ask Heidi", Heidi Evidence (fev/2026); não guarda áudio depois da transcrição | grátis (10 "Pro Actions"/mês); Clinician US$ 150/mês no anual (antes US$ 90). Outra fonte cita US$ 110 e US$ 180 | "110+ idiomas"; português não confirmado na fonte | modelo de **template por especialidade** e "áudio não fica guardado" vale copiar |
| **Nabla** | Copilot (nota SOAP em cerca de 5 s depois da consulta) + Dictation (voz no cursor, com dicionário próprio e atalhos); áudio processado no navegador e nunca guardado; nota e transcrição retidas 14 dias | não publica (a Commure cita US$ 119/mês); camada grátis com limite | "35+ idiomas", sem PT-BR confirmado | copiar o **dicionário personalizado** e a **retenção curta configurável** |
| **Abridge** | nota ligada ao trecho da transcrição e ao timestamp do áudio, detecção de alucinação, integração profunda com Epic | só enterprise: cerca de US$ 2.500–7.200+/clínico/ano (estimativa de terceiros); não atende consultório pequeno | 28+ idiomas | copiar a **rastreabilidade de cada frase até o trecho falado**. É a melhor defesa contra alucinação |

**Copiar:** (1) cada campo da nota ligado ao trecho do ditado que o originou (Abridge); (2) template por tipo de registro, aqui avaliação, evolução e alta da Res. 414 (Heidi); (3) dicionário de termos da clínica (Nabla); (4) áudio descartado depois da transcrição (Heidi, Nabla); (5) nada é gravado no prontuário sem a assinatura do profissional (todos).

**Evitar:** (1) "IA diagnóstica" ou sugestão de teste ou conduta (Kynesia; Physitrack fora dos EUA); (2) escriba **ambiente** que grava a consulta inteira. Ele exige consentimento de gravação, aumenta o volume de dado sensível e o custo, e o fisioterapeuta autônomo faz muita terapia manual, com a conversa misturada ao toque. Na F4 o modo é **ditado depois do atendimento** (30 s a 3 min); o ambiente fica para depois; (3) cobrar a IA como add-on caro, já que a âncora local é Kynesia/Vedius com voz incluída.

### 2.2 Questionários

O Physitrack chega a 9 com uma biblioteca grande de PROMs, envio pelo app e gráfico. Os locais (Kynesia Flow) citam "PROMs" sem lista pública. **Copiar:** envio programado, gráfico de evolução, relatório. **Evitar:** mostrar ao paciente a interpretação ("melhorou de forma clinicamente importante"). Ver §3.4.

---

## 3. Especificação melhorada

### 3.1 Ditado por voz para o prontuário (PT-BR)

#### 3.1.1 Fluxo

1. O profissional abre o registro (avaliação, evolução ou alta) e toca **Ditar**. Grava de 30 s a 5 min no navegador (MediaRecorder, opus/webm).
2. O áudio sobe para o backend, que transcreve com o motor configurado (§3.1.3).
3. Um LLM **reorganiza** a transcrição nos campos da Res. 414 (§3.1.4) e devolve JSON com, para cada campo, o texto e os **trechos de origem** (offsets na transcrição).
4. A tela mostra os campos preenchidos lado a lado com a transcrição. Cada campo tem um marcador "de onde veio" que destaca o trecho. Campo sem trecho de origem fica **vazio**, com o aviso "não ditado".
5. O profissional edita e **assina** (fluxo append-only da F1). Só a versão assinada vai para `fisio_records`.
6. O áudio é **apagado** assim que a transcrição termina (job com confirmação). A transcrição bruta fica vinculada ao rascunho por até 7 dias e some ao assinar, ou guarda-se só o hash. Recomendo não guardar a transcrição depois de assinado: o prontuário é o texto assinado.

#### 3.1.2 Qualidade em PT-BR com termos de fisioterapia

- Não achei benchmark público de ASR em **PT-BR clínico de fisioterapia** com números. O mais próximo: um trabalho da UFC (JIDM 2026) em transcrição médica PT-BR conclui que o Whisper supera o Wav2Vec2-PT e que uma correção pós-ASR com BERT melhora o resultado, mas não publica WER na página. O PROPOR 2026 (Ethos AT) comparou variantes do Whisper em PT e achou o **turbo** o melhor equilíbrio entre precisão e tempo, também sem WER na página.
- O WER do Whisper large-v3 em PT-BR limpo fica em torno de 5–6% e sobe para 7–10% em áudio real. Essa fonte é de um vendedor de produto Whisper e as figuras são em parte estimativas dele: usar só como ordem de grandeza.
- **Conclusão prática:** a escolha do motor deve sair de um **teste nosso**. São 30 ditados reais de fisioterapeutas (com consentimento), com termos como "manguito rotador", "LCA", "crepitação", "ADM", "Lasègue", "cinesioterapia", "TENS", "CREFITO", medindo WER geral e **taxa de acerto em termos do dicionário**. Custa uma tarde de engenharia e define o fornecedor (tarefa T-F4-02).
- **Dicionário da clínica:** passar a lista de termos como prompt ou keyterms para o ASR, quando suportado, e também para o LLM de estruturação, que pode corrigir grafia de termo técnico mas **não acrescentar conteúdo**.

#### 3.1.3 Motores: custo, local × API, LGPD

| motor | custo por minuto | onde roda | observações |
|---|---|---|---|
| OpenAI `gpt-transcribe` | US$ 0,0045 | EUA; residência de dados na Europa disponível para API | o `gpt-4o-mini-transcribe` sai por cerca de US$ 0,003 (estimado) e o `whisper-1` por US$ 0,006 |
| Deepgram Nova-3 (multilíngue, gravado) | US$ 0,0052 (PAYG) / US$ 0,0043 (Growth) | EUA | o Nova-3 **Medical** lista só **inglês**: não serve para PT-BR. Suporte a PT no Nova-3 genérico é mais recente e pouco testado |
| AssemblyAI Universal | US$ 0,15/h ≈ US$ 0,0025/min (streaming); Universal-3.5 Pro ≈ US$ 0,0075/min | EUA (UE disponível, não confirmado aqui) | WER PT-BR do Universal-2 em torno de 6–8% segundo publicação da própria AssemblyAI |
| Gemini 2.5 Flash (áudio como entrada) | 1.920 tokens/min × US$ 1/1M ≈ **US$ 0,0019/min** + saída; Flash-Lite ≈ US$ 0,0006/min **[estimativa a partir do preço por token]** | Google; Vertex permite região | transcreve e estrutura numa chamada só. Por isso é tentador, mas junta ASR e "interpretação" num passo menos auditável |
| Claude (Anthropic) | não aceita áudio nativamente | — | usar só na **estruturação** do texto: Haiku 5.5 a US$ 0,10/US$ 0,50 por 1M tokens; `inference_geo: "us"` +10%; ZDR e arranjo HIPAA disponíveis |
| **Whisper large-v3-turbo local** | custo de servidor | nosso servidor, **no Brasil** | elimina a transferência internacional. Exige GPU para latência boa; CPU serve para lote assíncrono **[estimativa]** |

**Custo por profissional [estimativa]:** 100 atendimentos/mês × 2 min de ditado = 200 min × US$ 0,0045 ≈ **US$ 0,90/mês** de ASR. A estruturação com Haiku 5.5 (cerca de 4k tokens de entrada e 1k de saída por nota) dá ≈ US$ 0,0009/nota, ou US$ 0,09/mês. **Total abaixo de US$ 1 por profissional por mês**: a IA pode vir **incluída no plano**, como fazem Kynesia e Vedius.

**LGPD: dado de saúde enviado a fornecedor estrangeiro**
- O ditado é dado pessoal **sensível** (art. 11). A base continua sendo tutela da saúde (art. 11, II, "f"). O oimpresso é operador e o fornecedor de ASR/LLM é **suboperador**: precisa constar no DPA com a clínica.
- Mandar para servidor fora do Brasil é **transferência internacional** (art. 33). Desde 23/08/2025, cláusula contratual só vale se for a **cláusula-padrão da ANPD** (Res. CD/ANPD 19/2024) ou específica aprovada. As SCCs europeias, sozinhas, não bastam.
- **União Europeia:** a ANPD publicou em 26/01/2026 a **Res. 32/2026**, que reconhece a UE como adequada. Processamento em **região UE** (OpenAI com residência europeia, Vertex/Gemini em região UE, AssemblyAI UE se confirmado) dispensa outro instrumento.
- **EUA:** não há adequação. Exige as cláusulas-padrão da ANPD no contrato com o fornecedor. Com OpenAI, Deepgram ou Google, é preciso confirmar se aceitam anexar as cláusulas da ANPD. **Não confirmado**; é tarefa jurídica (T-F4-01).
- **Consentimento do paciente como base da transferência (art. 33, VIII)** é frágil: revogável e precisa ser específico e destacado. Não usar como base principal.
- **Recomendação:** (1) padrão = ASR em **região UE** com retenção zero ou mínima e sem uso para treino; **ou** Whisper-turbo **local** se o teste T-F4-02 mostrar qualidade suficiente; (2) estruturação por LLM só com texto, sem nome nem CPF do paciente (pseudonimizar: o prompt recebe "Paciente", nunca o nome); (3) inventário de suboperadores visível para a clínica; (4) aviso ao paciente no consentimento de teleatendimento ou prontuário de que o registro pode ser ditado e transcrito por IA. O análogo médico é a Res. CFM 2.454/2026; para o COFFITO não achei exigência equivalente (**não confirmado**). Recomendo informar mesmo assim.

#### 3.1.4 Estruturação na Res. COFFITO 414: isso é SaMD?

**Não é**, desde que a função seja só documentação. Argumento pela P&R oficial da RDC 657/2022:
- **P26:** plataforma com agenda, prontuário eletrônico, registro de estado do paciente e comunicação, **inclusive assistente por voz com machine learning**, fica fora da RDC 185/2001 e da 657/2022 e não é dispositivo regularizável.
- **P44 / P71:** prontuário eletrônico puro não é SaMD, **salvo se acrescentar diagnóstico, triagem ou sugestão diagnóstica ou terapêutica automática**.
- **P34:** edição de texto de laudo e calculadora com dado digitado pelo profissional, sem função fisiológica, não é SaMD.
- **P67:** prognóstico tirado do diagnóstico do profissional, sem análise do software, é consulta; **prognóstico automático é SaMD**.

**Linha que o produto não cruza (vira teste automatizado):**
| permitido (documentação) | proibido (vira P44/P57/P67) |
|---|---|
| pôr nos campos da 414 o que foi **dito** (queixa, história, exame, diagnóstico e prognóstico **ditos pelo profissional**, plano, evolução) | preencher **diagnóstico fisioterapêutico** ou **prognóstico** que não foi ditado |
| corrigir a grafia de termo técnico pelo dicionário | sugerir teste, exame, conduta, número de sessões ou exercício |
| marcar "não ditado" em campo obrigatório vazio | inferir CID ou CIF, classificar risco, "alertar" sobre achado |
| resumo para o paciente **revisado e assinado** pelo profissional | resumo enviado automaticamente ao paciente |

Implementação: o prompt proíbe inferência e exige `source_spans` por campo; um validador rejeita campo com texto e sem trecho de origem; testes de regressão com ditados que **omitem** diagnóstico e prognóstico verificam que os campos voltam vazios.

### 3.2 Busca de exercícios por linguagem natural (sem virar recomendação de conduta)

**Base regulatória:**
- **P10:** banco de exercícios para o profissional escolher **não é SaMD**. Software que monta série a partir da dor relatada **é** classe II.
- **P46 / P65:** busca e cruzamento de literatura e banco de dados não é SaMD.
- **P58:** protocolo com a árvore toda visível é consulta; automatizado e opaco é SaMD.
- **P47:** software em que se escolhe protocolo de **tratamento** configurado pelo fornecedor pode ser SaMD. **Cuidado** com "busca por condição".
- **P57:** recomendar conduta, mesmo com aprovação humana, é classe II.

**Onde fica a linha:**
| busca (fica) | recomendação (não entra) |
|---|---|
| a consulta é **digitada pelo profissional** ("ponte unilateral sem carga", "exercício de ombro com elástico em pé") | o sistema monta a consulta a partir do prontuário, da dor ou do diagnóstico do paciente |
| o índice só usa **campos descritivos** do exercício: nome, região, posição, equipamento, tipo, passos, objetivo funcional descritivo ("fortalecer glúteo médio") | o índice inclui "indicado para lombalgia aguda" ou "fase 2 pós-LCA" e ranqueia por condição |
| resultado chama "Resultados da busca" e é ordenado por semelhança de texto, com "por que apareceu" (termos e campos que bateram) | título "Sugeridos para este paciente", "IA recomenda", score de adequação |
| nada entra no programa sem clique do profissional, um por um | "adicionar todos", programa pré-montado pela IA |
| o aviso de contraindicação vem da **etiqueta do exercício × marcador digitado pelo profissional** e só **avisa**, sem bloquear (análogo à P46, alerta de interação como cruzamento de literatura) | **bloquear** prescrição pelo diagnóstico (P68/P70 dizem que bloquear ação por diagnóstico é SaMD) |

> **Atenção para a F1 (T-06):** o alerta de contraindicação deve ser **aviso dispensável com justificativa**, nunca bloqueio. Se hoje o handoff prevê bloqueio, ajustar.

**Consulta com cara de diagnóstico** ("lombalgia o que passar"): a busca roda do mesmo jeito sobre os campos descritivos, mostra a faixa "A busca encontra exercícios pelo que eles são; a escolha clínica é sua" e **não** usa a palavra para filtrar por indicação. Levar este desenho à consulta de enquadramento na Anvisa junto com a câmera (a ADR já prevê consulta antes da F2).

**Técnica:**
- Embeddings multilíngues (ex.: `voyage-4-lite` ou `voyage-4-nano`, que tem pesos abertos Apache 2.0 e roda local; ou equivalente) sobre a ficha do exercício. São cerca de 40 a alguns milhares de exercícios: custo desprezível **[estimativa]**. Busca híbrida: BM25 (MySQL FULLTEXT ou Meilisearch) + vetor, com rerank opcional.
- Exercício próprio da clínica também é indexado, só no `business_id` dela.
- A query do profissional **não** sai com dado do paciente: não há paciente no contexto da busca.
- Log de busca sem dado do paciente, para medir "busca sem clique".

**Outras acelerações sem IA, que valem mais que a IA (copiar do MedBridge às avessas):**
- **"Repetir/adaptar programa anterior"** do mesmo paciente ou de outro (cópia editável).
- **Protocolos-modelo da clínica** (F1) com a lógica visível (P58).
- **Ditado para o programa**: "ponte, três de doze, segura cinco" vira item com séries, repetições e sustentação, mas **só para exercícios que o profissional nomeou** (busca + extração de parâmetros). É documentação do que ele disse, não sugestão.

### 3.3 Prescrição por IA como SaMD classe II: vale a pena?

**O que mudaria no produto e na empresa:**
| exigência | fonte | impacto |
|---|---|---|
| Classe II por regra de software/tratamento → **notificação** (não registro) | P10, P57; regime de notificação para classes I e II (RDC 423/2020; RDC 751/2022 segue o IMDRF) | processo administrativo, não estudo clínico obrigatório nos moldes do registro |
| **AFE** da empresa como fabricante de produtos para saúde + licença sanitária local, mesmo sendo só escritório | P55; Fazcomex | o oimpresso passa a ser empresa regulada pela Anvisa, com vigilância sanitária local. Taxa da AFE **não confirmada** |
| Boas Práticas de Fabricação (RDC 665/2022). O CBPF só é exigido no registro (classes III/IV), mas o sistema de qualidade se aplica | P55, P78 | SGQ: controle de projeto, gestão de risco (ISO 14971), ciclo de vida de software (IEC 62304), CAPA, reclamações |
| **Relatório de IA**: técnica, origem e quantidade dos dados de treino, descrição e justificativa estatística | P50, P55 | precisa de dataset clínico rotulado, com consentimento de pesquisa (ADR §7: desligado por padrão) |
| Rótulo e instruções de uso, tecnovigilância pós-mercado | P87 (lista das classes III/IV como referência); RDC 657 | cada versão do modelo vira mudança controlada; nada de "trocar o LLM na sexta-feira" |
| Configuração mais arriscada define a classe | P64 | se o motor puder ser configurado para algo mais arriscado, a classe sobe |

**Custo e prazo:**
- Taxa de notificação classe I/II: **R$ 1.406** (Pure Global). Registro III/IV: R$ 8.510–19.856. CBPF, se exigido: R$ 72.805.
- **Divergência com a etapa 03 §5 (refutação de 2026-10-18):** lá a taxa de notificação vem por porte da empresa (R$ 175,72 micro, R$ 351,43 pequena, até R$ 3.514,32 grande), a CBPF tem outro valor e a preparação total sai em 9–15 meses e R$ 110–320 mil. As duas etapas usam fontes diferentes e nenhum dos números foi conferido na tabela oficial da Anvisa. Antes de qualquer decisão da ADR 0437 §14 nº 7, conferir as taxas na fonte oficial e unificar aqui e na etapa 03.
- Análise da notificação: "semanas" (Pure Global).
- Preparação (SGQ, dossiê, gestão de risco, relatório de IA, validação): **6 a 12 meses e R$ 150–400 mil** entre consultoria regulatória, responsável técnico, validação clínica e horas de engenharia **[estimativa minha, sem fonte]**. Mais um custo recorrente de qualidade e tecnovigilância.
- Prestador como representante: a partir de US$ 2.000/ano (Pure Global), útil só para fabricante estrangeiro.

**Benefício:** o Physitrack só oferece sugestão de exercício nos EUA. Nenhum concorrente local mostra evidência pública de regularização. A Kynesia anuncia "IA diagnóstica" sem regularização visível (**não confirmado**). Seria diferencial, mas o ganho de tempo sobre "protocolo-modelo + busca + repetir programa" é marginal: montar um programa com busca e protocolo leva cerca de 1 minuto (promessa da ADR); a IA economizaria segundos.

**Recomendação: não fazer agora.** Ficar com **protocolos-modelo + busca + repetir programa + ditado para o programa**, que entregam cerca de 90% da velocidade **[estimativa]** com custo regulatório zero. Reavaliar quando houver: (a) pelo menos 500 clínicas pagantes (receita que absorva o SGQ) **[critério sugerido]**; (b) dataset próprio com consentimento de pesquisa; (c) o mesmo SGQ já montado para outra frente (câmera, se a consulta de enquadramento disser classe II). Fazer SGQ uma vez e usá-lo para câmera e prescrição é o único cenário em que vale. **Decisão de [W].**

### 3.4 Questionários: de 6 para 8–9

#### 3.4.1 Situação de licença

| instrumento | região | licença para software comercial | PT-BR | decisão |
|---|---|---|---|---|
| EVA/END | geral | domínio público | — | já na F1 |
| **RMDQ** | lombar | **domínio público**, sem permissão nem custo, original e traduções (SRALab) | Nusbaum et al. 2001 (a conferir) | já na F1 |
| **PSFS** | qualquer | SRALab: "Free"; termos de licença não descritos | Costa et al. 2008 (a conferir) | já na F1 |
| **LEFS** | membro inferior (quadril, joelho, tornozelo) | SRALab: "Free"; termos não descritos | Pereira et al. 2013 (a conferir) | já na F1 |
| **SPADI** | ombro | SRALab: "Free"; termos não descritos | Martins et al. 2010 (a conferir) | já na F1 |
| **PRWE** | punho e mão | SRALab: "Free"; termos não descritos | versão BR **não confirmada** nesta pesquisa | **F4-livre**, se houver versão BR validada e o autor (MacDermid) confirmar uso em software |
| **NDI** | cervical | **Mapi**: grátis para prática clínica; **pago** para organizações de saúde, usuários comerciais e **empresas de TI**. Valor não publicado | Cook et al. 2006 (a conferir) | **licenciar** (pedir cotação no ePROVIDE) |
| **ODI** | lombar | **Mapi**: grátis para clínico e acadêmico; comercial paga taxa "acessível", sem valor publicado | Vigatto et al. 2007 (a conferir) | **licenciar** junto com o NDI |
| **KOOS / HOOS** | joelho / quadril | koos.nu: grátis só para acadêmico, **permissão sempre exigida**; organização de saúde com fins lucrativos e comercial pagam, via Mapi | KOOS-BR a conferir | **licenciar** (mesma cotação Mapi) |
| **QuickDASH** | membro superior | **IWH**: categoria B (software comercial/web de uso repetido): **CAD 1.250** no 1º ano + **taxa por usuário** (até 500: CAD 625; até 1.000: CAD 1.150; até 2.000: CAD 2.120…); renovação CAD 650 + taxa por usuário | **sim**, versão oficial "Portuguese for Brazil" (Orfale, Araújo, Ferraz, Natour, UNIFESP) | **licenciar** se houver demanda de mão e cotovelo. Custo do 1º ano ≈ **CAD 1.875** até 500 usuários |
| **FAAM** | tornozelo/pé | autorização por escrito (ADR §8) | Moreira et al. 2016 (a conferir) | pedir autorização |
| **VISA-P / Tampa** | tendão patelar / cinesiofobia | autorização por escrito (ADR §8) | a conferir | pedir autorização |
| **Lysholm** | joelho | **não confirmado** | Peccin et al. 2006 (a conferir) | pesquisar licença |
| **CAIT** | instabilidade de tornozelo | **não confirmado** | versão BR a conferir | pesquisar licença |
| **ICIQ-SF / ICIQ-UI** | assoalho pélvico (incontinência) | site do ICIQ (Bristol) tem página de licenças; módulos só para membros. Termos comerciais **não confirmados** (não consegui ler a página) | Tamanini et al. 2004 (a conferir) | **não implementar** até ler a licença; o ICIQ costuma exigir licença para uso comercial (**não confirmado**) |
| **PFDI-20 / PFIQ-7** | assoalho pélvico | **não confirmado** | Arouca et al. 2016 (a conferir) | pesquisar licença |
| **STarT Back** | lombar (estratificação) | Keele: termos não publicados na página | tradução BR existe, a conferir | pedir licença. Atenção: estratificar risco pode encostar na P64 (triagem). Mostrar só ao profissional |
| **PROMIS** | genérico | licença conforme a plataforma; plataforma própria = taxa fixa da API do Assessment Center | PT não confirmado | fora da F4 |

**Leitura:** com custo zero dá para chegar a **RMDQ, PSFS, LEFS, SPADI, EVA + PRWE + FAAM + VISA-P + Tampa** (as quatro últimas dependem de e-mail com autorização) = 9–10 instrumentos cobrindo lombar, ombro, punho e mão, membro inferior, tornozelo e tendão. As lacunas são **cervical** (só NDI, que é Mapi), **joelho específico** (KOOS é Mapi; Lysholm sem licença confirmada) e **pelve** (ICIQ e PFDI sem licença confirmada).

#### 3.4.2 Caminho recomendado para 6 → 9

1. **F4-a (custo zero, cerca de 2 semanas):** motor de questionários genérico + PRWE, FAAM, VISA-P, Tampa (com autorização arquivada) + envio automático + MCID. **Nota 8.**
2. **F4-b (licença):** uma cotação Mapi para **ODI + NDI + KOOS (+HOOS)** e uma licença IWH para **QuickDASH** (≈ CAD 1.875 no 1º ano até 500 usuários). Com isso a cobertura por região fica completa e chega ao **9**. Decisão de [W] depois das cotações.
3. **Pelve:** só depois de ler a licença do ICIQ e do PFDI-20. Se forem caros, o PSFS cobre como instrumento paciente-específico.

> Conta de bolso **[estimativa]**: a licença QuickDASH a ~CAD 1.875/ano empata com cerca de 7 assinaturas anuais de R$ 49 a R$ 79/mês. Só vale quando o produto tiver base que peça mão e punho.

#### 3.4.3 Funcionalidades

- **Definição de instrumento versionada** (`fisio_instruments`): código, versão, idioma, itens, opções, regra de pontuação (soma, média, percentual, itens invertidos, regra de dados faltantes do manual), faixa, direção (maior = melhor ou pior), referência bibliográfica, **status de licença** (`livre`, `autorizado`, `licenciado`, `bloqueado`) com anexo da autorização e validade. Instrumento com licença vencida **some** do envio.
- **Texto literal** da versão BR validada, sem alteração (ADR §8), com a referência no rodapé da tela do paciente.
- **Envio automático** por WhatsApp e portal (link + PIN da F1), com regras por programa:
  - **início**: na criação do programa ou na avaliação;
  - **meio**: no dia N ou na sessão N (padrão: 4 semanas ou metade das sessões previstas no plano da Res. 414);
  - **alta**: ao marcar "alta" ou ao fim do plano;
  - lembrete 1× em 48 h; depois disso, o item vai para a fila "questionário pendente" do profissional.
- **PSFS** com as atividades escritas pelo paciente na 1ª aplicação e **repetidas** nas seguintes (regra do instrumento).
- **Resposta parcial** salva; tempo estimado na abertura ("cerca de 3 min").
- **Registro no prontuário:** cada questionário respondido gera um registro de telemonitoramento assíncrono (Res. 619/2025, padrão da F1).

#### 3.4.4 MCID só para o profissional

- O **escore bruto** e a **variação** aparecem no painel e na aba Questionários do profissional, com uma faixa "Δ ≥ MCID publicado (fonte, população)" quando aplicável.
- Valores com fonte, por população, guardados no instrumento (seleção que o profissional confirma). Exemplos com fonte SRALab: RMDQ 3,5 (Ostelo & de Vet) ou 5 pontos; LEFS 9 pontos (Binkley 1999; Stratford 2000 para ATQ/ATJ) ou 6 pontos no quadril; SPADI 8–13 conforme a população (13,2 em membro superior; 15,4 em manguito); NDI de 3,5 a 8,5 em escala de 0–50 conforme a condição; PSFS de 1,2 a 2,2 conforme a população; PRWE 11,5 total em fratura distal do rádio.
- O profissional escolhe a população de referência na 1ª aplicação; o sistema mostra o MCID daquela população e **cita a fonte**. Sem escolha, mostra o intervalo.
- **Paciente vê:** "Questionário respondido, obrigado". Opcionalmente, a curva do próprio escore **sem interpretação**, decisão do profissional por paciente, **desligado por padrão**. Motivo: P11 diz que acompanhamento periódico de dor/desconforto **para o próprio usuário** é SaMD classe I, e P79 diz que relatório que sugere diagnóstico ou discrepância de saúde vira classe I. Escore + MCID com rótulo "melhora importante" na tela do paciente seria exatamente isso. Para o profissional, é calculadora com dado não fisiológico (P34) e registro para avaliação dele (P11).
- O MCID **nunca** dispara mensagem automática ao paciente nem muda o programa.

---

## 4. Riscos e o que fica fora

| risco | mitigação |
|---|---|
| Alucinação na estruturação (campo inventado) | `source_spans` obrigatórios, validador, testes de omissão, assinatura humana obrigatória |
| Transferência internacional sem instrumento válido | região UE (Res. ANPD 32/2026) ou cláusulas-padrão da ANPD; alternativa Whisper local; suboperadores no DPA |
| Fornecedor usar dado para treino ou reter áudio | contrato com retenção zero ou mínima e sem treino; áudio apagado por job com confirmação; teste que verifica a remoção |
| Busca ser lida como "recomendação" | índice só descritivo, rótulos, sem dado do paciente, consulta de enquadramento à Anvisa junto com a câmera |
| Licença de questionário vencer ou ser violada | status de licença com validade; bloqueio automático; texto literal |
| Qualidade do ASR ruim com termo técnico | teste T-F4-02 antes de escolher; dicionário; fallback para digitação |
| Custo de IA explodir | < US$ 1/profissional/mês **[estimativa]**; limite mensal por business, com alerta |

**Fora da F4:** escriba ambiente (gravar a consulta inteira); prescrição ou sugestão de exercício por IA (classe II, §3.3); STarT Back com estratificação mostrada ao paciente; ICIQ, PFDI, Lysholm e CAIT até a licença ser lida; PROMIS; qualquer interpretação de escore para o paciente; treino de modelo com dado de clínica (exige consentimento de pesquisa, ADR §7).

---

## 5. Métricas de sucesso

| métrica | meta |
|---|---|
| Tempo mediano para assinar uma evolução (abrir → assinar) | −50% contra digitação (medir antes e depois) |
| % das evoluções feitas por ditado entre quem ativou | ≥ 40% em 60 dias |
| % dos campos estruturados editados antes de assinar | ≤ 20% (proxy de qualidade) |
| Campos sem trecho de origem aceitos pelo validador | **0** (teste) |
| WER no conjunto de teste PT-BR fisio / acerto em termos do dicionário | ≤ 10% / ≥ 95% para aprovar o motor |
| Tempo para montar o programa (abrir editor → enviar) | ≤ 60 s na mediana |
| Buscas com clique em algum resultado | ≥ 70% |
| Questionário de reteste respondido (meio e alta) | ≥ 60% dos enviados |
| Pacientes com par início + alta do mesmo instrumento | ≥ 50% das altas |
| Custo de IA por profissional ativo/mês | ≤ US$ 1,50 |

---

## 6. Tarefas para o Claude Code (ordem de dependência)

| id | tarefa | blocked_by | DoD |
|---|---|---|---|
| T-F4-00 | Admissão da F4 pelo funil (ADR 0368) e decisão de [W] sobre motor UE × local e sobre licenças Mapi/IWH | F1 entregue | aceite textual registrado |
| T-F4-01 | Jurídico/LGPD: DPA com suboperadores, cláusulas-padrão da ANPD ou região UE confirmada por fornecedor, texto de aviso ao paciente | T-F4-00 | contrato ou termos anexados; inventário de suboperadores na tela da clínica |
| T-F4-02 | Banco de teste de ASR: 30 ditados reais com consentimento + transcrição de referência; script de WER e acerto em termos; rodar Whisper-turbo local, `gpt-transcribe` UE, AssemblyAI e Gemini | T-F4-00 | relatório com WER/termos/custo/latência; motor escolhido |
| T-F4-03 | `TranscriptionService` com driver configurável (Prism quando couber), upload de áudio, job de transcrição, **remoção do áudio** com confirmação | T-F4-01, T-F4-02 | teste: áudio não existe depois do job; falha do driver cai em erro legível |
| T-F4-04 | `ClinicalNoteStructurer`: prompt sem inferência, JSON com `source_spans`, validador, pseudonimização (sem nome nem CPF no prompt), dicionário da clínica | T-F4-03 | testes: ditado sem diagnóstico → campo vazio; campo sem trecho → rejeitado; nome do paciente nunca no payload |
| T-F4-05 | UI "Ditar" em avaliação, evolução e alta: gravação, campos com destaque de origem, "não ditado", edição, assinatura no fluxo append-only | T-F4-04 | casos.md; nada é gravado em `fisio_records` sem assinatura; ui:lint verde |
| T-F4-06 | Ditado para o programa: extração de séries, repetições e sustentação **só para exercícios nomeados** que existem na biblioteca | T-F4-04, T-F4-07 | teste: exercício não nomeado nunca entra |
| T-F4-07 | Busca híbrida da biblioteca (FULLTEXT/BM25 + embeddings sobre campos descritivos), "por que apareceu", rótulo "Resultados da busca" | T-F4-00 | teste: índice não contém campo de indicação clínica; busca não recebe `patient_id`; nenhum texto "recomendado/sugerido" na UI |
| T-F4-08 | "Repetir/adaptar programa" (do paciente e de outro paciente) | F1 T-06 | cópia editável, origem registrada |
| T-F4-09 | Revisar F1 T-06: alerta de contraindicação = aviso dispensável com justificativa, **sem bloqueio** | — | teste: prescrição prossegue com justificativa registrada |
| T-F4-10 | Motor de questionários: `fisio_instruments` versionado, regras de pontuação e faltantes, status e validade de licença, migração dos 5 da F1 para o motor | F1 T-11 | pontuação dos 5 bate com os casos de referência; licença vencida esconde o instrumento |
| T-F4-11 | Obter autorizações (FAAM, VISA-P, Tampa, PRWE) e arquivar; implementar com texto BR literal | T-F4-10 | anexo da autorização no registro do instrumento; pontuação conferida |
| T-F4-12 | Envio automático início/meio/alta por WhatsApp e portal, lembrete de 48 h, fila "pendente", registro de telemonitoramento | T-F4-10, F1 T-07/T-09 | idempotente (1 envio por gatilho); registro de prontuário gerado |
| T-F4-13 | MCID para o profissional: tabela de MCID por instrumento e população com fonte, seleção da população, faixa Δ ≥ MCID; **nada disso na tela do paciente** | T-F4-10 | teste: endpoint e tela do portal não expõem escore interpretado nem MCID |
| T-F4-14 | (condicional a [W]) Licenças Mapi (ODI/NDI/KOOS/HOOS) e IWH (QuickDASH): contratos e implementação | T-F4-10, cotações | contrato anexado; contagem de usuários para a taxa IWH |
| T-F4-15 | Métricas da §5 no painel interno (tempo para assinar, % ditado, % edição, custo de IA por business) | T-F4-05, T-F4-07, T-F4-12 | números batem com os eventos de teste |
| T-F4-16 | Fecha o loop no SPEC (`Implementado em:`) | todas | `verificado@sha7` |

---

## 7. Fontes

**Transcrição e IA**
- Preços OpenAI (gpt-transcribe, whisper-1, mini): https://diyai.io/ai-tools/speech-to-text/openai-whisper-api-pricing-2026/ ; página oficial (faixa de US$ 0,003 a US$ 0,034/min, +10% em residência regional): https://developers.openai.com/api/docs/pricing
- Deepgram Nova-3: https://diyai.io/ai-tools/speech-to-text/deepgram-pricing-2026/ ; Nova-3 Medical só em inglês: https://www.callmissed.com/en/models/deepgram-nova-3-medical
- AssemblyAI: https://dailyaifixs.com/blog/assemblyai-pricing-2026-the-per-hour-truth
- Gemini (preço do token de áudio): https://www.morphllm.com/gemini-api-pricing ; 32 tokens/s de áudio: https://ai.google.dev/gemini-api/docs/audio
- Claude: preços e `inference_geo`: https://platform.claude.com/docs/en/about-claude/pricing ; retenção, ZDR e HIPAA: https://platform.claude.com/docs/en/manage-claude/api-and-data-retention ; embeddings (Voyage, voyage-4-nano aberto): https://platform.claude.com/docs/en/build-with-claude/embeddings ; sem áudio nativo: https://sonix.ai/ai/can-claude-transcribe-audio/
- WER em PT (fonte de vendedor, usar com cautela): https://vexascribe.com/pt/blog/comparacao-precisao-transcricao
- ASR médico PT-BR (UFC, JIDM 2026): https://journals-sol.sbc.org.br/index.php/jidm/article/view/5737
- Whisper em PT, turbo (PROPOR 2026): https://preview.aclanthology.org/new-sigs/2026.propor-1.30/

**Concorrentes**
- Physitrack EMR (escriba, preços, só EUA): https://www.physitrack.com/pt/emr
- Comparativo BR (Vedius, Kynesia, ZenFisio), 04/10/2026: https://www.physitrack.com/pt/insights/melhor-software-fisioterapia-brasil-comparativo
- Kynesia: https://www.kynesia.com.br/ · Vedius: https://vedius.com.br/
- Heidi: https://www.veroscribe.com/blog/heidi-health-review-2026 ; Nabla, Heidi, Freed, DAX: https://commure.com/blog-scribe/scribe-pricing ; Nabla: https://www.commure.com/blog-scribe/nabla-ai-review ; Abridge: https://www.veroscribe.com/blog/abridge-review-2026
- Escribas no Brasil, LGPD e CFM 2.454/2026: https://www.trytwofold.com/pt-br/blog/escribas-com-ia-no-brasil ; CFM 2.454/2026: https://medicinasa.com.br/ia-diagnostico-medico-cfm/

**LGPD**
- Res. CD/ANPD 19/2024, prazo 23/08/2025: https://lefosse.com/noticias/transferencia-internacional-de-dados-prazo-para-adocao-das-clausulas-padrao-da-anpd-se-encerra-em-23-de-agosto/
- Adequação UE, Res. ANPD 32/2026: https://www.mattosfilho.com.br/unico/protecao-dados-perspectivas/
- LGPD: https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm

**Anvisa**
- P&R RDC 657/2022 (P10, P11, P24, P26, P34, P44–P47, P50, P55, P57, P58, P64, P65, P67, P68, P70, P71, P78, P79, P87): https://www.gov.br/anvisa/pt-br/assuntos/noticias-anvisa/2022/software-como-dispositivo-medico-perguntas-e-respostas/perguntas-respostas-rdc-657-de-2022-v1-01-09-2022.pdf/@@download/file
- Notificação para classe II (RDC 423/2020): https://www.mattosfilho.com.br/unico/noticias/tomada-publica-softwares-medicos
- Taxas e prazos (notificação R$ 1.406, CBPF R$ 72.805, semanas): https://www.pureglobal.com/pt/devices/samd
- AFE: https://www.fazcomex.com.br/blog/o-que-e-o-autorizacao-de-funcionamento-afe-da-anvisa/

**Questionários**
- IWH DASH/QuickDASH, preços: https://dash.iwh.on.ca/licences ; FAQ (uso sem fins lucrativos grátis, software vendido exige licença): https://dash.iwh.on.ca/faq ; tradução BR: https://dash.iwh.on.ca/available-translations
- ODI (Mapi): https://www.mapi-trust.org/news-events/news/the-oswestry-disability-i ; licenciamento Mapi: https://mapi-trust.org/services/questionnaire-licensing
- KOOS: http://www.koos.nu/
- NDI (licença e MCID): https://www.sralab.org/rehabilitation-measures/neck-disability-index
- RMDQ (domínio público, MCID): https://www.sralab.org/rehabilitation-measures/roland-morris-disability-questionnaire
- LEFS: https://www.sralab.org/rehabilitation-measures/lower-extremity-functional-scale
- SPADI: https://www.sralab.org/rehabilitation-measures/shoulder-pain-and-disability-index
- PSFS: https://www.sralab.org/rehabilitation-measures/patient-specific-functional-scale
- PRWE: https://www.sralab.org/rehabilitation-measures/patient-rated-wrist-evaluation
- ICIQ (site; licença não lida): https://iciq.net/
- PROMIS: https://www.healthmeasures.net/explore-measurement-systems/promis/obtain-administer-measures
- STarT Back: https://keele.health/startbackvideos/
- Oxford Knee Score (licença por pedido): https://innovation.ox.ac.uk/outcome-measures/oxford-knee-score-oks/
- **Validações BR citadas como "a conferir"** (de memória, sem URL verificada nesta etapa): RMDQ (Nusbaum 2001), PSFS (Costa 2008), LEFS (Pereira 2013), SPADI (Martins 2010), NDI (Cook 2006), ODI (Vigatto 2007), FAAM (Moreira 2016), Lysholm (Peccin 2006), ICIQ-SF (Tamanini 2004), PFDI-20 (Arouca 2016).
