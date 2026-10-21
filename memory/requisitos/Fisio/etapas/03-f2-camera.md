# Etapa F2 — Câmera: contar, marcar o ritmo e cronometrar a sustentação, no aparelho e sem medir ângulo

> Módulo Fisio · ADR 0437 §5, §6, §7, §9 · redigido em 2026-10-17
> Convenção: **[fonte N]** remete à §7. **[estimativa]** é número meu, sem fonte, para ser medido. **[não confirmado]** é o que procurei e não achei.
> Limitação desta pesquisa: a cota de buscas na web acabou antes do fim. Ficaram sem confirmação: suporte a WebGPU em GPUs Mali de entrada, consumo de bateria medido, preço de consultoria regulatória no Brasil e a exigência de responsável técnico para fabricante de software. Esses pontos estão marcados no texto.

---

## 1. Objetivo da etapa e meta de nota

**Objetivo.** Com o celular apoiado no chão, o paciente faz a série e o portal (PWA, sem instalar nada):
1. **conta as repetições** em voz alta;
2. **marca o ritmo** que o fisioterapeuta prescreveu, como um metrônomo falado;
3. **cronometra a sustentação** e só conta o tempo em que o paciente está parado na posição;
4. calcula a **nota da série (0–100)**, que mede adesão ao prescrito e não avalia a qualidade clínica do movimento;
5. faz tudo isso **sem enviar imagem**: só sobem números.

**Linha regulatória (ADR §5).** Nada de ângulo articular, amplitude de movimento (ADM), "postura errada", "corrija", risco de queda ou sugestão de troca de exercício. A Q50 do Perguntas & Respostas da RDC 657 classifica como SaMD classe II um app que localiza pontos anatômicos por rede neural, calcula ângulos e classifica desvios. Ele exige análise clínica e relatório de IA [fonte 13]. A F2 fica deliberadamente aquém disso, e a §5 descreve o caminho para ir além, se [W] decidir.

**Nota na grade: hoje 6 → meta 8 sem notificar; 9 com a classe II notificada.**
- Hinge e Sword têm nota 10 porque medem movimento em 3D, corrigem a forma em tempo real e têm equipe clínica olhando os dados [fonte 7, 8, 11]. Kaia e MedBridge também dão feedback corretivo [fonte 5, 9]. Nada disso cabe na linha sem notificação.
- Chegamos a 8 com o que eles não têm ou fazem pior para o nosso público: **zero instalação** (link + PIN), **prova de privacidade** que o paciente consegue verificar (funciona em modo avião), **contagem validada por exercício** (a câmera só liga onde passou no teste) e **metrônomo falado em português** ligado ao prescrito.
- A classe II (§5) libera ADM e feedback de forma, o que leva a 9. O 10 pede 3D com sensores e uma equipe clínica própria, que está fora do modelo de negócio.

---

## 2. O que os melhores fazem: o que copiar e o que evitar

| player | o que faz com a câmera | copiar | evitar (ou só com classe II) |
|---|---|---|---|
| **Kaia Motion Coach** | Roda no próprio celular com a câmera frontal [fonte 6]. Dá feedback falado e corrige a execução, conta repetições e cronometra a sustentação [fonte 5]. Não funciona em todo exercício nem em todo aparelho (iOS 16.4+, Android 9+, e nem todo Android) [fonte 5b]. Só grava vídeo com consentimento explícito antes do exercício [fonte 6b]. | Áudio como canal principal; contar e cronometrar; liberar a câmera exercício por exercício e aparelho por aparelho; gravação sempre opcional (aqui: nunca gravar). | "Feedback corretivo" é a função da Q50: classe II. |
| **Hinge Health (TrueMotion)** | Mais de 100 pontos de referência no corpo, visualização 3D e feedback falado. A equipe clínica vê o progresso [fonte 7, 8]. Diz que não grava nem guarda dados da sessão sem consentimento [fonte 8]. Usa câmera e sensores vestíveis [fonte 7]. | O fisioterapeuta vê o resultado da série no painel; frase de privacidade simples. | 3D, medidas de movimento, HingeScore de "melhora" (alegação clínica). |
| **MedBridge** | Portal do paciente no celular ou no computador, só com a câmera. Analisa ADM, aponta problemas, sugere correção de posição, sinaliza risco de queda e recomenda exercícios [fonte 9]. Comprou a Rehab Boost [fonte 10]. | Funcionar no navegador, sem app. | ADM, correção, risco de queda e recomendação: tudo SaMD (P10, P50, P57). |
| **Sword Health** | Patente US 12.456.204 de rastreamento de corpo inteiro por visão computacional com feedback em tempo real. Combina câmera com sensores vestíveis [fonte 11]. | — | Modelo B2B com sensor físico. |
| **Exer Health** | App iOS de fisioterapia com IA [fonte 12]. Detalhes técnicos [não confirmado]. | — | — |
| **Estudo Dotmoovs (JMIR mHealth 2026)** | MoveNet no aparelho, classificador de estados e cadeia de Markov. Melhor resultado no agachamento em diagonal a 200 cm (95,5% de séries corretas, erro médio de 0,05 repetição). Pior na lateral a 90 cm (0%). Na média geral, apenas cerca de 61% de séries corretas [fonte 1]. | **Posição do celular guiada**: diagonal ou frontal, a 180–200 cm. | Prometer acerto sem guiar o enquadramento: a média de 61% é o que acontece sem guia. |

**Lição central.** O que derruba a contagem é o enquadramento, mais do que o modelo [fonte 1]. O produto precisa tratar o "onde ponho o celular" como parte do exercício, com checagem automática, e recusar a contagem quando o enquadramento estiver ruim, em vez de contar errado. Um vídeo de contagem errada viraliza contra o produto (ADR §2, erro 2).

---

## 3. Especificação melhorada

### 3.1 Pilha técnica: PWA com MediaPipe no aparelho

**Decisão: MediaPipe Tasks Vision (Pose Landmarker *lite*) no navegador, com MoveNet Lightning como plano B. Sem app nativo na F2.**

| item | dado | fonte |
|---|---|---|
| Pacote | `@mediapipe/tasks-vision` via npm; modos `IMAGE`/`VIDEO`; `numPoses`, limiares de detecção, presença e rastreamento (padrão 0,5); máscara de segmentação desligável | [fonte 2] |
| Saída | 33 pontos com coordenadas normalizadas e coordenadas de mundo; entrada de 256×256 para os pontos | [fonte 3] |
| Modelo lite | `pose_landmarker_lite.task` = **5,78 MB** | [fonte 4] |
| Modelos antigos (BlazePose TF.js, 2021) | lite ≈ 10,6 MB, heavy ≈ 34,9 MB; runtime WASM de 1,57 MB carregado ao criar o detector; MediaPipe mais rápido que TF.js na maioria dos aparelhos | [fonte 14] |
| MoveNet Lightning | 17 pontos; 2,9 MB (INT8) a 4,8 MB (FP16); 25–28 ms na GPU e 52–60 ms na CPU de um Pixel 5 (TFLite nativo, não navegador) | [fonte 15] |
| Medição real no navegador | Pose lite, CPU, em worker, num Pixel 10 Pro XL (topo de linha), Chrome: **78,6 ms de inferência, só 8,7 poses/s** | [fonte 16] |
| GPU × CPU | Num teste em desktop do mantenedor, a GPU deu ~42–50 FPS contra ~28 FPS na CPU | [fonte 17] |
| LiteRT.js (jul/2026) | Sucessor do TF.js para `.tflite` no navegador: WebGPU (ML Drift), XNNPACK na CPU, WebNN experimental. Até 3× mais rápido que outros runtimes web (medido num Mac M4) | [fonte 18] |
| WebGPU | Disponível no Chrome e no Edge, e no Safari desde a versão 26 (macOS, iOS, iPadOS). Muitas funções de pose rodam bem a 7–10 FPS | [fonte 19] |

**Leitura para Android de entrada.** O fato que importa: mesmo num topo de linha, a pose na CPU do navegador ficou em ~9 Hz [fonte 16]. Num Galaxy A15 (Helio G99, Mali-G57), o modelo mais lembrado na lista de mais vendidos de 2025 [fonte 20], a CPU dará menos que isso **[estimativa: 4–7 Hz]**. Se o delegate de GPU funcionar, a meta é **10–15 Hz [estimativa]**. A Samsung tem 40% do mercado brasileiro, a Motorola 24% e a Xiaomi 16% [fonte 21].

Isso basta para o nosso uso, por desenho:
- Na reabilitação, uma repetição leva de 2 a 6 s. Com 8 Hz são 16 a 48 amostras por repetição, muito mais do que o necessário para detectar picos e vales.
- A sustentação é um cronômetro de "parado ou não", que funciona bem com 5 Hz.
- Por isso o algoritmo (3.2) é **independente da taxa de quadros**: trabalha com carimbo de tempo, não com contagem de quadros.

**Regras de execução** (todas medíveis, ver C-01):
- Delegate GPU com fallback automático para CPU. O nível do aparelho é medido nos primeiros 3 s: Hz alcançado e latência (p95).
- Vídeo da câmera pedido em **640×480 [estimativa]**. O modelo trabalha em 256×256 [fonte 3], então resolução maior só gasta bateria.
- Inferência num **Web Worker** com `OffscreenCanvas`, como no teste da fonte 16, e um quadro por vez: quadro novo descarta o anterior, nunca forma fila.
- **Piso de 6 Hz**: abaixo disso o aparelho é marcado como "câmera indisponível" e o paciente segue com o check-in manual da F1, sem perder nada no jogo.
- **Wake Lock** para a tela não apagar. Câmera desligada entre séries e no descanso.
- **Bateria**: não achei medição publicada [não confirmado]. Atenuantes: sessões curtas (5–15 min), resolução baixa, câmera desligada no descanso. Se o Hz cair mais de 30% durante a sessão (sinal de aquecimento), a taxa alvo baixa para 6 Hz. Medir no C-01 o consumo de bateria por 10 minutos em três aparelhos.

**Download em 4G.** São cerca de 6 MB de modelo mais cerca de 1,5 MB de runtime WASM [fonte 4, 14]. A velocidade média geral das operadoras no 4T2025 ficou entre 42 e 51 Mbps [fonte 22], o que dá poucos segundos na teoria; com sinal fraco, bem mais **[estimativa]**.
- O download acontece **uma vez**: o modelo fica no Cache Storage do service worker, com versão no nome.
- O download é **sob demanda**, na primeira vez que o paciente toca em "usar câmera" (nunca no primeiro acesso ao portal), com aviso "≈ 8 MB, uma vez só; prefira Wi-Fi".
- O modelo e o WASM são **servidos pelo próprio domínio do oimpresso**, não por CDN de terceiros: isso sustenta a prova de privacidade (3.6) e o CSP.

**Por que não app nativo agora.**

| | PWA (escolhido) | Nativo (Flutter/React Native com MediaPipe/ML Kit) |
|---|---|---|
| Atrito | link + PIN, igual à F1 | instalar app: é a reclamação nº 1 contra Wibbi, MedBridge e PT Wired (ADR §2) |
| Desempenho | GPU via WebGL/WebGPU, ~8–15 Hz em entrada [estimativa] | acesso direto a GPU/NPU, tipicamente 2–3× mais Hz [estimativa]; os números de TFLite nativo do Pixel 5 (25 ms) dão a ordem de grandeza [fonte 15] |
| Mais ganhos do nativo | — | câmera em segundo plano, voz offline garantida, notificação push sem depender do WhatsApp |
| Custo | um código, mesmo deploy do Laravel/Inertia | dois builds, lojas, revisão de política de saúde nas lojas |

**Gatilho para reabrir a decisão:** mais de 25% dos aparelhos dos pacientes abaixo do piso de 6 Hz (telemetria C-15), **ou** classe II notificada, com 3D e feedback de forma pedindo mais Hz.

### 3.2 Algoritmo de contagem: sinal 1D, máquina de estados com histerese e autocorrelação para conferir

**Princípio regulatório aplicado ao código:** o algoritmo trabalha com **distâncias normalizadas e posições relativas entre pontos**, adimensionais. Ele **não calcula ângulo articular**. Nenhum valor intermediário é exibido, salvo ou comparado com norma clínica. A amplitude serve só como régua da própria pessoa para decidir "subiu e desceu".

**Perfil de câmera por exercício** (`fisio_exercises.camera_profile`, json, definido e validado pela equipe):
```json
{
  "enabled": true, "validated_version": "cnt-1.0",
  "view": "diagonal", "distance_cm": [180, 220], "posture": "em_pe",
  "required_landmarks": [23,24,25,26,27,28],
  "signal": { "kind": "vertical_ratio", "a": [23,24], "b": [27,28], "norm": "torso" },
  "direction": "down_first",
  "min_rep_s": 0.8, "max_rep_s": 12,
  "hold": { "phase": "bottom", "still_tol": 0.04 }
}
```

**Pipeline, a cada pose recebida (com carimbo de tempo `t`):**
1. **Portão de qualidade:** todos os pontos exigidos com visibilidade ≥ 0,6. Senão o quadro é "inválido" e não entra no sinal. Mais de 1,5 s inválido pausa a contagem e o áudio pede para voltar ao quadro.
2. **Sinal 1D:** por exemplo, a distância vertical quadril–tornozelo dividida pelo comprimento do tronco (ombro–quadril). Dividir pelo tronco tira o efeito da distância até o celular.
3. **Suavização:** filtro One Euro, ou média móvel exponencial com α adaptativo ao Δt. Funciona com taxa variável.
4. **Calibração pessoal:** nas 2 primeiras repetições guiadas pelo metrônomo ("vamos fazer duas juntos"), registra o mínimo e o máximo do sinal da pessoa. A amplitude A = máx − mín vale **só para esta sessão** e não é salva.
5. **Máquina de estados com histerese:** `TOPO → DESCENDO → FUNDO → SUBINDO → TOPO`. Entra em FUNDO quando o sinal cruza 35% de A (a partir do lado de início) e só volta ao TOPO ao cruzar 75%. As duas faixas evitam contagem dupla por tremor.
6. **Regras de repetição válida:** ciclo completo; duração entre `min_rep_s` e `max_rep_s`; pontos visíveis em ≥ 80% do ciclo; período refratário de 0,4 s.
7. **Conferência por autocorrelação:** a cada 10 s, autocorrelação do sinal na janela para estimar o período dominante. Se o número de repetições implícito pelo período divergir da máquina de estados em mais de 1, a série vira **"baixa confiança"**.
8. **Saída:** `reps_detected`, duração de cada repetição, `confidence` (alta, média ou baixa) e `invalid_ratio`.

**Honestidade na tela.** Ao fim da série: "Contamos **10**. Confere?" com os botões **[Sim]** e **[−] [+]**. O número confirmado pelo paciente vale para o jogo. A diferença em relação ao detectado é a **métrica de erro em campo** (C-15). Com baixa confiança, a pergunta vem sempre, e a nota mostra "contagem confirmada por você".

**Sustentação (isometria).** Na fase definida em `hold.phase`, o cronômetro corre enquanto |velocidade do sinal| < `still_tol`·A por segundo **e** o sinal fica na faixa da fase. Uma tolerância de 0,5 s absorve microajustes. Passou disso, o cronômetro pausa e o áudio diz "segura… pode voltar à posição". **Não** se diz "corrija". Contagem regressiva falada nos últimos 5 s.

**Ritmo.** O fisioterapeuta prescreve o tempo de cada fase (ex.: 2 s descendo, 1 s parado, 2 s subindo). O metrônomo falado conduz ("desce… dois… segura… sobe… dois"). Uma repetição está **no ritmo** se sua duração d ficar dentro de ±30% do prescrito P (|d − P| / P ≤ 0,3). Sem ritmo prescrito, não existe métrica de ritmo.

**Frases permitidas** (áudio e tela): números, "segura", "pode voltar", "descanso", "próxima série", "no ritmo", "acompanhe a contagem", "não estou vendo seus pés, dê um passo para trás".
**Frases proibidas:** qualquer referência a amplitude ("desça mais"), forma ("joelho para dentro", "costas retas"), qualidade ("movimento errado") ou conduta ("pare", "troque").
**Ponto cinzento, a levar à consulta de enquadramento:** "um pouco mais devagar para acompanhar o ritmo combinado". Até a resposta da Anvisa, fica só o metrônomo, sem comentário sobre a velocidade do paciente.

### 3.3 Enquadramento guiado

1. **Cartão por exercício** antes de começar: ilustração do celular **no chão, deitado ou apoiado num objeto**, na vista pedida (diagonal ou frontal), a **1,8–2 m**, que foi a faixa de melhor resultado no estudo [fonte 1]. Nunca de lado e perto (pior caso: 0% a 90 cm [fonte 1]).
2. **Checagem automática ao vivo**, com silhueta translúcida na tela e voz:
   - corpo inteiro: todos os `required_landmarks` visíveis, e altura do corpo entre 50% e 90% da tela → "dê um passo para trás", "chegue mais perto";
   - centralização: centro do quadril entre 30% e 70% da largura;
   - **uma pessoa só**: `numPoses: 2`; se aparecer uma segunda pessoa → "parece que tem mais alguém no quadro";
   - **luz**: luminância média de um quadro de 32×32 pixels; abaixo do limiar → "acenda uma luz ou vire-se para a janela"; contraluz forte → aviso;
   - **celular parado**: se houver `DeviceMotionEvent`, variação acima do limiar → "apoie o celular";
   - **vista certa**: para "diagonal", a razão entre largura dos ombros e largura do quadril no quadro dentro da faixa do perfil [estimativa, calibrar no C-14].
3. Tudo verde por 1 s → "Perfeito. Vamos começar em 3, 2, 1".
4. Durante a série, a checagem continua. Se falhar por mais de 1,5 s, a contagem pausa e o áudio chama de volta.
5. **Deitado, sentado e no chão:** só liberar depois de validados (C-14). O estudo mostra que exercício no chão é mais difícil, com mais oclusão [fonte 1]. Na primeira onda, só exercícios **em pé e sentado**.

### 3.4 Áudio e experiência

- **Áudio pré-gravado em pt-BR** (números de 1 a 30, comandos, metrônomo) em Opus, **≈ 300–600 KB no total [estimativa]**, em cache. A voz sintética do Android varia de aparelho para aparelho e pode nem existir em pt-BR. O `speechSynthesis` fica só como reserva.
- Tela grande e simples, legível a 2 m: número da repetição gigante, anel do cronômetro de sustentação, barra de série, tokens do DS v6 (roxo 295 = progresso). Sem esqueleto desenhado por padrão, para não sugerir "medição"; um toque mostra o "ponto de presença" só para ajudar o enquadramento.
- **Modo sem câmera sempre a um toque**: o paciente nunca fica preso. A câmera é bônus de nota, nunca condição de XP (ADR §6).
- **Pausa de segurança da F1 tem prioridade**: com dor ≥ limiar no "Como foi hoje", a câmera nem é oferecida no dia.

### 3.5 Nota da série (0–100): fórmula explicável

Vale para cada série feita com câmera, comparada com o **prescrito** no `fisio_program_items`.

```
R = min(reps_validas / reps_prescritas, 1)                       # repetições
T = reps_no_ritmo / reps_validas                                  # ritmo (só se houver ritmo prescrito)
S = média por repetição de min(sust_alcançada / sust_prescrita, 1) # sustentação (só se houver)

Pesos base: R 0,50 · T 0,25 · S 0,25
Sem ritmo prescrito: o peso de T vai para R.  Sem sustentação: o peso de S vai para R.
Nota = round(100 × (wR·R + wT·T + wS·S))
```

- Fazer **além** do prescrito não aumenta a nota (o mínimo com 1 corta), em linha com "não premiar volume" (ADR §6).
- `reps_validas` usa o **número confirmado** pelo paciente. Com baixa confiança, T e S só contam as repetições que o algoritmo viu com confiança, e a nota leva o selo "contagem confirmada por você".
- **Explicação na tela**, sempre por partes: "Repetições 10 de 10 · Ritmo 8 de 10 no tempo · Sustentação 26 s de 30 s → **86**".
- **Exemplo:** prescrito 10 reps, 2-1-2 s, sustentação de 3 s. Feito: 10 reps, 8 no ritmo, média de 2,6 s sustentados. R = 1; T = 0,8; S = 0,867. Nota = 100 × (0,5 + 0,2 + 0,217) = **92**.
- **Leitura de adesão, não de saúde:** rótulo "quanto você seguiu o combinado". Sem vermelho; abaixo de 60 aparece "vamos tentar de novo amanhã". No painel do profissional, a nota aparece ao lado da série e nunca entra em ranking.
- **Jogo:** a série com câmera dá **o mesmo XP** da série marcada à mão. Uma nota ≥ 80 dá a missão diária bônus "Série no ritmo". Assim a câmera não vira obrigação nem pressão por volume.

### 3.6 Privacidade: 100% no aparelho, e como provar ao paciente

**Garantias técnicas (testadas em CI, C-11):**
- O código do portal **não usa** `MediaRecorder`, nem `canvas.toBlob`/`toDataURL` sobre o vídeo, nem upload de `Blob`/`File` na rota da câmera. Uma regra de lint (grep em CI) quebra o build se aparecerem.
- **CSP** da rota da câmera: `connect-src 'self'` e `img-src 'self' data:`. **Permissions-Policy** `camera=(self)`. Modelo e WASM do próprio domínio.
- O único POST é o JSON de métricas (3.7). **Pontos do esqueleto também não sobem**: o padrão de movimento pode identificar a pessoa, e o espírito da ADR 0383 é não coletar biometria.

**Prova para o paciente (diferencial de demonstração):**
1. Tela "**O que a câmera faz**", em 3 linhas: "A imagem fica no seu celular. Não gravamos nem enviamos vídeo. Só enviamos a contagem e o tempo."
2. Botão "**Teste você mesmo: ligue o modo avião**": com o modelo em cache, a sessão roda **inteira offline** e as métricas sobem quando a rede voltar. É a prova que qualquer pessoa entende.
3. "**Ver o que foi enviado**": mostra o JSON exato da última série, em linguagem simples.
4. **Indicador de câmera** do sistema mais um selo próprio: "Processando no seu celular".
5. Consentimento **"câmera"** separado e versionado em `fisio_consents`, já previsto na ADR §7 (o handoff da F1 não o lista). É revogável no portal; revogou, o botão some.
6. Recomendação para depois [estimativa de valor]: publicar o módulo `fisio-camera` como código aberto, para auditoria. Decisão de [W].

### 3.7 Dados

**Nova tabela `fisio_camera_sets`** (append-only, `business_id` em tudo):

| campo | tipo | nota |
|---|---|---|
| business_id, patient_id, program_item_id, checkin_id | fk | checkin único por paciente e data (F1) |
| set_index | int | |
| started_at, duration_s | | |
| reps_prescribed, reps_detected, reps_confirmed | int | a diferença é métrica de erro |
| reps_in_tempo, tempo_prescribed (json), rep_durations_s (json, só números) | | |
| hold_prescribed_s, hold_achieved_s (json por repetição) | | |
| confidence | enum alta/média/baixa | |
| invalid_ratio, fps_avg, device_tier, delegate (gpu/cpu) | | telemetria técnica |
| model_version, algorithm_version, camera_profile_version | string | rastreabilidade (base para a classe II, se vier) |
| score, score_breakdown (json R/T/S/pesos) | | derivado, recalculável |

**Alterações:**
- `fisio_program_items`: `tempo` (json: descida, pausa e subida em s), opcional;
- `fisio_exercises`: `camera_profile` (json, 3.2);
- `fisio_consents.purpose`: incluir `camera`.

O check-in da F1 passa a referenciar as séries feitas com câmera. O registro de telemonitoramento no prontuário (Res. 619) ganha um resumo: "3 séries com contagem automática confirmada pelo paciente, nota média 88".

**Painel do profissional:** colunas "séries com câmera (7 dias)", "nota média da série" e "diferença contada × confirmada". Na ficha do paciente, um gráfico de **adesão** (nota por dia). **Não** mostra amplitude, ângulo nem forma.

### 3.8 Integrações
- Jogo da F1 (ledger: razão nova `serie_no_ritmo` para a missão bônus).
- Fila de atenção da F1: "baixa confiança repetida em 3 dias", que sugere ao profissional rever o enquadramento com o paciente.
- O projeto de estúdios de pilates (ADR §11) reaproveita o módulo `fisio-camera` como pacote interno.

---

## 4. Riscos e o que fica fora

| risco | mitigação |
|---|---|
| A Anvisa entende que **qualquer IA sobre vídeo de movimento** é SaMD (Q50), mesmo só contando | **Consulta de enquadramento antes do lançamento** (ADR §5), com funções, entradas, saídas e telas descritas (Q55, Q58, Q68 [fonte 13]). Texto sem alegação de tratamento: a Q60 diz que jogo de exercício só vira SaMD se o fabricante indicar tratamento [fonte 13]. O desenvolvimento pode seguir; **o lançamento depende da resposta**. |
| Contagem errada em vídeo viral | Câmera só nos exercícios validados (C-14); o paciente confirma o número; baixa confiança é declarada. |
| Android de entrada abaixo do piso | Nível medido nos primeiros 3 s; caminho sem câmera idêntico em XP; telemetria com gatilho para reabrir o nativo. |
| WebGPU/GPU ausente ou instável em Mali | Delegate com fallback para CPU; [não confirmado] o suporte a WebGPU no Chrome Android para GPUs Mali de entrada; testar no C-01. |
| iOS Safari | WebGPU desde o Safari 26 [fonte 19]; o MediaPipe Tasks no iOS precisa ser testado (o runtime de 2021 não suportava iOS Safari [fonte 14]). |
| Bateria e aquecimento | 3.1; sessão curta; medição no C-01. |
| Expectativa de "corrigir" vinda do profissional | Material comercial explica a linha; a classe II fica como opção futura (§5). |

**Fora da F2, sem classe II:** ângulo, ADM, comparação com a norma, feedback de forma, risco de queda, sugestão de progressão, alerta ao paciente baseado na câmera, gravação de vídeo (nem com consentimento: não é preciso para contar e aumenta o risco LGPD), esqueleto 3D, exercício deitado ou no chão antes de validar, ranking.

---

## 5. Plano Anvisa: se [W] decidir notificar a classe II para ir além

### 5.1 O que a classe II libera
- **Câmera:** ADM e ângulo, feedback de forma ("desça mais", "joelho alinhado") e relatório de movimento para o profissional. É a Q50: classe II, com análise clínica conforme a RDC 548/2021 e relatório de IA [fonte 13].
- **Dor:** sugerir ao paciente parar ou trocar o exercício (P10, ADR §5).
- **Prescrição por IA (F4):** motor que recomenda conduta, mesmo com aprovação humana. Na Q57, classe II por notificação [fonte 13].
- Recomendação: **uma única notificação** que cubra um componente "Fisio Clínico" separado do ERP (fronteira de versão e de deploy própria). Assim, mudanças no ERP não disparam petição. Mudanças de função clínica exigem petição; correção de bug e atualização de segurança não exigem [fonte 23].

### 5.2 Rito e exigências (o que confirmei)

| item | o que diz a fonte |
|---|---|
| Rito | RDC 751/2022 (em vigor desde 01/03/2023, substituiu a RDC 185/2001): **classes I e II = notificação**; III e IV = registro [fonte 24]. As Q10 e Q57 citam o rito de notificação da RDC 40/2015 [fonte 13]; confirmar a norma vigente com a consultoria. |
| Empresa | A peticionante precisa de **AFE** na atividade adequada; **CBPF** (certificado de boas práticas) só é exigido no rito de **registro** [fonte 13, Q55]. Manter a notificação depende de **cumprir** as BPF [fonte 24]. Leitura: para a classe II, o certificado não é exigido, mas o sistema de qualidade (RDC 665/2022) é. Confirmar. |
| Responsável técnico, licença sanitária local da sede | [não confirmado] para fabricante de software; levantar com a consultoria. |
| Dossiê de SaMD | Arquitetura de software e de hardware, plataforma, compatibilidade e interoperabilidade, **cibersegurança**, verificação e validação, **gerenciamento de risco**, anomalias residuais, **avaliação clínica**, declaração de conformidade com normas. É a lista da Q87, escrita para as classes III e IV; usar como teto [fonte 13]. Instruções de uso com atualização, requisitos mínimos, princípio de funcionamento, alertas e cibersegurança; menus de preferência em português [fonte 23]. |
| Normas | A consulta pública que originou a RDC 657 exigia conformidade com **IEC 62304** (ciclo de vida do software), **IEC 62366-1** (usabilidade) e **ISO 14971** (risco) [fonte 25]. Se o texto final as cita nominalmente: [não confirmado], pois não consegui ler a íntegra; na prática, são as normas que o dossiê da Q87 pede para declarar. |
| IA | **Relatório de IA**: técnica e racional, origem, quantidade e descrição dos dados de treino, justificativa estatística do tamanho das bases [fonte 13, Q50]. Se usarmos o modelo pré-treinado do MediaPipe sem retreino, o relatório precisa documentar o modelo de terceiro e **o nosso conjunto de validação** (C-14 vira a base disso). |
| Prazo da Anvisa | Notificação classe I/II: **30–45 dias** após a documentação completa, segundo consultoria (média, sem garantia) [fonte 26]. |
| Taxas | Notificação classe I/II: **R$ [redacted Tier 0]** (micro), **R$ [redacted Tier 0]** (pequena), até R$ [redacted Tier 0] (grande). AFE de indústria: **R$ [redacted Tier 0]** (micro), **R$ [redacted Tier 0]** (pequena), até R$ [redacted Tier 0] (grande). CBPF (se exigido): R$ [redacted Tier 0] para micro e pequena. Empresa sem porte comprovado é cobrada como grande. Tabela de consultoria com rodapé de 2026; confirmar a vigente [fonte 27]. |

### 5.3 Custo e prazo totais [estimativa minha, sem fonte: validar com 2–3 orçamentos]

Não achei preço público de consultoria regulatória brasileira para SaMD [não confirmado]. A única referência é uma consultoria internacional que anuncia representação no Brasil "a partir de US$ 2.000/ano" [fonte 26], que não inclui o dossiê.

| bloco | prazo | custo |
|---|---|---|
| Diagnóstico regulatório + consulta de enquadramento formal | 1–2 meses | R$ [redacted Tier 0] (consultoria) |
| AFE de fabricante + licença sanitária + estrutura de qualidade (RDC 665: procedimentos de projeto, controle de mudanças, reclamações, tecnovigilância) | 3–6 meses | R$ [redacted Tier 0] (consultoria + horas internas) |
| Documentação de IEC 62304 (classe de segurança do software), ISO 14971, IEC 62366-1 (estudo de usabilidade com 15 ou mais usuários) e cibersegurança | 3–6 meses, em paralelo | R$ [redacted Tier 0], sobretudo horas do time de 2 devs mais a revisão da consultoria |
| Avaliação clínica / análise clínica (RDC 548/2021) da ADM: estudo de concordância com goniômetro ou captura de movimento em ~30–50 voluntários, com fisioterapeuta pesquisador e possível comitê de ética | 4–8 meses | R$ [redacted Tier 0] |
| Taxas Anvisa (porte micro ou pequeno) | — | R$ [redacted Tier 0] |
| **Total para notificar** | **9–15 meses** | **R$ [redacted Tier 0]** |
| Manutenção anual (qualidade, tecnovigilância, petições de mudança, auditoria interna) | contínuo | R$ [redacted Tier 0]/ano |

**Custo escondido mais caro:** o **ritmo de entrega**. Com a IEC 62304 e o controle de mudanças, toda mudança no componente clínico passa por análise de risco e verificação documentada. O isolamento do componente (5.1) protege o resto do ERP.

### 5.4 Como decidir (para [W])

| decisão | quando faz sentido |
|---|---|
| **Ficar na linha (recomendado agora)** | Até a F1+F2 provarem adesão e venda (por exemplo, 300 fisioterapeutas pagantes [estimativa de limiar]). Nota da câmera: 8. |
| **Notificar a classe II** | Se (a) os clientes pedirem ADM e correção como motivo de compra ou de cancelamento, **ou** (b) a prescrição por IA (F4) for o diferencial principal. Uma notificação cobre câmera, dor e IA juntas, o que dilui o custo. |
| **Parceria** | Licenciar um SaMD já notificado de terceiro: [não confirmado] que exista um no Brasil para a câmera. |

**Passo zero, nos dois cenários:** a **consulta de enquadramento da F2** (C-00) já testa a régua da Anvisa e custa pouco.

---

## 6. Métricas de sucesso

| métrica | meta | como medir |
|---|---|---|
| Contagem exata por série (bancada, exercícios liberados) | ≥ 90% das séries | C-14, dois avaliadores |
| Acerto com erro de até 1 repetição (OBOA [fonte 28]) | ≥ 98% | C-14 |
| Erro absoluto médio por série (MAE) | ≤ 0,3 repetição; MAE normalizado com α declarado [fonte 28] | C-14 |
| Repetições falsas com a pessoa parada ou andando no quadro | 0 em 60 s | C-14, cenários negativos |
| Erro da sustentação | ≤ 1 s em sustentações de 5–30 s | C-14, cronômetro em vídeo |
| Divergência em campo, contado × confirmado | ≤ 10% das séries com diferença ≥ 1 | C-15 |
| Aparelhos acima do piso de 6 Hz | ≥ 80% dos pacientes | C-15 |
| Enquadramento resolvido em até 30 s | ≥ 85% das sessões | C-15 |
| Adoção: pacientes ativos que usam a câmera ≥ 1×/semana | ≥ 35% [estimativa de meta] | painel |
| Adesão: dias prescritos cumpridos, usuários de câmera × não usuários | diferença positiva (observacional, sem alegação clínica) | painel |
| Bytes de imagem enviados | **0** | CSP, lint e teste e2e com interceptação de rede |

---

## 7. Tarefas para o Claude Code (ordem de dependência)

> Pré-condições: F1 em produção (T-03, T-07 e T-08 do handoff). A ADR da F2 é admitida pelo funil da ADR 0368. **O lançamento ao público depende de C-00 respondido.**

| id | tarefa | blocked_by | DoD |
|---|---|---|---|
| C-00 | **Consulta de enquadramento à Anvisa** (fora do código: [W] + consultoria). Formulário com funções, entradas e saídas, telas (C-07 a C-09) e as frases permitidas e proibidas de 3.2, incluindo o ponto cinzento do ritmo | — | protocolo de envio anexado à ADR; resposta registrada antes do C-16 |
| C-01 | **Spike de bancada**: página isolada com Pose lite (GPU e CPU) e MoveNet Lightning (LiteRT.js) em Galaxy A15, Galaxy A06 (ou similar de entrada), um Moto G e um Redmi Note, mais um iPhone com iOS 26 | — | tabela com Hz (média e p95), latência, tempo de carga a frio em 4G, bateria por 10 min e temperatura; escolha do runtime registrada na ADR |
| C-02 | Pacote `resources/js/fisio/camera/`: `getUserMedia` em 640×480, Worker + OffscreenCanvas, adaptador `PoseSource` (uma implementação por runtime, sem interface genérica além do necessário), medição de nível, Wake Lock | C-01 | roda ≥ 6 Hz em 3 dos 4 Android do C-01; quadro novo descarta o anterior |
| C-03 | Service worker: cache versionado de modelo, WASM e áudio; download sob demanda com aviso de tamanho; modelo servido pelo próprio domínio | C-02 | segunda sessão em modo avião funciona; troca de versão limpa o cache antigo |
| C-04 | **Núcleo de contagem em TS puro** (sem DOM): sinal 1D por perfil, One Euro, calibração, máquina de estados com histerese, refratário, autocorrelação, confiança | C-02 | testes unitários com **fixtures de sequências de pontos (JSON)**, não vídeo, incluindo taxa variável de 5 a 30 Hz; mesmo resultado com quadros descartados |
| C-05 | Detector de sustentação + medidor de ritmo (duração por repetição, critério de ±30%) | C-04 | testes com fixtures de sustentação de 5/15/30 s e microajustes; erro ≤ 1 s |
| C-06 | Migrations: `fisio_camera_sets` (append-only), `fisio_program_items.tempo`, `fisio_exercises.camera_profile`, `fisio_consents.purpose = camera`; editor do profissional para `tempo` | T-03 da F1 | isolamento por business; UPDATE em `fisio_camera_sets` falha |
| C-07 | **Enquadramento guiado**: cartão de posição, silhueta, checagens de corpo, centro, pessoa única, luz, estabilidade e vista; voz | C-02, C-08 | casos.md com os 8 estados de erro; e2e com fixtures simulando cada falha |
| C-08 | **Áudio**: banco pré-gravado pt-BR (números 1–30, comandos, metrônomo), fila de fala sem sobreposição, fallback `speechSynthesis`; lint da lista de frases proibidas no repositório de textos | — | nenhum texto fora da lista permitida; teste que falha se aparecer termo proibido ("corrija", "postura", "ângulo", "amplitude", "errado") |
| C-09 | **Tela da série** no portal: número grande, anel de sustentação, metrônomo, "Contamos N. Confere?", modo sem câmera a um toque, câmera não oferecida em dia de pausa por dor | C-04, C-05, C-07, C-08 | charter.md + casos.md (ADR 0264); `ui:lint` limpo; só tokens DS v6 |
| C-10 | **Nota da série** (3.5) em PHP no backend (fonte da verdade) e em TS (prévia), com a explicação por partes; integração ao ledger (missão `serie_no_ritmo`) sem XP extra por série | C-06, C-09 | testes com a tabela de exemplos (incluindo o 92 de 3.5); nota calculada no TS e no PHP idênticas; exceder o prescrito não aumenta a nota |
| C-11 | **Privacidade verificável**: consentimento `camera`, tela "O que a câmera faz", "Ver o que foi enviado", CSP e Permissions-Policy na rota, lint em CI contra MediaRecorder, toBlob, toDataURL e upload de Blob | C-06, C-09 | e2e intercepta a rede e prova 0 bytes de imagem; revogação esconde o botão |
| C-12 | API de métricas: POST idempotente das séries (fila offline no IndexedDB, envio ao reconectar), vínculo com o check-in e resumo no registro de telemonitoramento (Res. 619) | C-06, C-10 | envio duplicado não duplica; prontuário recebe o resumo; nada de pontos do esqueleto no payload (teste de schema) |
| C-13 | Painel e ficha do profissional: séries com câmera, nota média, divergência contado × confirmado, item na fila de atenção "baixa confiança repetida" | C-12, T-10 da F1 | números batem com as fixtures; sem amplitude nem ângulo em lugar algum (teste de snapshot) |
| C-14 | **Banco de validação e porta de liberação por exercício**: protocolo com ≥ 30 voluntários (incluindo ≥ 10 com 60+ anos), 2 condições de luz, 3 aparelhos, vídeos gravados **fora do produto**, com consentimento de pesquisa, e convertidos em fixtures; contagem manual por 2 avaliadores; script que calcula exata, OBOA, MAE, falsos positivos e erro da sustentação por exercício | C-04, C-05 | relatório por exercício; `camera_profile.enabled` só é ligado para exercícios que batem as metas da §6; primeira onda com 8–12 exercícios em pé ou sentado |
| C-15 | Telemetria técnica anônima por série (Hz, delegate, nível, tempo de enquadramento, divergência) e painel interno com os gatilhos (nativo, piso de Hz) | C-12 | painel interno mostra as métricas da §6 em campo |
| C-16 | Lançamento controlado: flag por business, piloto com 3–5 fisioterapeutas, revisão de textos e marketing contra a lista proibida da ADR §5; fechar o loop no SPEC (`Implementado em:`) | C-00 (resposta), C-09 a C-15 | aceite de [W]; `verificado@sha7` |

---

## 8. Fontes

1. Oliosi E. et al., *Smartphone camera positioning and AI pose estimation for exercise detection*, JMIR mHealth uHealth 2026;14:e82412 — https://mhealth.jmir.org/2026/1/e82412/
2. Google, Pose landmark detection guide for Web — https://developers.google.com/edge/mediapipe/solutions/vision/pose_landmarker/web_js?authuser=2
3. Google, Pose landmarker (visão geral) — https://developers.google.com/edge/mediapipe/solutions/vision/pose_landmarker
4. Tamanho do `pose_landmarker_lite.task` (5,78 MB) — https://huggingface.co/AndorML/Public/blob/a6ba17720dfb88f514fcab0f8161a4c655fbccd3/pose_landmarker_lite.task
5. Kaia, How does the Kaia Motion Coach work? — https://kaiahealth.zammad.com/help/en-us/18-motion-coach/60-how-does-the-kaia-motion-coach-work · 5b. How to use Kaia Motion Coach — https://help.kaiahealth.com/article/15-how-to-use-kaia-motion-coach
6. Kaia, Motion Coach — https://kaiahealth.com/motion-coach/ · 6b. Gravação só com consentimento — https://kaiahealth.zammad.com/help/en-us/18-motion-coach/61-will-video-recordings-of-me-be-made-automatically-when-i-use-the-kaia-motion-coach
7. Hinge Health, Motion Tracking / TrueMotion — https://www.hingehealth.com/computer-vision
8. Hinge Health, folheto para membros (feedback por áudio; não grava sem consentimento) — https://www.csusm.edu/corp/hr/hingemotioninsightsflyer.pdf
9. MedBridge, expansão da captura de movimento por IA (mai/2025) — https://www.medbridge.com/blog/medbridge-expands-ai-motion-capture-technology-to-transform-msk-care
10. MobiHealthNews, MedBridge compra a Rehab Boost — https://www.mobihealthnews.com/news/medbridge-acquires-motion-capture-company-rehab-boost
11. Sword Health, três novas patentes de IA (nov/2025) — https://swordhealth.com/newsroom/three-new-ai-care-patents
12. Exer Health na App Store — https://apps.apple.com/app/id1505858367
13. Anvisa, Perguntas & Respostas da RDC 657/2022 (Q10, Q11, Q17, Q50, Q55, Q57, Q58, Q60, Q62, Q68, Q87) — https://www.gov.br/anvisa/pt-br/assuntos/noticias-anvisa/2022/software-como-dispositivo-medico-perguntas-e-respostas/perguntas-respostas-rdc-657-de-2022-v1-01-09-2022.pdf/@@download/file
14. TensorFlow Blog, BlazePose em MediaPipe e TF.js (tamanhos e runtime) — https://blog.tensorflow.org/2021/05/high-fidelity-pose-tracking-with-mediapipe-blazepose-and-tfjs.html
15. TensorFlow, Pose estimation (tabela MoveNet/PoseNet, Pixel 5) — https://www.tensorflow.org/lite/examples/pose_estimation/overview
16. Benchmark MediaPipe lite no Chrome de um Pixel 10 Pro XL — https://github.com/BDubDesigns/ToyStage/issues/33
17. MediaPipe issue 6041 (GPU × CPU) — https://github.com/google-ai-edge/mediapipe/issues/6041
18. Google Developers Blog, LiteRT.js (jul/2026) — https://developers.googleblog.com/en/litertjs-googles-high-performance-web-ai-inference/
19. Fora Soft, comparativo OpenPose/MediaPipe/RTMPose (2026; WebGPU, 7–10 FPS) — https://www.forasoft.com/learn/ai-for-video-engineering/articles-ai/openpose-mediapipe-rtmpose-pose-tracking
20. Super Rádio Tupi, 5 celulares mais vendidos no Brasil em 2025 (sem fonte primária do ranking) — https://www.tupi.fm/tecnologia/esses-sao-os-5-celulares-mais-vendidos-no-brasil-em-2025/
21. MacMagazine, participação de mercado no Brasil em 2025 (Omdia) — https://macmagazine.com.br/post/2026/02/17/apple-ficou-com-7-do-mercado-de-smartphones-no-brasil-em-2025/
22. Teletime, operadoras mais rápidas em 2025 (Opensignal) — https://teletime.com.br/28/01/2026/operadoras-rapidas-brasil-2025-opensignal/
23. Mattos Filho, novo marco de SaMD (RDC 657) — https://www.mattosfilho.com.br/unico/anvisa-softwares-dispositivos/
24. Lefosse, RDC 751/2022 — https://lefosse.com/noticias/alerta/anvisa-publica-novo-regulamento-tecnico-para-dispositivos-medicos/
25. Mattos Filho, consultas públicas de software médico (CP 1.035/2021: IEC 62304, IEC 62366-1, ISO 14971) — https://www.mattosfilho.com.br/unico/anvisa-consultas-publicas-softwares-medicos/
26. Pure Global, regularização no Brasil (prazo de notificação de 30–45 dias; preço do serviço) — https://www.pureglobal.com/pt/markets/brazil
27. RAGB, Taxas da Anvisa — https://ragb.com.br/taxas-da-anvisa-informativo
28. *A short note on evaluating RepNet* (definições de OBOA e MAE) — https://arxiv.org/html/2411.08878v1
29. Mattos Filho, RDC 423/2020 (classe II passa à notificação; AFE continua exigida) — https://www.mattosfilho.com.br/en/unico/tomada-publica-softwares-medicos/
30. P=uioio, contagem no aparelho com máquina de estados (98,9% de acurácia) — https://arxiv.org/abs/2308.02420v1
