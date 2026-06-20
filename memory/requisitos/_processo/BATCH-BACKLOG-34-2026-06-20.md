---
title: "Batch backlog — 34 perdidas com sinal qualificado (pronto pra tasks-create)"
owner: W
created: "2026-06-20"
status: "aguardando-aprovacao-W"
related_adrs: ["0105-cliente-como-sinal-guiar-sem-mandar", "0070-jira-style-task-management-current-md-removed"]
source: "triagem 2026-06-20 (run wf_1bfbefba)"
---

# Batch backlog — 34 iniciativas perdidas com sinal qualificado

> Saída da [triagem das 155 perdidas](../../sessions/2026-06-20-triagem-planos-perdidos.md). Estas 34 passaram no filtro ADR-0105 (cliente paga+reporta · métrica em drift · P0-segurança). **NÃO foram criadas** — MCP offline + admissão a backlog é decisão [W]. Quando aprovar, virar `tasks-create` com `parent_plan=<slug>` + prioridade abaixo. Cada uma já tem evidência verificada em git no doc-fonte.

## P0 — segurança / LGPD / prod-quebrado (10)

| # | subject (imperativo) | slug / parent_plan | evidência-chave |
|---|---|---|---|
| 1 | Remover `startMockStream` da rota live `/ia/dashboard` (Cockpit responde mock) | adr0270-cockpit-mock-kill | `Jana/Cockpit.tsx:705-780`; needs fonte streaming real (Jana chat) |
| 2 | Reabilitar acesso OpenAI gpt-4o do BoletoOcrService (403 silencioso em prod) | boleto-ocr-gpt4o-vision-prod | `BoletoOcrService.php` prod; Eliana usa; **ação no dashboard OpenAI** (não-código) |
| 3 | Escopar delete do ContentReconciler por business_id (hoje `healable=false`, Tier-0) | content-reconciler-safe-heal | comment "delete global sem business_id — Tier-0-inseguro (ADR 0093)" |
| 4 | Adicionar `kb_node_visibility` + filtro ACL pre-retrieval no KbRagService | kb-acl-aware-rag | P0 LGPD; bloqueia RAG pro time MCP (Agent D 2026-05-15) |
| 5 | Propagar renames Copiloto→Jana / MemCofre→SRS nos ~112 PHP em Modules/ | knowledge-drift-rename-propagation | git grep 112 PHP "Copiloto" + 27 "MemCofre" (⚠ confirmar 1º se módulo ainda é Copiloto no código) |
| 6 | Criar view cliente `/copiloto/decisoes/{id}/revisao` (LGPD Art.20) | hitl-audit-card-ui-copiloto | UI admin existe; view cliente-final ausente |
| 7 | Migrar dados históricos `transaction_date` (timezone) — afeta ROTA LIVRE | timezone-format-date-migracao | bug preservado ADR 0066; migration nunca rodada |
| 8 | Fundir 4 gates de cor em `color-canon-gate.yml` (item 7 onda 2) | gates-onda2-item7-fusao-cor | 4 .yml separados ainda; recipe no handoff 2026-06-11-0930 |
| 9 | Fechar gaps #6/#9/#10 do channel-reliability whatsmeow (nonce·failover·circuit-breaker) | whatsapp-channel-reliability-roadmap | 7/10 fechados (probe #3055); #6 precisa design de dedup-key |
| 10 | Adicionar OTel spans do ciclo de sessão + dashboard saúde de canal (#8/#10) | whatsapp-channel-reliability-slo | ADR 0288 fase 1 feita (PR #3005) |

## P1 — cliente paga + reporta (14)

| # | subject | slug / parent_plan | sinal |
|---|---|---|---|
| 11 | Ativar gateway nas **109 assinaturas** com gateway=NULL (cobranças dormentes) | recurring-billing-gateway-ativacao | 36 C6 + 51 Inter + 22 Cora — **receita parada** |
| 12 | Destravar 4 P0 Martinho biz=164 (B0 recovery 4.378 produtos · NFSe 500 · final_total=0 · ADR 0194) | roadmap-2-semanas-reconquista-martinho | Martinho pagante |
| 13 | Foundation NFe US-NFE-040 (migrations/models/composer) | nfe-foundation-us-nfe-040 | Martinho R$850/mês fiscal |
| 14 | Reparar 59 boletos órfãos + 3.372 fin_titulos com origem_id bug (Firebird) | migracao-firebird-boletos-contratos | handoff 2026-06-08 |
| 15 | Rodar canary 7d do FSM bulk-start nas 14 vendas legadas biz=1 | us-sell-036-fsm-bulk-start | comando existe (ADR 0143); 14 vendas current_stage_id NULL |
| 16 | Aplicar recalibração de pricing (setup/trial/anual) | pricing-3-ajustes-urgentes | Martinho compra ativa |
| 17 | Adicionar botão "Salvar e emitir NFC-e" no Sells/Create (reusa VdNfeEmitModal) | sells-nfce-inline-create | Larissa biz=4 vende+fatura |
| 18 | Fechar paridade Sells V2 vs Blade (configure-search · quick-add · preço-diferenciado) | sells-v2-paridade-blade-biz4 | Larissa; guard biz=4 já removido |
| 19 | Fechar G3-G5 Vestuário (estação · liquidação · fidelidade) | vestuario-paridade-linx-2q | G1 etiqueta feito |
| 20 | Customer 360 sidebar + inferência IA (ondas 2-5 voz-cliente) | voz-cliente-5-ondas | customer_memory shipou |
| 21 | VoC: ContactProfile acumulativo (Gap#3) + auto-tag IA + dashboard (Gap#4) | voc-omnichannel-gaps | whatsapp_consent + customer_memory shipparam |
| 22 | Executar smokes humano-limitados PaymentGateway Onda 5 (biz=1 + canary Larissa) | paymentgateway-onda-5-dogfooding | código feito (PR #1148) |

## P2 — métrica detecta drift (10)

content-only / governança / DS — sem cliente direto, mas métrica viva sinaliza:
- **adr0270-ciclo-vida-f1-f5** (F5 archive faltando) · **css-hex-drift-fase2** (61 hex restantes) · **design-request-ledger-mcp** · **governance-sprint-2-cleanup** (pre-commit 3 blocos) · **ia-os-onda2-endurecer** (anchor-gate→required) · **manual-css-js-roadmap** (CSS 28k→20k) · **screen-qa-dim16-sentinela** (workflow ausente CI) · **sdd-gate-required** (continue-on-error) · **sdd-kl-e2-e3** (27 renames classe A) · **sdd-sqlite-corruptors-burn-down** (237 corruptores).

## Como criar (quando MCP voltar)

```
# pseudo — 1 task por linha, parent_plan = slug
tasks-create subject:"<subject>" priority:<P0|P1|P2> parent_plan:"<slug>" \
  description:"<evidência + DoD>" labels:[plano-perdido,backlog-2026-06-20]
```

> Os 19 discard (já-feito/obsoleto), 58 park-wish (desejo sem sinal) e 44 decide-W (deliberação) **não** entram aqui — ver [triagem](../../sessions/2026-06-20-triagem-planos-perdidos.md).
