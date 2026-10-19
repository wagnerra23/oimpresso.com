---
id: modules-fisio-pages-fisio-public-portal-charter
page: /fisio/p/{token}
component: Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.tsx
related_prototype: "n/a (tela pública fora do AppShellV2; o canvas Prescreve Pro desenha o app do paciente, mas em 2026-10-18 não havia fonte resolvível em prototipo-ui/ — a versão desenhada é da US-FISIO-007b)"
owner: wagner
status: draft
last_validated: "2026-10-18"
parent_module: Fisio
related_adrs: [93, 358, 437]
alcance:
  rota: /fisio/p/{token}
  rota_nome: fisio.portal.show
  permission: n/a (pública, sem login — link + PIN gerados pela clínica)
  pacote: fisio_module
tier: B
charter_version: 1
---

# Page Charter — /fisio/p/{token} (DRAFT · portal do paciente, entrada mínima)

> Nasce na US-FISIO-007a (ADR 0437 §4, §5 e §7; HANDOFF-F1 T-07; etapa 02 T-07a/T-07b). Casos: [`Portal.casos.md`](Portal.casos.md).
> Precedente: link público de aprovação da OficinaAuto (token HMAC + PIN + lockout). Non-Goals e Anti-hooks além dos abaixo são de [W].

## Mission

Deixar o paciente abrir, pelo link que a clínica gerou e com o PIN que a fisioterapeuta passou, o próprio programa de exercícios, depois de aceitar o acompanhamento à distância.

## Goals — Features (faz)

- Link inválido, expirado, revogado ou substituído: o mesmo estado vazio, sem dizer qual condição quebrou — UC-FPP-01, UC-FPP-02.
- PIN de 4 dígitos com 5 tentativas e trava de 30 min — UC-FPP-03, UC-FPP-04.
- Consentimento de teleatendimento antes de qualquer dado; WhatsApp e câmera são opcionais e independentes; revogação pelo portal — UC-FPP-05, UC-FPP-06.
- Paciente bloqueado ou de outra clínica não abre — UC-FPP-07.
- Lista do dia: só nome do exercício, dose e observação do profissional.

## Non-Goals — Features (NÃO faz)

- ❌ Check-in, XP, nível e sequência (US-FISIO-007b e US-FISIO-008) — o endpoint de check-in já barra sem PIN e sem consentimento, mas não grava.
- ❌ Mostrar queixa, marcadores de condição, aviso de contraindicação ou série de dor (ADR 0437 §5 — SaMD).
- ❌ "Lembrar este aparelho por 30 dias" (etapa 02 T-07a) — depende de `fisio_portal_devices`, fora deste corte.

## Anti-hooks

- ⛔ Nenhum dado clínico antes do PIN e do consentimento — garantido no servidor, nunca só na tela.
- ⛔ O business nunca vem de parâmetro: vem assinado no token.
- ⛔ Token e PIN em claro nunca vão para banco, log ou activity_log.

## Refs

- ADR 0437 · `memory/requisitos/Fisio/HANDOFF-F1.md` (T-07) · `etapas/02-f1-constancia.md` (T-07a, T-07b) · `resources/js/Pages/OficinaAuto/AprovacaoPublica.charter.md`