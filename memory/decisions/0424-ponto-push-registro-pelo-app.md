---
slug: 0424-ponto-push-registro-pelo-app
number: 424
title: "Ponto — emenda à 0423: o app de telas próprias registra o aparelho pela API (Passport), não pela página web"
type: adr
status: superseded
authority: canonical
lifecycle: substituido
kind: decision
decided_by: [W]
decided_at: "2026-10-01"
module: pontowr2
tags: [ponto, push, fcm, capacitor, mobile, lembrete, api]
supersedes: []
supersedes_partially:
  - 0423-ponto-push-lembrete-fcm
superseded_by: ['0428-app-das-lojas-avisos-sem-firebase-centrifugo']
related:
  - 0423-ponto-push-lembrete-fcm
  - 0093-multi-tenant-isolation-tier-0
pii: false
---

# ADR 0424 — Ponto: o app de telas próprias registra o aparelho pela API

## Contexto

A [ADR 0423](0423-ponto-push-lembrete-fcm.md) partiu de um app Capacitor que abria o ERP web
inteiro, com o registro do token feito pela página `/ponto/mobile` (§Contexto, §Decisão 3-4). Em
2026-10-01 a forma do app mudou mais de uma vez, testada no emulador: abrir o site, telas
próprias, telas do ERP em `/m` e, por fim, **telas próprias no `oimpresso-app`** — decisão [W]
registrada no #8472, que reverte o `/m` (*"não gostei dele dentro do sistema"*). O app não tem
sessão web: entra no ERP por **token Passport** (`auth:api`). O PR que punha o botão em
`/ponto/mobile` (#8441) foi fechado.

## Decisão

Substitui só o **canal de registro** da 0423 (§Contexto, §Decisão 3 e o "botão na tela mobile" do
§4). O resto da 0423 continua valendo: FCM HTTP v1 nos dois sistemas, sem dependência nova,
comando `ponto:lembretes-push` a cada 5 min atrás de `PONTO_PUSH_ENABLED`, envio idempotente,
conteúdo genérico, segredos fora do git.

1. **Quem registra é o app**, nas suas telas: pede a permissão, recebe o token FCM do plugin e
   chama `POST /ponto/api/push/dispositivo` `{token, plataforma}` com o Bearer Passport, ao abrir e a
   cada evento `registration`; o opt-out é `DELETE` no mesmo caminho. Usuário e business vêm do
   token; nada do corpo escolhe (#8457). Sem sessão o ScopeByBusiness não filtra: os `where`
   explícitos de `business_id`/`user_id` do controller são a defesa.
2. **No iOS o token é o do FCM**, não o APNs cru: o app usa FirebaseMessaging e repassa o
   `apnsToken` ao Messaging. O servidor continua falando só com o FCM.
3. **A porta web `/ponto/mobile/push/dispositivo` fica**, mesmo controller e mesmo contrato, hoje
   sem chamador. Coberta por teste; removê-la é decisão à parte.
4. **Tocar na notificação** entrega `data.url = /ponto/mobile`, que serve de identificador da tela
   de ponto: o app trata `pushNotificationActionPerformed` e abre a sua própria tela de ponto.

## Justificativa

O registro acontece onde o colaborador está autenticado, e no app de telas próprias esse lugar é
o token da API. O controller já filtrava por `business_id`/`user_id` explicitamente, então
funciona igual sem sessão.

## Consequências

**Positivas:** o app controla o momento do pedido de permissão; nenhuma mudança no envio.

**Negativas / Trade-offs:** a porta web fica sem chamador até alguém decidir removê-la.

## Referências

- ADR 0423 — lembrete de bater ponto por push · #8457 (rotas de API) · #8472 (reverte o `/m`) · #8441 (fechado)
