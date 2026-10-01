---
slug: 0424-ponto-push-registro-pela-tela-m
number: 424
title: "Ponto — emenda à 0423: o app abre as telas do ERP em /m (sessão web); o registro do aparelho é feito pela tela /m de ponto"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-01"
module: pontowr2
tags: [ponto, push, fcm, capacitor, mobile, lembrete]
supersedes: []
supersedes_partially:
  - 0423-ponto-push-lembrete-fcm
superseded_by: []
related:
  - 0423-ponto-push-lembrete-fcm
  - 0093-multi-tenant-isolation-tier-0
pii: false
---

# ADR 0424 — Ponto: o lembrete se registra pela tela /m de ponto, com sessão web

## Contexto

A [ADR 0423](0423-ponto-push-lembrete-fcm.md) partiu de um app Capacitor que abria o ERP web
inteiro, com o registro do token feito pela página `/ponto/mobile` (§Contexto, §Decisão 3-4). No
mesmo dia (2026-10-01) a forma do app mudou duas vezes: primeiro para telas próprias com token
Passport (que levou às rotas `/ponto/api/push/dispositivo`, #8457) e, por fim, decisão [W], para
o app abrindo **telas do ERP em `https://oimpresso.com/m`** dentro do WebView, com **sessão web**
(cookie). O PR que punha o botão em `/ponto/mobile` (#8441) foi fechado.

## Decisão

Substitui só o **ponto de chamada** do registro (§Contexto e §Decisão 3-4 da 0423). O resto da
0423 continua valendo: FCM HTTP v1 nos dois sistemas, sem dependência nova, comando
`ponto:lembretes-push` a cada 5 min atrás de `PONTO_PUSH_ENABLED`, envio idempotente, conteúdo
genérico, segredos fora do git.

1. **Quem registra é a tela de ponto em `/m`**, rodando no WebView do app: ela usa o plugin
   `PushNotifications` que a ponte do Capacitor injeta na página remota (medido no emulador
   Android), pede a permissão e envia o token a `POST /ponto/mobile/push/dispositivo` com a
   sessão web; o opt-out é `DELETE` no mesmo caminho. Usuário e business vêm da sessão.
2. **As rotas `/ponto/api/push/dispositivo` (auth:api, #8457) saem**: sem app de telas
   empacotadas, não têm chamador.
3. **No iOS o token é o do FCM** (FirebaseMessaging no app, repassando o `apnsToken`).
4. **Tocar na notificação** entrega `data.url = /ponto/mobile`; a página trata
   `pushNotificationActionPerformed` e navega. O destino final na base `/m` acompanha a tela de
   ponto quando ela existir.

## Justificativa

O registro acontece onde o colaborador já está autenticado. Com o app abrindo o ERP em `/m`, esse
lugar é a sessão web, e a ponte do Capacitor alcança a página remota. Manter uma segunda porta
(API) sem chamador seria superfície sem dono.

## Consequências

**Positivas:** um caminho de registro só; nenhuma mudança no envio.

**Negativas / Trade-offs:** o lembrete depende da tela de ponto em `/m` existir e incluir o
componente de permissão; até lá não há onde ativar.

## Referências

- ADR 0423 — lembrete de bater ponto por push · #8457 (rotas de API, removidas aqui) · #8441 (fechado)
