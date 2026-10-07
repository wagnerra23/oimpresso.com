---
slug: 0423-ponto-push-lembrete-fcm
number: 423
title: "Ponto — lembrete de bater ponto por push (FCM HTTP v1, token por usuário+business, scheduler existente, opt-out)"
type: adr
status: superseded
authority: canonical
lifecycle: substituido
kind: decision
decided_by: [W]
decided_at: "2026-10-01"
module: pontowr2
tags: [ponto, push, fcm, apns, capacitor, mobile, lembrete]
supersedes: []
supersedes_partially: []
superseded_by: ['0428-app-das-lojas-avisos-sem-firebase-centrifugo']
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0062-separacao-runtime-hostinger-ct100
  - 0383-ponto-interno-nao-coleta-biometria
  - 0419-ponto-rep-p-escopo-ratificado-w10
pii: false
---

# ADR 0423 — Ponto: lembrete de bater ponto por push

## Contexto

O app das lojas é **Capacitor sobre o ERP web inteiro**, id `com.oimpresso.app` (decisão [W]
2026-10-01; o app Expo de `mobile/` fica fora das lojas). O ponto entra pela própria página
`/ponto/mobile` ([RUNBOOK-mobile](../requisitos/Ponto/RUNBOOK-mobile.md)), que no app tem acesso
direto ao plugin `@capacitor/push-notifications` — é ela que pede permissão e registra o token. A Apple recusa app que é só um site embrulhado
(diretriz 4.2); precisamos de uma função nativa que sirva ao colaborador. A escolhida é o
**lembrete de bater ponto conforme a jornada**: alguns minutos antes de cada horário da escala do
dia, se a marcação ainda não foi feita, o aparelho recebe um aviso.

Pré-flight (2026-10-01, `origin/main`): o repositório **não tem** infra de push — nenhum token de
aparelho, nenhum cliente FCM, nenhum pacote de notificação. A jornada já existe:
`ponto_escalas` + `ponto_escala_turnos` (`dia_semana`, `hora_entrada`, `hora_almoco_inicio`,
`hora_almoco_fim`, `hora_saida`) ligados ao colaborador por `escala_atual_id`.

Restrições: produção web no Hostinger **sem daemon** ([ADR 0062](0062-separacao-runtime-hostinger-ct100.md)),
fila padrão `sync`, multi-tenant Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)).

## Decisão

1. **Transporte: Firebase Cloud Messaging, API HTTP v1**, para Android e iOS. No iOS o FCM entrega
   via APNs com a chave `.p8` da conta Apple cadastrada no projeto Firebase. Um caminho só no
   servidor.
2. **Sem dependência nova.** O token OAuth2 do FCM é obtido assinando um JWT RS256 da conta de
   serviço com `openssl_sign` e trocando no endpoint do Google; o envio é `Http::post`. Token de
   acesso em cache por 50 min.
3. **Registro do aparelho:** tabela `ponto_push_dispositivos` (`business_id` indexado + FK,
   `user_id`, `token` único, `plataforma` android|ios, `ativo`, `ultimo_uso_at`). Endpoint
   `POST /ponto/mobile/push/dispositivo` (mesma sessão web da tela) grava sempre para o usuário
   autenticado e o `business_id` da sessão; nada do corpo escolhe usuário ou empresa.
   `DELETE` do mesmo caminho desativa. Token que o FCM responder `UNREGISTERED` é desativado.
4. **Opt-out do colaborador:** `ativo=false` no aparelho (botão na tela mobile) **e** o próprio
   sistema operacional (o usuário nega a permissão). Sem preferência por horário nesta fase.
5. **Agendamento:** comando `ponto:lembretes-push`, `everyFiveMinutes`, `withoutOverlapping`, no
   `Kernel` existente, só em `live`, atrás da flag `PONTO_PUSH_ENABLED` (default `false`). Para
   cada empresa com aparelho ativo, acha os colaboradores com horário da escala de hoje na janela
   `[agora+5min, agora+10min)` e sem marcação daquele tipo, e despacha
   `EnviarLembretePontoJob($businessId, $userId, $tipo, $data)`. Com fila `sync` roda no mesmo
   tick — sem worker.
6. **Sem envio repetido:** tabela `ponto_push_envios` com único `(business_id, user_id, data, tipo)`.
7. **Conteúdo:** só texto genérico ("Lembrete: entrada às 08:00"), sem dado sensível. Tocar abre
   `/ponto/mobile`. O push **nunca bate ponto** — marcação continua sendo ato do colaborador.
8. **Segredos** (JSON da conta de serviço, chave `.p8`) ficam no Vaultwarden e no `.env` do
   servidor; nunca no git.

## Justificativa

FCM é o único caminho gratuito que cobre os dois sistemas com um servidor só. Assinar o JWT com
`openssl_sign` evita pacote novo (o `lcobucci/jwt` já vem com o Passport, mas a assinatura RS256
direta tem 15 linhas e não depende de `ext-sodium`, ausente no CLI do Hostinger). O scheduler já
roda no Hostinger; cinco minutos de granularidade bastam para lembrete. Reabrir se o volume
passar de alguns milhares de envios por tick ou se o lembrete precisar de precisão de minuto.

## Consequências

**Positivas:** função nativa real para a revisão da Apple; valor direto ao colaborador; nenhuma
dependência ou daemon novo.

**Negativas / Trade-offs:** o tick síncrono cresce com o número de aparelhos; janela de 5 min;
escala sem turno cadastrado não gera lembrete.

**Riscos mitigados:** isolamento por `business_id` em tabela, rota e Job; flag desligada por
padrão; envio idempotente; token inválido desativado.

## Referências

- [RUNBOOK-mobile](../requisitos/Ponto/RUNBOOK-mobile.md)
- ADR 0093 — multi-tenant Tier 0 · ADR 0062 — Hostinger ≠ CT 100 · ADR 0383 — sem biometria
