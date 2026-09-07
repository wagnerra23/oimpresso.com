---
id: requisitos-whatsapp-briefing
module: Whatsapp
status: producao
updated_at: "2026-09-06"
distilled_at: "2026-09-06"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Whatsapp (verdade destilada)

## Estado atual
Módulo de atendimento multicanal (inbox omnichannel) com WhatsApp como canal principal. Driver default `meta_cloud` (`Modules/Whatsapp/Config/config.php`); o whatsmeow (daemon WuzAPI/Go no CT 100, ADR 0204) substituiu o Baileys (descontinuado pela ADR 0202, 2026-05-27 — classe do driver e tabelas/colunas removidas; `forbidden_drivers` no config impede a volta e `Channel::TYPE_WHATSAPP_BAILEYS` segue na lista de tipos, lido por comandos de reconciliação/health, mas o `ChannelDriverFactory` lança `NotImplementedDriverException` — nenhum envio é possível) e é o driver dos canais `Suporte` e `Jana` (biz=1), medidos no incidente de 2026-09-02. Há também `ZapiDriver`. Estado operacional não é afirmado aqui — ver o incidente aberto em Gaps. Absorveu o antigo módulo Atendimento (fusão KL-E2 — `_TRIAGEM-IDENTIDADE-2026-06.md` §Estado de execução E2/E3, recibos #2750 e #3653).

## Capacidades
- Integração Meta Cloud (`MetaCloudDriver`); Embedded Signup v4 (Fase 2 da ADR 0202; em main desde 2026-05-27, PR #1768). O SPEC não tem story própria pra ele — quem carrega a âncora é US-WA-001 (Wizard 2 passos), `_parcial_` · verificado@dd3ed7c (2026-07-01), com nota de que a tela evoluiu pro Embedded Signup.
- Jana (IA conversacional) no inbox.
- Persistência de mensagens (`Services/Webhook/MessagePersister`) e lembretes (`Services/Notes/LembreteHandler`).
- Testes de saturação da Wave 26 (`Wave26WhatsappSaturationTest` e irmãs — o D3 dessa suíte exige a menção "Wave 26" nesta porta), idempotência de webhook e isolamento Tier 0; rota admin sob `can:whatsapp.access`.
- Feedback do cliente em dois canais: `canal=whatsapp` (capturado no inbox) e `canal=web_form` (link público assinado, `php artisan feedback:link {biz}`, validade de 30 dias; a rota não tem auth — o global scope é no-op sem auth — então o `business_id` vem do HMAC da URL, nunca do input). Decisão de 2026-07-17 (`RUNBOOK-feedback-publico.md`): o canal público grava em `clients_feedbacks`, sem tabela nova; a ADR 0334 classificou a atrofia do órgão sensor (US-INFRA-002) e a entidade dedicada saiu depois em `Modules/VozDoCliente` (`voz_sinais`, 2026-07-28).

## Gaps
- Inbox omnichannel: só o eixo WhatsApp opera. `instagram`/`messenger`/`email_imap`/`email_smtp`/`mercadolivre` existem como tipo em `Channel::TYPES`, mas o `ChannelDriverFactory` lança `NotImplementedDriverException` e não há webhook — Fases 1–3 da ADR 0135 (Instagram · e-mail · Mercado Livre), backlog gated por sinal de cliente. ⚠️ Os ids US-WA-063/064/065 que a 0135 §Stories reserva pra essas fases já foram consumidos no SPEC por outras stories (063 Tags e 064 Contact/@lid, ambas `done`; 065 inexistente) — citar a fase, nunca o id.
- Canais `Suporte` e `Jana` (biz=1) sem inbound desde aproximadamente julho/2026; recovery não executado (CT 100 inalcançável em 2026-09-02) — plano na sessão `2026-09-02-whatsapp-incident-canais-biz1-mudos-whatsmeow` §7.
- Anexos automáticos (boleto/NFe/Pix) nas conversas — backlog (US-WA-038 · US-RB-044 v2; Pix marcado AUSENTE no CAPTERRA-INVENTARIO).

## Última mudança
Última mudança de capacidade: `dias_ativos_30d` nunca era calculado (PR #6293, 2026-08-26); depois só higiene cross-cutting (copy "Copiloto" #6344, fonte de design #6459, a11y #6465). Antes: trio charter/casos/teste da CaixaUnificada (#6190, 2026-08-24) e o canal público de feedback (#4413, 2026-07-17), que reusa dedup/relevância/status/dashboard.

## Proveniência (destilado de)

- audit `requisitos/Whatsapp/AUDIT-LOG.md` — AUDIT-LOG.md
- audit `requisitos/Whatsapp/AUDITORIA-MIDIA-OUTBOUND-2026-05-28.md` — AUDITORIA-MIDIA-OUTBOUND-2026-05-28.md
- audit `requisitos/Whatsapp/AUDITORIA-REALTIME-WEBHOOK-UI-2026-05-28.md` — AUDITORIA-REALTIME-WEBHOOK-UI-2026-05-28.md
- audit `requisitos/Whatsapp/AUDITORIA-WEBHOOK-SYNC-HANDLERS-2026-05-14.md` — AUDITORIA-WEBHOOK-SYNC-HANDLERS-2026-05-14.md
- audit `requisitos/Whatsapp/AUDITORIA-WHATSMEOW-DAEMON-2026-05-28.md` — AUDITORIA-WHATSMEOW-DAEMON-2026-05-28.md
- audit `requisitos/Whatsapp/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/Whatsapp/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-09-02-whatsapp-incident-canais-biz1-mudos-whatsmeow.md` (2026-09-02) — 2026-09-02-whatsapp-incident-canais-biz1-mudos-whatsmeow.md
- handoff `handoffs/2026-08-13-0750-smoke-real-pages-no-modulo-dono-a-divida-paga.md` (2026-08-13) — 2026-08-13-0750-smoke-real-pages-no-modulo-dono-a-divida-paga.md
- handoff `handoffs/2026-08-07-1530-quarentena-era-sqlite-piloto-lane-whatsapp.md` (2026-08-07) — 2026-08-07-1530-quarentena-era-sqlite-piloto-lane-whatsapp.md
- session `sessions/2026-07-26-fronteira-modulo-e-obra-parada.md` (2026-07-26) — 2026-07-26-fronteira-modulo-e-obra-parada.md
