---
slug: 0446-fisio-teleconsulta-livekit-e-evolucao-ditada
number: 446
title: "Fisio: teleconsulta por vídeo no navegador (LiveKit, sem gravação) e evolução ditada por voz (laravel/ai, áudio apagado, nada no prontuário sem assinatura)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-11-04"
module: fisio
quarter: 2026-Q4
tags: [fisio, teleconsulta, livekit, webrtc, ditado, transcricao, laravel-ai, lgpd, coffito, prontuario, dependencia]
related_adrs: [0437-modulo-fisio-vertical-fisioterapia, 0035-stack-ai-canonica-wagner-2026-04-26, 0062-separacao-runtime-hostinger-ct100, 0093-multi-tenant-isolation-tier-0, 0438-artefato-substitui-cowork-como-superficie-do-prototipo]
supersedes: []
pii: true
review_triggers:
  - "o LiveKit Cloud abrir região de dados no Brasil, ou o volume passar de 150.000 min/mês (plano Ship) — reavaliar self-host"
  - "o teste de transcrição T-F4-02 sair, porque ele escolhe o motor"
  - "a assinatura ICP-Brasil virar exigência para prontuário de fisioterapia (pendência da ADR 0437 §12)"
  - "[W] quiser gravar a teleconsulta"
---

# ADR 0446 · Fisio: teleconsulta por vídeo e evolução ditada por voz

> **Status `proposto`.** Nada de código sai antes da ratificação de [W]. Esta ADR existe porque a
> teleconsulta traz **dependência nova** (`livekit-client` e `@livekit/components-react` no npm) e a
> evolução ditada manda **dado de saúde** para um fornecedor de transcrição. As duas coisas exigem
> decisão registrada (proibições: *"não criar nova dependência sem ADR"*).
>
> **Não é pesquisa nova.** A pesquisa foi feita em 2026-10-17 e está em
> [`etapas/04-f3-operacao.md` §3.3](../requisitos/Fisio/etapas/04-f3-operacao.md) (teleconsulta) e
> [`etapas/05-f4-ia-questionarios.md` §3.1](../requisitos/Fisio/etapas/05-f4-ia-questionarios.md)
> (ditado). Esta ADR transforma as recomendações de lá em decisão, fecha dois pontos que ficaram
> pendentes (região do LiveKit e o motor de transcrição) e conta o que o repo já tem. Em conflito
> entre esta ADR e as etapas, vale esta.

## Contexto

O canvas "Prescreve Pro" veio para o git no [#10321](https://github.com/wagnerra23/oimpresso.com/pull/10321)
(fonte de design pela [ADR 0438](0438-artefato-substitui-cowork-como-superficie-do-prototipo.md)). Duas telas
dele dependem de decisão técnica:

- **`Teleconsulta.dc.html`** — chamada de vídeo com a paciente, com a evolução sendo escrita ao lado.
  O que a tela promete: *"Teleconsulta síncrona (Res. COFFITO 619/2025) · consentimento de
  teleatendimento v1 aceito"*; *"Sem gravação: a consulta não é gravada. Se a conexão cair para áudio,
  a chamada continua só com voz"*; indicador *"4G · 360p · áudio estável"*; e, ao encerrar, a evolução
  é carimbada com nome e CREFITO, modalidade *"teleconsulta síncrona"* e canal *"vídeo"*, e *"a sessão
  só conta como realizada depois de assinada"*.
- **`EvolucaoDitada.dc.html`** — o profissional fala depois do atendimento e o sistema separa nos
  campos da Res. COFFITO 414/2012, mostrando de onde veio cada trecho (marcas `0:22–0:58`). Promete:
  *"Áudio apagado logo depois da transcrição"*, *"Nome e CPF não entram na transcrição"*, *"Nada vai
  ao prontuário sem a sua assinatura"*, e cada campo marcado *"do ditado"* ou *"editado por você"*.
  *"Depois de assinada, a evolução não muda; correção só por adendo."*

A [ADR 0437](0437-modulo-fisio-vertical-fisioterapia.md) já põe a teleconsulta na F3 (*"LiveKit, sem
gravação"*) e o ditado na F4 (§13.6: *"dado de saúde só para fornecedor em país reconhecido como adequado
pela ANPD ou modelo rodando no nosso servidor; nome e CPF nunca vão no prompt; áudio apagado depois da
transcrição"*). Aquilo é direção; esta ADR é a decisão de implementação.

### O que o oimpresso já tem (introspecção de 2026-11-04, `origin/main` em `3583e435c2`)

| peça | onde | serve aqui? |
|---|---|---|
| Prontuário append-only | `Modules/Fisio/Entities/Record.php` — `fisio_records` com `modality`, `channel`, `signed_by`, `signed_crefito`, `signed_at`, `amends_id`; registro assinado não aceita UPDATE nem DELETE (model + trigger MySQL); correção é `kind=adendo` | **sim, inteiro.** Rascunho = registro sem `signed_at`. Não precisa tabela nova de rascunho |
| Consentimento por finalidade | `Modules/Fisio/Services/Consentimentos.php`, finalidade `teleatendimento` já existe | **sim.** A sala confere o consentimento vigente antes de abrir |
| Transcrição no SDK | `laravel/ai` ^0.10 (ADR 0035) tem `Laravel\Ai\Transcription::fromStorage(...)->generate()` e provider `openai-compatible` que fala com qualquer `/audio/transcriptions` (doc do SDK, `laravel/ai@latest`) | **sim**, com uma ressalva: a doc consultada é a `@latest`; o PR 3 confirma no `vendor` que a v0.10.1 instalada já tem a classe |
| Transcrição no Whatsapp | `Modules/Whatsapp/Services/Audio/WhisperTranscriber.php` — HTTP direto para `api.openai.com` (EUA), modelo `whisper-1` | **não para o Fisio.** Processa nos EUA sem instrumento da ANPD e é anterior ao `Transcription` do SDK. O Fisio não depende de classe do Whatsapp |
| Ollama no CT 100 | `config/ai.php` provider `ollama` (hoje usado para embedding) | só se o teste escolher modelo local (ver D2) |
| JWT no servidor | `lcobucci/jwt` e `firebase/php-jwt` já estão no `composer.lock` (via Passport) | **sim.** O token de sala do LiveKit é um JWT assinado com a chave do projeto; **não entra pacote PHP novo** |
| Centrifugo (CT 100, ADR 0058) | tempo real do app | **não.** Centrifugo é pub/sub de mensagens, não leva vídeo |
| Vídeo | nada no repo (`git ls-files` sem `livekit`, `telesession`, `teleconsult` fora do protótipo) | — |

**Ferramenta madura existe?** Sim, e por isso nada aqui é construído do zero: vídeo é **WebRTC com SFU**
(técnica padrão; LiveKit é um SFU open source com SDK React), e o ditado estruturado é o padrão
"escriba clínico com rastreio até o trecho falado" que Abridge, Heidi e Nabla praticam (comparativo em
`etapas/05` §2.1). O que é nosso é só a cola: consentimento, prontuário append-only e o validador de
trecho de origem.

## Decisão

### D1 · Teleconsulta: LiveKit embutido na tela, sem gravação

1. **Vídeo embutido no oimpresso, não link externo.** A sala é aberta pelo agendamento (tipo
   `teleconsulta`), o profissional entra pela tela do Fisio e o paciente pelo **link do portal com
   PIN** (o mesmo da F1), sem instalar nada. Link externo (Meet, Zoom, chamada do WhatsApp) fica só
   como **fallback** quando a conexão cai duas vezes, e o registro marca o canal real
   (`whatsapp_chamada`).
2. **Provedor: LiveKit Cloud**, um projeto do oimpresso para todas as clínicas, com a sala nomeada por
   empresa e agendamento. O backend gera o token de sala (JWT de uso único, validade curta, só depois de
   conferir `business_id`, agendamento e consentimento). Nenhuma mídia passa pelo nosso servidor.
3. **Região de dados do projeto: União Europeia (Frankfurt).** O LiveKit Cloud oferece hoje região de
   dados nos **EUA e na UE**, escolhida ao criar o projeto e imutável depois (fonte LiveKit, abaixo,
   consulta de 2026-11-04). **Não achei região de dados no Brasil** — São Paulo aparece só como ponto de
   SIP. A UE é reconhecida como adequada pela ANPD (Res. CD/ANPD 32/2026, citada em `etapas/05` §3.1.5);
   os EUA exigiriam cláusulas-padrão. A observabilidade de agentes do LiveKit fica **desligada** no
   projeto, como o próprio LiveKit recomenda para residência restrita.
   ⚠️ *Não confirmado:* se a **mídia em tempo real** de dois participantes no Brasil passa por servidor
   de borda no Brasil ou fora. A mídia só trafega (não é guardada), mas a transferência entra no DPA
   do mesmo jeito. O PR 1 mede isso numa chamada real antes de ligar para cliente.
4. **Onde roda:** o vídeo roda no LiveKit; o oimpresso (Hostinger) só emite token e grava metadado.
   Nenhum daemon novo no Hostinger nem no CT 100 (ADR 0062 respeitada).
5. **Sem gravação, e a tela diz isso.** A Res. 619 não trata de gravação; gravar faria o vídeo entrar no
   prontuário com **20 anos de guarda** (Lei 13.787/2018) e pediria consentimento próprio. Gravação fica
   fora até [W] pedir (gatilho de revisão).
6. **4G:** simulcast, paciente publica no máximo 360p, áudio com prioridade, e abaixo do limiar a chamada
   propõe continuar só com voz (o que a tela já promete).
7. **Câmera é só para o profissional ver.** Nenhuma análise de movimento na teleconsulta — isso é SaMD
   classe II (ADR 0437 §5) e é assunto da ADR do lote 7.
8. **Camada única:** `TeleconsultaProvider` com **uma** implementação (LiveKit), sem interface
   especulativa. Trocar de fornecedor = reescrever essa classe.

**Custo por sessão** (preço do LiveKit Cloud levantado em 2026-10-17, `etapas/04` §3.3, a reconferir no
PR 1): consulta de 30 min com 2 pessoas = 60 participante-min. O plano **Build é grátis com 5.000
min/mês** (≈ 83 consultas por mês somando todas as clínicas). Depois, plano **Ship US$ 50/mês com
150.000 min** (≈ 2.500 consultas) e excedente de US$ 0,0005/min, ou **≈ US$ 0,03 por consulta**. Para
comparação, na mesma fonte: Twilio ≈ US$ 0,24/consulta; Jitsi JaaS cobra por usuário ativo (US$ 99/mês
para 300). O custo é do oimpresso, não da clínica, até o preço do módulo dizer o contrário.

### D2 · Evolução ditada: transcrição pelo `laravel/ai`, áudio apagado, revisão antes de assinar

1. **Modo: ditado depois do atendimento** (30 s a 3 min), não escriba ambiente que grava a consulta.
   Gravar a consulta inteira aumenta o volume de dado sensível e exige outro consentimento
   (`etapas/05` §2.1).
2. **Captura no navegador** (MediaRecorder), upload para o backend em disco **privado**, por empresa.
3. **Transcrição pela Camada A do `laravel/ai`** (`Transcription`), com o provedor em configuração do
   módulo. **O motor sai do teste T-F4-02** (30 ditados reais de fisioterapeutas, com consentimento,
   medindo WER e acerto nos termos do dicionário: "manguito rotador", "Lasègue", "ADM", "TENS"…). Não há
   benchmark público de ASR em PT-BR de fisioterapia; escolher sem teste seria chute. Candidatos, todos
   com a mesma regra de onde o dado pode ir:
   - **Whisper large-v3-turbo no CT 100**, servido como endpoint `openai-compatible` — o dado não sai do
     Brasil. Sem GPU, roda em fila (estimativa: segundos a poucos minutos por ditado), o que serve porque
     o ditado já é assíncrono. É um serviço novo no CT 100, permitido pela ADR 0062.
   - **OpenAI em projeto com residência na UE**. ⚠️ A OpenAI anuncia processamento na Europa com
     retenção zero para "endpoints elegíveis", mas **não confirmei que `/audio/transcriptions` é
     elegível** (consulta de 2026-11-04). Só vale se o PR 3 confirmar no help da OpenAI.
   - **Proibido:** o mesmo `whisper-1` nos EUA que o Whatsapp usa, sem cláusulas-padrão da ANPD.
4. **Onde o áudio é processado e quanto tempo vive:** CT 100 (Brasil) ou UE, nunca EUA sem instrumento.
   O arquivo é **apagado por job assim que a transcrição termina**, com confirmação registrada; se a
   transcrição falha, é apagado em até 24 h. **O áudio não é guardado.**
5. **Estruturação nos campos da Res. 414 por um agente `laravel/ai`**, só com texto e **pseudonimizado**
   (o prompt recebe "Paciente", nunca nome nem CPF). Devolve JSON com, para cada campo, o texto e os
   **trechos de origem** (`source_spans`). Um validador recusa campo com texto e sem trecho; campo não
   ditado volta **vazio** com o aviso "não ditado". Proibido inferir diagnóstico, prognóstico, CID/CIF ou
   conduta que não foi dita (SaMD, ADR 0437 §5). O LLM obedece à mesma regra de região do item 4.
6. **Fluxo de revisão e assinatura (append-only):** a estruturação cria um `fisio_records` **sem
   `signed_at`** (rascunho editável). A tela mostra cada campo marcado "do ditado" ou "editado por
   você". Ao assinar, grava `signed_by`, `signed_crefito` e `signed_at`; daí em diante o model e o
   trigger recusam UPDATE e DELETE, e correção é `kind=adendo`. A **transcrição bruta** fica junto do
   rascunho por no máximo 7 dias e é descartada ao assinar — o prontuário é o texto assinado.
7. **Custo** (estimativa de `etapas/05` §3.1.3): 100 atendimentos/mês × 2 min ≈ US$ 0,90/mês de
   transcrição por profissional em API, mais ≈ US$ 0,09 de estruturação. No CT 100 o custo é o do
   servidor que já existe. Cabe no plano, sem add-on.

### D3 · LGPD (dado de saúde é sensível, art. 11)

| ponto | decisão |
|---|---|
| Papéis | a clínica é **controladora**; o oimpresso é **operador**; LiveKit e o fornecedor de transcrição/LLM (se externo) são **suboperadores**, listados no DPA e numa lista visível para a clínica |
| Base legal do atendimento, do vídeo e do ditado | **tutela da saúde** (art. 11, II, "f"); guarda do prontuário por **obrigação legal** (art. 11, II, "a" e Lei 13.787) |
| Consentimento | não é a base legal, mas **a norma profissional o exige** para o atendimento remoto (Res. 619 art. 1º; Lei 14.510 art. 26-G). Usa a finalidade `teleatendimento` já existente, versionada e revogável. O texto do consentimento avisa que o registro pode ser ditado e transcrito por fornecedor listado |
| Retenção | vídeo: **nada guardado** · metadado da sessão (`fisio_telesessions`: horários, duração, qualidade, consentimento): parte do prontuário, 20 anos · áudio do ditado: minutos (apagado após transcrever, no máximo 24 h) · transcrição bruta: até 7 dias ou até assinar · evolução assinada: 20 anos, sem exclusão (pedido de exclusão não alcança prontuário dentro da guarda, art. 16, I) |
| Transferência internacional | só para a UE (adequada, Res. CD/ANPD 32/2026) ou com cláusulas-padrão da ANPD; o padrão desta ADR é UE ou Brasil |
| Treino | contratos sem uso para treino e com retenção zero ou mínima; nada do Fisio vai para treino de modelo |
| Multi-tenant | `business_id` em `fisio_telesessions`, no arquivo de áudio e em toda query; teste cruzado de empresa em cada PR (ADR 0093) |

### D4 · O que fica de fora

- Gravação de teleconsulta (vídeo ou áudio).
- Escriba ambiente que escuta a consulta inteira.
- Sala de espera com triagem, teleconsulta em grupo, app nativo para a chamada.
- LiveKit self-host (fica como caminho se o volume crescer ou se a medição do item D1.3 mostrar mídia
  saindo do país e o DPA não aceitar).
- Análise de movimento por vídeo (lote 7 / ADR da câmera).
- IA que sugere conduta, teste ou diagnóstico (ADR 0437 §5, classe II).
- Migrar o `WhisperTranscriber` do Whatsapp para o `laravel/ai` (melhora válida, outro módulo, outro PR).

## Alternativas consideradas

| opção | prós | contras | veredito |
|---|---|---|---|
| **LiveKit Cloud embutido** | SDK React maduro, simulcast, open source (dá para levar para self-host sem trocar código), grátis até ~83 consultas/mês | sem região de dados no Brasil; dependência npm nova | **escolhida** |
| LiveKit self-host (VPS no Brasil ou CT 100) | mídia no país | operar TURN, atualização e monitoramento; o CT 100 é servidor doméstico, não ponto de mídia público | depois, se precisar |
| Daily | o mais simples (sala por URL) | preço "não reverificado" na fonte; add-on de conformidade é HIPAA, que não se aplica aqui | não |
| Twilio Video | conhecido | ~8× mais caro por consulta; já teve fim de vida anunciado (revertido em 2024) | não |
| Jitsi (JaaS) | embutível | cobra por usuário ativo, caro para o autônomo; self-host tem custo de operação | não |
| Link externo (Meet/Zoom/WhatsApp) | zero código | fora do prontuário, sem conferir consentimento, sem carimbar a evolução; paciente precisa de conta/app | só fallback |
| Transcrição no `whisper-1` EUA do Whatsapp | já integrado | saúde para os EUA sem instrumento da ANPD | **proibido** |
| Gemini com áudio (transcreve e estrutura numa chamada) | barato | junta transcrição e interpretação num passo menos auditável; perde o trecho de origem | não |
| Whisper no CT 100 × OpenAI UE | dado no Brasil × latência menor | operar o serviço × elegibilidade do endpoint não confirmada | **decide o T-F4-02** |

## Consequências

- Primeira dependência de vídeo no repo: `livekit-client` e `@livekit/components-react` (npm). Versão
  exata fixada no PR 2, com pelo menos duas semanas de publicada.
- Primeiro dado de saúde em áudio: o job de apagar o áudio vira teste obrigatório (o arquivo não existe
  depois do job).
- O custo de vídeo é do oimpresso e cresce com o uso; acima do plano Ship o gatilho de revisão dispara.
- Uma conta nova no LiveKit (credencial no Vaultwarden, nunca no git).
- Se o T-F4-02 escolher Whisper local, nasce um serviço no CT 100 que precisa de monitor de saúde.

## Pendências antes de ligar para cliente real (não bloqueiam o código)

- DPA da clínica com LiveKit e o fornecedor de transcrição como suboperadores.
- Medição da rota da mídia numa chamada real (D1.3).
- T-F4-02 com 30 ditados reais e consentimento — depende da Dra. Dejeane e de colegas dela.
- Assinatura ICP-Brasil para prontuário (ADR 0437 §12) segue aberta; esta ADR usa a assinatura que o
  `fisio_records` já tem (usuário autenticado + CREFITO + data e hora) e não resolve essa pendência.

## PRs de implementação que saem desta ADR (estimativa: 4)

Cada um ≤300 linhas quando possível, com teste cruzado de empresa (biz=98) e flag desligada por padrão.

1. **Teleconsulta · backend** — migration `fisio_telesessions` (sem mídia); `TeleconsultaProvider`
   LiveKit gerando token com `lcobucci/jwt` (sem pacote PHP novo); rota que só emite token com
   agendamento da mesma empresa e consentimento `teleatendimento` vigente; encerramento cria o rascunho
   de evolução com `modality=sincrona`, `channel=video`, horários e duração. Pest: token negado sem
   consentimento, negado para outra empresa, rascunho criado ao encerrar. Medição da rota da mídia.
2. **Teleconsulta · tela** — `Pages/Fisio/Teleconsulta` a partir de `Teleconsulta.dc.html`; entrada do
   paciente pelo portal (link + PIN, botão liberado 10 min antes, teste de câmera e microfone);
   simulcast/360p, só-voz, fallback; "Encerrar e assinar evolução". Traz a dependência npm.
   `casos.md` + charter.
3. **Ditado · backend** — upload do áudio em disco privado, job de transcrição via `laravel/ai`
   `Transcription` com o provedor em config, job que apaga o áudio com confirmação,
   `ClinicalNoteStructurer` (pseudonimizado, `source_spans`, validador), rascunho em `fisio_records`
   sem `signed_at`, descarte da transcrição bruta em 7 dias ou ao assinar. Pest: áudio não existe depois
   do job; nome do paciente nunca no payload; ditado sem diagnóstico devolve o campo vazio; campo sem
   trecho é recusado. Provider de teste fixo (`Transcription::fake`) até o T-F4-02 decidir o motor.
4. **Ditado · tela** — `EvolucaoDitada` a partir do protótipo: gravar, campos com "de onde veio",
   "do ditado" × "editado por você", "não ditado", assinar no fluxo append-only (adendo depois de
   assinada). `casos.md` + charter.

O teste T-F4-02 e, se escolhido, o serviço Whisper no CT 100 são trabalho à parte (o primeiro é
humano-limitado; o segundo só existe se o teste mandar).

## Fontes (consultadas em 2026-11-04, salvo indicação)

- Res. COFFITO 619/2025, de 28/05/2025, DOU 09/07/2025, que revogou a 516/2020: https://abmes.org.br/legislacoes/detalhe/5208/resolucao-coffito-n-619-2025 · https://www.normasbrasil.com.br/norma/resolucao-619-2025_480866.html · resumo do fornecedor: https://www.physitrack.com/pt/insights/resolucao-coffito-619-2025-telefisioterapia-brasil (art. 1º consentimento e sigilo; art. 4º infraestrutura sem plataforma imposta; art. 5º registro detalhado a cada atendimento; nenhuma regra sobre gravação nos textos consultados — o texto integral do DOU segue pendência da ADR 0437 §12).
- Res. COFFITO 414/2012 (prontuário): https://www.normasbrasil.com.br/norma/resolucao-414-2012_240928.html
- Lei 14.510/2022 (telessaúde): https://www.planalto.gov.br/ccivil_03/_ato2019-2022/2022/lei/l14510.htm · Lei 13.787/2018 (prontuário, 20 anos): https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13787.htm · LGPD: https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm
- LiveKit, regiões de dados (EUA e UE; escolhida na criação; observabilidade de agente desligada para residência restrita): https://docs.livekit.io/deploy/admin/regions/data-residency · https://livekit.com/blog/checklist-for-regional-deployments · São Paulo só como SIP: https://docs.livekit.io/deploy/admin/regions/endpoints
- Preços de vídeo e o comparativo LiveKit/Daily/Twilio/Jitsi: `memory/requisitos/Fisio/etapas/04-f3-operacao.md` §3.3 e §7 (pesquisa de 2026-10-17).
- OpenAI, residência de dados na Europa (endpoints elegíveis, retenção zero): https://openai.com/index/introducing-data-residency-in-europe/
- `laravel/ai`, transcrição e provider `openai-compatible`: https://github.com/laravel/docs/blob/13.x/ai-sdk.md (seção Transcriptions)
- Escribas clínicos, preços de ASR, Res. CD/ANPD 32/2026 (UE adequada), Whisper em PT: `memory/requisitos/Fisio/etapas/05-f4-ia-questionarios.md` §2.1, §3.1 e §7 (pesquisa de 2026-10-17).
- Protótipo: `prototipo-ui/cowork/Wagner/prototipos/fisio/Teleconsulta.dc.html` e `EvolucaoDitada.dc.html` (#10321).
