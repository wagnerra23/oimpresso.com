---
distilled_at: "2026-07-01"
distilled_by: jana:distill-module-truth
module: Whatsapp
---

# BRIEFING — Whatsapp (verdade destilada)

## Estado atual  
O módulo "Whatsapp" permite a interação multicanal via WhatsApp, utilizando a infraestrutura Meta Cloud, após a descontinuação do BaileysDriver (2026-05-27, ADR 0202). A operação está estável; o wizard de Embedded Signup (US-WA-310 Fase 2) está em main desde 2026-05-27 (PR #1768).

## Capacidades  
- Integração com WhatsApp usando Meta Cloud.
- Operação no inbox omnichannel com suporte a IG/FB/Email/ML.
- IA conversacional integrada (Jana) no inbox.
- Persistência de mensagens e lembretes agendados (LembreteHandler).
- Implementação de testes abrangentes para garantir performance e segurança.

## Gaps  
- Refinamento e monitoramento contínuo da confiabilidade da integração com o WhatsApp.
- Anexo automático de boleto/NFe/Pix nas conversas — backlog (US-WA-038 · US-RB-044 v2; Pix marcado AUSENTE no CAPTERRA-INVENTARIO).
- Ampliação da cobertura formal do SPEC e documentação de governança.

## Última mudança  
Em 27 de maio de 2026, o BaileysDriver foi descontinuado (classe e tabelas/colunas removidas; referências legadas de compatibilidade permanecem no código, ex. `Channel::TYPE_WHATSAPP_BAILEYS`). Driver default é Meta Cloud e `baileys` está nos `forbidden_drivers` da config.

## Proveniência (destilado de)

- audit `requisitos/Whatsapp/AUDIT-LOG.md` — AUDIT-LOG.md
- audit `requisitos/Whatsapp/AUDITORIA-MIDIA-OUTBOUND-2026-05-28.md` — AUDITORIA-MIDIA-OUTBOUND-2026-05-28.md
- audit `requisitos/Whatsapp/AUDITORIA-REALTIME-WEBHOOK-UI-2026-05-28.md` — AUDITORIA-REALTIME-WEBHOOK-UI-2026-05-28.md
- audit `requisitos/Whatsapp/AUDITORIA-WEBHOOK-SYNC-HANDLERS-2026-05-14.md` — AUDITORIA-WEBHOOK-SYNC-HANDLERS-2026-05-14.md
- audit `requisitos/Whatsapp/AUDITORIA-WHATSMEOW-DAEMON-2026-05-28.md` — AUDITORIA-WHATSMEOW-DAEMON-2026-05-28.md
- audit `requisitos/Whatsapp/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/Whatsapp/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-06-20-arte-atendimento-automatico-vs-melhor.md` (2026-06-20) — 2026-06-20-arte-atendimento-automatico-vs-melhor.md
- handoff `handoffs/2026-06-19-1103-midia-dns-rootcause.md` (2026-06-19) — 2026-06-19-1103-midia-dns-rootcause.md
- handoff `handoffs/2026-06-19-1154-fechamento-midia-dns-banner.md` (2026-06-19) — 2026-06-19-1154-fechamento-midia-dns-banner.md
- session `sessions/2026-06-18-arte-whatsapp-channel-reliability.md` (2026-06-18) — 2026-06-18-arte-whatsapp-channel-reliability.md
- session `sessions/2026-06-18-arte-whatsapp-naoficiais.md` (2026-06-18) — 2026-06-18-arte-whatsapp-naoficiais.md
- session `sessions/2026-06-18-como-integrar-whatsapp-loggedout-faseA.md` (2026-06-18) — 2026-06-18-como-integrar-whatsapp-loggedout-faseA.md
- session `sessions/2026-06-13-audit-sqlite-test-corruptors.md` (2026-06-13) — 2026-06-13-audit-sqlite-test-corruptors.md
- handoff `handoffs/2026-06-13-1810-auditor-channel-access-flipflop-sqlite-corruptors.md` (2026-06-13) — 2026-06-13-1810-auditor-channel-access-flipflop-sqlite-corruptors.md
- session `sessions/2026-06-08-mapa-telas-projeto.md` (2026-06-08) — 2026-06-08-mapa-telas-projeto.md
