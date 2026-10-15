---
slug: 0432-app-das-lojas-biometria-so-no-login
number: 432
title: "App das lojas — biometria só no login, feita pelo aparelho; o ponto continua sem biometria"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-05"
module: core
tags: [app-mobile, lgpd, biometria, login, capacitor, camera]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0383-ponto-interno-nao-coleta-biometria
  - 0093-multi-tenant-isolation-tier-0
pii: false
---

# ADR 0432 — App das lojas: biometria só no login

## Contexto

A [ADR 0383](0383-ponto-interno-nao-coleta-biometria.md) tirou a biometria do **ponto interno**: o
sistema de ponto não coleta, não trafega e não deriva dado biométrico. Ela termina dizendo que não
opina sobre biometria em outro contexto: *"se um dia isso se colocar, é ADR nova, com hipótese do
Art. 11 declarada"*.

O contexto se colocou. As propostas P1–P9 do app das lojas (handoff `propostas-app`, conferido em
`oimpresso.com@main` e `oimpresso-app@main` em 2026-10-05) trazem a **P9 · Entrar com biometria**: a
pessoa desbloqueia o app com a digital ou o rosto em vez de digitar a senha. A mesma rodada libera a
**câmera** para anexos de OS (P2) e leitura do QR da etiqueta (P8). Decisão [W] de 2026-10-05,
registrada na lista de tarefas do handoff (item 0.1/0.2).

Sem esta ADR, o próximo agente lê *"Ponto sem câmera/biometria (ADR 0383)"* no BRIEFING do AppMobile
como regra do app inteiro, ou lê a P9 como reabertura da 0383. Nenhuma das duas leituras é a decisão.

## Decisão

**O app das lojas pode usar a biometria do aparelho para destravar o login. Só isso.**

1. **Quem confere a digital ou o rosto é o sistema operacional** (Android BiometricPrompt / iOS
   LocalAuthentication). O app recebe só "confirmou" ou "não confirmou".
2. **Nada biométrico sai do aparelho.** Nenhum template, foto, hash ou identificador derivado da
   biometria é enviado ao ERP, gravado no banco ou escrito em log. A biometria só libera o token
   Passport que já está guardado no armazenamento seguro do aparelho.
3. **A senha continua sendo o caminho.** A biometria é opção em Conta, desligada até a pessoa ligar.
   Três falhas seguidas pedem a senha. Sem biometria cadastrada no aparelho, a opção não aparece.
4. **A empresa pode desligar**, pelo ajuste `app_biometria_login` (padrão `true`, em
   `pos_settings`, lido por `GET /api/app/inicio → ajustes`). Com `false`, a opção some em Conta e o
   token deixa de ficar preso à biometria.
5. **O ponto continua sem biometria** ([ADR 0383](0383-ponto-interno-nao-coleta-biometria.md)
   intacta). A biometria do login não vira prova de presença, não entra na marcação e não é ajuste
   da empresa no ponto.
6. **A câmera é permitida** para anexos de OS e leitura de QR, pedida no primeiro uso. Foto de
   anexo é dado pessoal comum, não biométrico: nenhuma comparação facial é feita sobre ela.

### Base legal

Dado biométrico é **dado pessoal sensível** (LGPD **Art. 5º, II**); seu tratamento exige uma das
hipóteses do **Art. 11**. A hipótese declarada é a do **Art. 11, I** — **consentimento específico e
destacado** do titular, para a finalidade de entrar no app: a pessoa liga a opção por conta própria,
pode desligar a qualquer momento, e a senha continua disponível. É por isso que a opção nasce
desligada.

Há um argumento de que o oimpresso **nem trata** o dado, porque ele não sai do aparelho e o sistema
operacional é quem compara. A decisão não se apoia nele: declarar a hipótese custa uma tela de
consentimento, e deixar sem hipótese custaria a mesma discussão da 0383 na primeira revisão das
lojas.

## Consequências

- App (`wagnerra23/oimpresso-app`): permissões `CAMERA` e `USE_BIOMETRIC` (Android),
  `NSCameraUsageDescription` e `NSFaceIDUsageDescription` (iOS), textos em PT-BR. Feito no README
  do app em [wagnerra23/oimpresso-app#86](https://github.com/wagnerra23/oimpresso-app/pull/86)
  (PR 00a do playbook).
- ERP: nenhuma rota nova para a P9. O que muda é o ajuste `app_biometria_login` (PR 01 do playbook).
- BRIEFING do AppMobile: a linha da 0383 continua; entra a linha desta ADR ao lado.
- Textos de privacidade das lojas: câmera passa a ser declarada (fotos de anexo e QR). A biometria
  **não** entra como dado coletado no Data Safety / App Privacy, porque não sai do aparelho; entra a
  permissão e o texto de uso.

## Como se reconhece violação

- Qualquer campo de biometria (template, imagem, hash, score, identificador derivado) numa rota
  `/api/app/*`, numa tabela ou num log.
- Biometria usada como condição de marcação de ponto, ou como ajuste da empresa no ponto.
- Biometria ligada por padrão, sem a pessoa escolher, ou sem a senha como saída.
- Comparação facial sobre foto de anexo.

Qualquer um desses pede ADR nova.

**Ratificação:** pendente — merge [W] do PR desta ADR flipa `proposto → aceito`.
