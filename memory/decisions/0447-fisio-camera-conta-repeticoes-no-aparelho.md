---
slug: 0447-fisio-camera-conta-repeticoes-no-aparelho
number: 447
title: "Fisio: a câmera conta repetições, ritmo e tempo parado no celular do paciente, sem medir ângulo e sem enviar imagem; lançamento depende da consulta de enquadramento à Anvisa"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-11-04"
module: fisio
quarter: 2026-Q4
tags: [fisio, camera, anvisa, samd, lgpd, dado-sensivel, mediapipe, dependencia-nova, f2]
related_adrs: [0437-modulo-fisio-vertical-fisioterapia, 0383-ponto-interno-nao-coleta-biometria, 0368-funil-admissao-feature-pesquisa-propoe-w-admite, 0438-artefato-substitui-cowork-como-superficie-do-prototipo]
supersedes: []
supersedes_partially: []
superseded_by: []
pii: false
review_triggers:
  - "a Anvisa responder a consulta de enquadramento (qualquer resposta reabre esta ADR)"
  - "a revisão da RDC 657/2022 (minuta de fev/2025) ser publicada no DOU"
  - "[W] decidir notificar o software como dispositivo médico classe II"
  - "mais de 25% dos aparelhos dos pacientes abaixo do piso de 6 Hz na telemetria"
---

# ADR 0447 · Fisio: câmera que conta repetições no aparelho

> **Status `proposto`.** Nada aqui vale até [W] aceitar. Número 0447: o `next-id.mjs adr` deu 0446 em 2026-11-04, mas a ADR do Lote 6 da Fisio (teleconsulta e ditado, #10326) ficou com 0446, combinado entre as sessões.
> **Esta ADR não repete a especificação.** O dono técnico do tema é [`memory/requisitos/Fisio/etapas/03-f2-camera.md`](../requisitos/Fisio/etapas/03-f2-camera.md) (pesquisa de 2026-10-17: pilha, algoritmo, enquadramento, nota da série, privacidade, dados, tarefas C-00 a C-16). Esta ADR decide o que lá ficou para decisão: se a F2 entra, com qual dependência, sob que linha regulatória e em que ordem.

## Contexto

A ADR 0437 (aceita em 2026-10-18) previu a câmera como F2, com três travas: "câmera só conta repetições, ritmo e tempo; sem ângulo, sem 'corrigir'" (§5), "consulta de enquadramento antes" (§5 e §9) e "processamento no aparelho; só métricas sobem" (§7). Em 2026-11-04 o canvas Prescreve Pro entrou no git (#10321) com cinco telas da câmera, que pela ADR 0438 são a fonte do design:

| tela | o que mostra |
|---|---|
| `CameraLiberacao.dc.html` | painel da clínica: câmera liberada exercício por exercício, critério "90% das séries com contagem exata em 30 voluntários", pré-requisito "consulta de enquadramento à Anvisa", e o quadro de decisão "Plano classe II" |
| `PacienteCameraPreparo.dc.html` | 3 passos: "Fica no seu celular" / "Vale o mesmo" / "Você confirma", botões "Testar no modo avião" e "Ver o que é enviado", posição a ~2 m na diagonal, checagem de enquadramento |
| `PacienteCamera.dc.html` | a série: repetição 5/8, "ritmo ok", nota parcial, "Saiu do quadro? A contagem pausa. No fim, você confirma o número." |
| `PacienteCameraClasse2.dc.html` | ângulo 72° contra meta 80–90° e "Desça menos"; a própria tela diz "Só com notificação classe II na Anvisa. Não entra na F2." |
| `PacienteAcesso.dc.html` | consentimento separado "Câmera para contar repetições: liga só quando você escolher. O vídeo não sai do seu celular e não é gravado." |

Duas coisas já existem em produção: o consentimento `camera` (versão `v1` em `Modules/Fisio/Config/config.php`, gravado e revogável por `Modules/Fisio/Services/Consentimentos.php`, testado em `FisioPortalLinkTest`) e o texto dele no portal (`Public/_components/Entrada.tsx`). A câmera em si não existe, e `package.json` não tem nenhuma biblioteca de visão computacional. Uma dependência nova sem ADR é proibida no repo (`memory/proibicoes.md` §Código).

### Análise prévia (pre-adr-introspect, 2026-11-04)

- **Dono do tema:** `etapas/03-f2-camera.md`. Esta ADR aponta para ele e não abre paralelo.
- **Padrão parecido:** ADR 0383 (o ponto interno não coleta biometria). Mesmo espírito: o que identifica o corpo não sai do aparelho.
- **Prior art externa:** contagem de repetições por pose no aparelho é técnica publicada (máquina de estados sobre pontos do corpo; estudo Dotmoovs, JMIR mHealth 2026, citado na etapa como fonte 1) e tem ferramenta madura (MediaPipe Pose Landmarker, Apache-2.0). Kaia Motion Coach e Hinge TrueMotion fazem o mesmo no celular, com correção de forma, que aqui fica de fora. Construir o contador é a parte nossa; o detector de pose é adotado, não escrito.
- **Diagnóstico de produção:** não se aplica; não há dado de câmera.

## Pergunta 1 · Regulatório: contar repetições faz o software virar dispositivo médico?

**Fontes, lidas em 2026-11-04:** Perguntas & Respostas oficial da RDC 657/2022, versão de 01/09/2022 (Anvisa), perguntas 50, 60, 83 e 128; busca sobre a revisão da norma.

**O que a Anvisa já respondeu, literalmente:**

- **Q50.** Um app que faz "avaliação estática ou dinâmica através de imagens com uso de inteligência artificial" para fisioterapeutas, com pontos anatômicos por rede neural, ângulos e classificação do desvio, "se enquadra na classe de risco II pela regra 10". Exige análise clínica (RDC 548/2021) e relatório da IA (técnica, bases de treino, justificativa estatística).
- **Q60.** Jogo de exercício baseado em movimento corporal "será SaMD caso o fabricante faça a indicação do produto para tratamentos (em material publicitário ou instruções de uso etc.)". O enquadramento depende das alegações do fabricante.
- **Q83 e Q128.** Em dúvida, a empresa consulta a Anvisa pelo formulário eletrônico ou pelo Parlatório, com o formulário de enquadramento de software previsto no art. 19 da RDC 657.

**Estado da norma:** a RDC 657/2022 segue vigente. A Anvisa publicou em fevereiro de 2025 uma minuta de revisão que trata de software com IA e de planos de controle de mudança; não achei publicação final no DOU até 2026-11-04. O próprio P&R ainda cita a RDC 185/2001 e a RDC 40/2015, revogadas pela RDC 751/2022; em divergência valem as normas atuais.

**A leitura desta ADR (não é parecer jurídico):**

| condição | enquadramento provável | por quê |
|---|---|---|
| Conta repetições, marca o ritmo prescrito e cronometra a sustentação; o paciente confirma o número; o resultado é lido como **adesão** ("quanto você seguiu o combinado"); nenhuma frase de tratamento | **fora de SaMD, ou zona cinzenta** | não avalia postura nem movimento (diferença para a Q50); não indica tratamento (Q60); o profissional continua decidindo tudo (ADR 0437 §5) |
| Mede ângulo, amplitude ou desvio e mostra ao paciente ou ao profissional | **SaMD classe II** | é a Q50 quase palavra por palavra |
| Diz ao paciente para mudar a execução ("desça menos", "joelho para dentro", "corrija") | **SaMD classe II** | é avaliação dinâmica com retorno ao usuário (Q50) e passa a orientar a execução |
| Anuncia que a câmera trata, reabilita, previne lesão ou melhora a dor | **SaMD**, mesmo só contando | a Q60 põe o enquadramento nas alegações |

A contagem usa uma rede neural que localiza pontos do corpo, e é aí que a linha fica fina: a Q50 descreve exatamente essa técnica, só que com outra finalidade. Por isso a zona cinzenta não se resolve por leitura nossa. **A consulta de enquadramento (tarefa C-00 da etapa) vem antes do lançamento, como a 0437 já mandava.**

**Textos das telas que empurrariam para o lado errado:**

| onde | texto | problema | o que fazer |
|---|---|---|---|
| `PacienteCameraClasse2` | "72°", "meta 80°–90°", "Desça menos", "correção" | Q50 inteira | **a tela não vai para produção.** Fica no canvas como ilustração do plano classe II |
| `PacienteCamera` | chip "ritmo ok" | julga a velocidade do paciente; a etapa §3.2 marca isto como ponto cinzento a levar à consulta, e até a resposta fica só o metrônomo | trocar por "no tempo combinado" só depois da resposta; antes disso, só o metrônomo falado e a contagem |
| `PacienteCamera` | "Nota parcial · combo 88 ×1,5" | não é regulatório: um multiplicador na série com câmera contraria a 0437 §13.4 ("a série com câmera vale o mesmo XP da série marcada à mão") | o combo, se existir, vale igual com ou sem câmera; confirmar com [W] no PR da tela |
| qualquer material comercial | "trata", "corrige postura", "previne lesão", "avalia o movimento" | Q60 e 0437 §5 | lista proibida da etapa §3.2, com teste que falha se aparecer (tarefa C-08) |

Ficam, porque descrevem o que a câmera faz e não o corpo do paciente: "A câmera conta por você", "Saiu do quadro? A contagem pausa", "No fim, você confirma o número", "Dica da sua fisio" (texto estático escrito pela profissional, que a 0437 §5 já admite).

## Pergunta 2 · Técnica: pose no aparelho, sem enviar vídeo, e o custo no celular

A etapa (§3.1) já mediu e decidiu a pilha; esta ADR só formaliza a dependência.

- **Dependência nova:** `@mediapipe/tasks-vision` (Google, Apache-2.0), versão fixada no `package.json` sem `^` (0.10.35 era a última vista em maio de 2026; conferir `npm view` no PR). Modelo `pose_landmarker_lite.task` (~5,8 MB) e runtime WASM (~1,5 MB) **servidos pelo próprio domínio**, nunca por CDN: é o que permite o CSP `connect-src 'self'` e a promessa "funciona em modo avião".
- **Plano B, não adotado agora:** MoveNet Lightning por LiteRT.js. Só entra se o spike de bancada (C-01) mostrar o MediaPipe abaixo do piso em Android de entrada, e entra por emenda a esta ADR, não por PR solto. Dois runtimes ao mesmo tempo, não.
- **Custo no celular do paciente**, pelos números da etapa: num topo de linha, a pose na CPU do navegador ficou em ~9 quadros/s; num Galaxy A15 a estimativa é 4 a 7 na CPU e 10 a 15 com GPU, **sem medição nossa**. Uma repetição de reabilitação leva 2 a 6 s, então 6 quadros/s bastam. Abaixo de 6 Hz o aparelho fica "câmera indisponível" e o paciente segue pelo check-in manual, sem perder XP. Download uma vez (~8 MB, sob demanda, com aviso). Bateria e aquecimento: **não há medição publicada**; o spike mede 10 minutos em 3 aparelhos.
- **Hostinger × CT 100:** tudo roda no navegador do paciente; o servidor só serve arquivos estáticos e recebe um JSON de números. Nenhum processo novo em servidor (ADR 0062 intacta).
- **Sem app nativo.** O portal continua sendo link + PIN (0437 §2); o gatilho para reabrir está na etapa §3.1.

## Pergunta 3 · LGPD: imagem do corpo e consentimento por finalidade

- **O que é dado sensível aqui.** A imagem do paciente fazendo exercício, ligada a um tratamento, revela dado referente à saúde (LGPD art. 5º, II). A imagem como biometria é discutida (a ANPD abriu tomada de subsídios sobre dados biométricos em 2025 e há doutrina dos dois lados), e por isso esta ADR trata a imagem e os **pontos do esqueleto** como sensíveis: o padrão de movimento pode identificar a pessoa.
- **Decisão de desenho que encolhe o problema:** imagem e pontos não saem do aparelho e não são gravados. O que sobe são números (repetições detectadas e confirmadas, duração de cada repetição, tempo sustentado, confiança, Hz do aparelho, versões do modelo e do algoritmo). Isso é verificável: sem `MediaRecorder`, sem `toBlob`/`toDataURL` sobre o vídeo, CSP restrito na rota, teste e2e que intercepta a rede e prova 0 bytes de imagem (etapa §3.6, tarefa C-11).
- **Base legal:** a 0437 §7 põe o atendimento em tutela da saúde (art. 11, II, "f"), mas a câmera fica em **consentimento específico e destacado** (art. 11, I), separado dos outros, como o protótipo desenha e como o código já grava. Motivo: é opcional, o paciente pode fazer tudo sem ela, e a finalidade é distinta. Revogou, o botão some; nada gravado a apagar.
- **Texto do consentimento:** o protótipo diz "O vídeo não sai do seu celular e não é gravado"; o que está em produção diz "A imagem fica no seu celular; só a contagem sobe". Os dois são verdadeiros só depois do teste de rede da C-11 passar. Unificar no texto do protótipo e subir a versão para `camera: v2` no mesmo PR da tela, porque a mudança de texto pede novo aceite.
- **Validação com voluntários (tarefa C-14):** os vídeos dos 30 voluntários são gravados **fora do produto**, com consentimento de pesquisa próprio, e viram fixtures de números. Se essa validação for pesquisa com seres humanos (Res. CNS 466/2012) é pergunta para a Dra. Dejeane e para a consultoria, ainda não respondida.

## Opções

| opção | o que é | prós | contras |
|---|---|---|---|
| **A · não fazer agora** | F2 parada até o piloto da F1 provar adesão | zero risco regulatório e de suporte; foco na F1 | perde o "uau" de demonstração (0437 §2, alavanca 8); a consulta à Anvisa demora e começaria tarde |
| **B · F2 restrita, consulta primeiro** (recomendada) | mandar a consulta de enquadramento já; desenvolver contagem, ritmo e sustentação no aparelho atrás de flag desligada; nenhum paciente usa até a resposta | a consulta corre em paralelo ao código; o núcleo de contagem serve mesmo se a resposta for "classe II" (versões rastreadas, base do relatório da IA); custo baixo | trabalho pode ter de mudar com a resposta; exige disciplina de texto em toda tela |
| **C · notificar classe II já** | dossiê, IEC 62304, ISO 14971, IEC 62366-1, análise clínica, AFE | libera ângulo, correção e a tela `PacienteCameraClasse2` | 9 a 15 meses e custo alto (estimativa na etapa §5.3, sem orçamento); trava o ritmo de entrega; a 0437 §14 nº 7 já recomendou decidir só depois do piloto |
| **D · serviço de visão na nuvem ou SDK de terceiro** | mandar vídeo a um servidor que conta | celular fraco não importa | quebra a promessa "o vídeo não sai do celular", vira transferência de dado sensível, CSP impossível; recusada |

## Decisão (proposta)

**Opção B.**

1. A F2 da câmera entra pelo funil da ADR 0368 com o escopo da etapa `03-f2-camera.md`: contar repetições, marcar o ritmo prescrito, cronometrar a sustentação, nota da série como adesão. Sem ângulo, amplitude, correção, risco de queda ou sugestão de troca.
2. **A consulta de enquadramento à Anvisa (C-00) é enviada antes do primeiro PR de tela**, por [W] com consultoria, com as funções, entradas, saídas, telas e a lista de frases permitidas e proibidas. A resposta é registrada nesta ADR.
3. **Nenhum paciente usa a câmera antes da resposta.** Flag por business, desligada por padrão; ligar é ato de [W] depois da resposta e da validação por exercício (C-14).
4. Dependência adotada: `@mediapipe/tasks-vision`, versão fixa, modelo e WASM no próprio domínio. Segunda biblioteca de pose só por emenda.
5. Imagem e pontos do esqueleto nunca saem do aparelho nem são gravados, com prova automática na CI.
6. A tela `PacienteCameraClasse2` não vai para produção. O quadro "Plano classe II" de `CameraLiberacao` pode ir, como informação para [W] dentro da área da clínica.
7. Se a Anvisa responder que a contagem é SaMD classe II, a F2 para no estado em que estiver e a decisão volta a [W] entre notificar (opção C) ou arquivar.

## PRs que sairiam desta ADR (estimativa: 4)

Cada um depois do aceite; a C-00 (consulta) e a C-14 (voluntários) não são código e correm por fora.

| PR | conteúdo | tarefas da etapa | bloqueio |
|---|---|---|---|
| 1 | Dependência `@mediapipe/tasks-vision` fixada, modelo e WASM servidos pelo domínio, página de bancada isolada (sem rota de paciente) e tabela de medição em 3 a 4 Android e 1 iPhone | C-01, C-02, C-03 | aceite desta ADR |
| 2 | Núcleo de contagem em TypeScript puro (sinal 1D, histerese, sustentação, ritmo, confiança) com testes de fixtures JSON, inclusive taxa variável de 5 a 30 Hz | C-04, C-05 | PR 1 |
| 3 | Migration `fisio_camera_sets` (append-only, `business_id`), campos `tempo` e `camera_profile`, API idempotente de métricas, nota da série no PHP, lint e CSP contra envio de imagem, teste de rede com 0 bytes de imagem | C-06, C-10, C-11, C-12 | PR 2 |
| 4 | Telas do paciente (`PacienteCameraPreparo`, `PacienteCamera`) e da clínica (`CameraLiberacao`), áudio pré-gravado, lista de frases proibidas com teste, consentimento `camera: v2`, flag por business desligada | C-07, C-08, C-09, C-13 | PR 3 e resposta da Anvisa para ligar a flag |

Telemetria de campo (C-15) e lançamento controlado (C-16) ficam para depois da resposta. Cada PR deve caber em ~300 linhas; o PR 3 e o PR 4 provavelmente se dividem em dois.

## Consequências

**Positivas:** a consulta à Anvisa começa já, que é o item mais lento; a promessa de privacidade passa a ser testada, não só escrita; a câmera cabe no portal sem app e sem servidor novo.

**Negativas / trade-offs:** cerca de 8 MB de download no celular do paciente na primeira vez; Android de entrada pode ficar sem câmera; parte do trabalho pode ser refeita conforme a resposta da Anvisa; toda frase de tela passa por uma lista proibida.

**Riscos que continuam abertos:** a Anvisa pode enquadrar a contagem como classe II pela Q50; desempenho e bateria em Android de entrada não foram medidos; o enquadramento ético da validação com voluntários; a revisão da RDC 657 pode mudar as regras de software com IA.

## Fontes

- Anvisa, Perguntas & Respostas da RDC 657/2022, v1 de 01/09/2022, Q50, Q60, Q83, Q128 (lido em 2026-11-04): https://www.gov.br/anvisa/pt-br/assuntos/noticias-anvisa/2022/software-como-dispositivo-medico-perguntas-e-respostas/perguntas-respostas-rdc-657-de-2022-v1-01-09-2022.pdf/@@download/file
- Formulário de enquadramento de software (Q128): https://www.gov.br/anvisa/pt-br/centraisdeconteudo/publicacoes/produtos-para-a-saude/manuais
- Minuta de revisão da RDC 657 (fev/2025), segundo a ABIMED: https://legismap.com.br/conteudos/artigos-e-noticias/noticias-abimed-em-21-02-2025 · apresentação da Anvisa na Câmara, 05/08/2025: https://www2.camara.leg.br/atividade-legislativa/comissoes/comissoes-permanentes/cssf/apresentacoes-em-eventos/eventos-2025/05-08-2025-audiencia-publica-regulacao-da-inteligencia-artificial-no-setor-saude-beneficios-e-riscos-1/apresentacao-anvisa/view
- `@mediapipe/tasks-vision` no npm (Apache-2.0): https://npmjs.com/package/@mediapipe/tasks-vision
- ANPD, tomada de subsídios sobre dados biométricos (2025): https://www.gov.br/participamaisbrasil/ts-dados-biometricos
- LGPD, arts. 5º, 11 e 18: https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm
- As fontes técnicas (MediaPipe, MoveNet, LiteRT.js, benchmarks, estudo Dotmoovs, Kaia, Hinge) estão em `memory/requisitos/Fisio/etapas/03-f2-camera.md` §8, pesquisa de 2026-10-17; não foram relidas nesta ADR.
