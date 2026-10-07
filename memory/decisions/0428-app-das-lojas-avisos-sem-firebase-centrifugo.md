---
slug: 0428-app-das-lojas-avisos-sem-firebase-centrifugo
number: 428
title: "App das lojas — avisos sem Firebase: Centrifugo com o app aberto e lembrete local de ponto (revoga 0423 e 0424)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-07"
module: pontowr2
tags: [app-mobile, ponto, push, centrifugo, capacitor, lembrete]
supersedes:
  - 0423-ponto-push-lembrete-fcm
  - 0424-ponto-push-registro-pelo-app
supersedes_partially: []
superseded_by: []
related:
  - 0058-reverb-substituido-por-centrifugo-frankenphp
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
pii: false
---

# ADR 0428 — App das lojas: avisos sem Firebase

## Contexto

A [ADR 0423](0423-ponto-push-lembrete-fcm.md) escolheu o Firebase Cloud Messaging (FCM) para o
lembrete de ponto, e a [0424](0424-ponto-push-registro-pelo-app.md) ajustou o registro do aparelho
pela API. As duas ficaram em `proposto`.

Em 2026-10-07 o [W] disse que já tinha decidido outra coisa: as mensagens do app são **internas,
não Firebase**, pelo Centrifugo. Textual: *"as mensagens deveria ser internas não firebase"* e
*"revoge a anterior, eu ja tinha decidido outra coisa por isso coloquei o GO centrifugo"*. Essa
decisão **não estava registrada no git** — a varredura em `memory/decisions/`, `docs/lojas-app/`,
`memory/requisitos/AppMobile/` e no repo `oimpresso-app` não achou Centrifugo ligado ao app. O
Centrifugo já é a stack de tempo real do ERP desde a [ADR 0058](0058-reverb-substituido-por-centrifugo-frankenphp.md)
(aceita, CT 100).

No mesmo dia, a versão 7 do app travou no Android 12 por chamar o Firebase sem configuração
(corrigido em `wagnerra23/oimpresso-app#82`).

## Decisão

1. **O app não usa Firebase (FCM) nem SDK de push de terceiros.** As ADRs 0423 e 0424 ficam revogadas.
2. **Avisos do servidor chegam pelo Centrifugo** (ADR 0058, CT 100), por WebSocket, enquanto o app
   está aberto. Canal por usuário e business; o `business_id` vem do token do usuário, nunca do
   cliente (Tier 0, [ADR 0093](0093-multi-tenant-isolation-tier-0.md)).
3. **Lembrete de ponto com o app fechado: notificação local**, agendada pelo próprio aparelho a
   partir da escala do colaborador. Não passa por servidor externo.
4. **O que chegar com o app fechado fica na tela de notificações do app**, que o usuário vê ao abrir.

## Consequências

**Positivas:** nenhum serviço do Google no caminho; nada de conta, chave ou SDK do Firebase; usa a
stack que o ERP já tem; menos a declarar na Play e na App Store.

**Negativas (limite técnico, não escolha):** com o app **fechado**, um aviso do servidor **não
aparece na hora** — Android e iPhone só entregam aviso nessa situação pelos serviços deles (FCM e
APNs). Só o lembrete de ponto, que é local, aparece com o app fechado. Se um dia for preciso avisar
"na hora" com o app fechado (tarefa nova, pedido aprovado), esta decisão precisa ser revista.

**O que já existe e muda** (implementação em PRs próprios, não nesta ADR):
- ERP: `Modules/Ponto` tem o caminho FCM da 0423 — `FcmClient`, `EnviarLembretePontoJob`,
  `LembretesPushCommand` (agendado no `app/Console/Kernel.php`), tabela `ponto_push_dispositivos`
  e testes. Vira código a desligar e depois remover.
- App: `src/push.ts` usa `@capacitor/push-notifications`; desde o `#82` só roda com `VITE_PUSH=1`,
  que nenhum build tem. Troca por notificação local + cliente Centrifugo.
- Texto de privacidade das lojas (`docs/lojas-app/textos/`) deixa de citar FCM.
