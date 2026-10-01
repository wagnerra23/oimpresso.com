---
slug: 0424-ponto-push-registro-pela-api-do-app
number: 424
title: "Ponto — emenda à 0423: o app de telas próprias registra o aparelho pela API (Passport), não pela página web"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-01"
module: pontowr2
tags: [ponto, push, fcm, capacitor, mobile, lembrete, api]
supersedes: []
supersedes_partially:
  - 0423-ponto-push-lembrete-fcm
superseded_by: []
related:
  - 0423-ponto-push-lembrete-fcm
  - 0093-multi-tenant-isolation-tier-0
pii: false
---

# ADR 0424 — Ponto: o lembrete se registra pela API do app

## Contexto

A [ADR 0423](0423-ponto-push-lembrete-fcm.md) partiu de um app Capacitor que **abria o ERP web**: a
própria página `/ponto/mobile` pediria a permissão e registraria o token pela sessão web (§Contexto
e §Decisão 3-4). No mesmo dia (2026-10-01) [W] recusou o app que abre o site. O app das lojas
passou a ter **telas próprias** e a entrar no ERP por **token Passport** (`auth:api`), sem sessão
web. O PR da página web (#8441) foi fechado sem merge.

## Decisão

Substitui só o **canal de registro** da 0423 (§Contexto, §Decisão 3 e o "botão na tela mobile" do
§4). O resto da 0423 continua valendo: FCM HTTP v1 nos dois sistemas, sem dependência nova,
comando `ponto:lembretes-push` a cada 5 min atrás de `PONTO_PUSH_ENABLED`, envio idempotente,
conteúdo genérico, segredos fora do git.

1. **Quem registra é o app**, pelas suas telas: pede a permissão, recebe o token FCM do plugin e
   chama `POST /ponto/api/push/dispositivo` `{token, plataforma}` com o Bearer Passport; o opt-out é
   `DELETE` no mesmo caminho. Usuário e business vêm do token; nada do corpo escolhe (#8457).
2. **No iOS o token é o do FCM**, não o APNs cru: o app usa FirebaseMessaging e repassa o
   `apnsToken` ao Messaging. O servidor continua falando só com o FCM.
3. **A porta web `/ponto/mobile/push/dispositivo` fica**, mesmo controller e mesmo contrato, hoje
   sem chamador. Ela é coberta por teste e não abre superfície nova. Removê-la é decisão à parte.
4. **Tocar na notificação** entrega `data.url = /ponto/mobile`; o app traduz para a sua tela de ponto.

## Justificativa

O registro tem de acontecer onde o colaborador está autenticado. Com telas próprias, esse lugar é
o token da API, e não há mais página web dentro do app para fazê-lo. O controller já filtrava
`business_id`/`user_id` explicitamente, então funciona igual sem sessão.

## Consequências

**Positivas:** o app controla o pedido de permissão no momento certo; nenhuma mudança no envio.

**Negativas / Trade-offs:** a porta web fica sem chamador até alguém decidir removê-la.

## Referências

- ADR 0423 — lembrete de bater ponto por push · PR #8457 (rotas de API) · PR #8441 (fechado)
