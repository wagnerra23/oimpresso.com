---
id: requisitos-whatsapp-briefing
module: Whatsapp
status: producao
updated_at: "2026-09-15"
distilled_at: "2026-09-15"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Whatsapp (verdade destilada)

## Estado atual
O módulo de atendimento multicanal com WhatsApp como canal principal está em produção. O driver padrão é o `meta_cloud`, substituindo o Baileys, que foi descontinuado. A integração é feita através do whatsmeow, que é utilizado para os canais `Suporte` e `Jana`. No entanto, o estado operacional completo não é confirmado por aqui devido a incidentes recentes.

## Capacidades
- Integração com o Meta Cloud via `MetaCloudDriver`.
- IA conversacional Jana no inbox.
- Persistência de mensagens e lembretes.
- Realização de testes de saturação na Wave 26.
- Coleta de feedback do cliente via WhatsApp e formulário público.

## Gaps
- Hoje, o inbox omnichannel limita-se ao WhatsApp, com outros canais não operacionais.
- Canais `Suporte` e `Jana` não estão recebendo inbound desde julho/2026.
- Falta de suporte a anexos automáticos (boleto/NFe/Pix) nas conversas.

## Última mudança
Em 2026-09-08, três agendamentos que só produziam falha saíram do schedule (#7067) e a Onda 7 deu dono a 5 inventários de paridade, incluindo o do Whatsapp (#6973). Antes disso, a capacidade de calcular `dias_ativos_30d` foi implementada (PR #6293, 2026-08-26), precedida de ajustes de higiene e melhorias no canal público de feedback.

## Proveniência (destilado de)

- audit `requisitos/Whatsapp/AUDIT-LOG.md` — AUDIT-LOG.md
- audit `requisitos/Whatsapp/AUDITORIA-MIDIA-OUTBOUND-2026-05-28.md` — AUDITORIA-MIDIA-OUTBOUND-2026-05-28.md
- audit `requisitos/Whatsapp/AUDITORIA-REALTIME-WEBHOOK-UI-2026-05-28.md` — AUDITORIA-REALTIME-WEBHOOK-UI-2026-05-28.md
- audit `requisitos/Whatsapp/AUDITORIA-WEBHOOK-SYNC-HANDLERS-2026-05-14.md` — AUDITORIA-WEBHOOK-SYNC-HANDLERS-2026-05-14.md
- audit `requisitos/Whatsapp/AUDITORIA-WHATSMEOW-DAEMON-2026-05-28.md` — AUDITORIA-WHATSMEOW-DAEMON-2026-05-28.md
- audit `requisitos/Whatsapp/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/Whatsapp/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-09-06-refutacao-gt-g5-seis-gap-fundacao-r1.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-seis-gap-fundacao-r1.md
- session `sessions/2026-09-06-refutacao-gt-g5-seis-gap-fundacao-r2.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-seis-gap-fundacao-r2.md
- session `sessions/2026-09-02-whatsapp-incident-canais-biz1-mudos-whatsmeow.md` (2026-09-02) — 2026-09-02-whatsapp-incident-canais-biz1-mudos-whatsmeow.md
