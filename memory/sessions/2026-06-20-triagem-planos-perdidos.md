---
date: "2026-06-20"
topic: "Triagem das 155 iniciativas-plano perdidas (estado real vs git + sinal ADR-0105). Resultado: 19 descartar (já feito/obsoleto) · 58 park-wish (desejo sem sinal) · 34 backlog-now (sinal qualificado) · 44 decide-W (estratégico/ambíguo). A cauda 'perdida' é majoritariamente wish/done; só 34 são acionáveis com sinal."
authors: [C]
related_adrs: ["0105-cliente-como-sinal-guiar-sem-mandar", "0070-jira-style-task-management-current-md-removed", "0256-knowledge-survival-meia-vida-catraca-sentinela"]
prs: []
---

# Triagem das 155 perdidas — decisão-ready

> Continuação de [reclassificacao-planos-completa](2026-06-20-reclassificacao-planos-completa.md). 20 agentes verificaram estado real (git) + sinal ADR-0105 de cada perdida. Workflow `triar-planos-perdidos` (run wf_1bfbefba). Disposição = recomendação; admissão a backlog é decisão [W].

## Placar

| Disposição | Qtd | O que fazer |
|---|--:|---|
| **discard-done** | 15 | já shipou → fechar, sem ação |
| **discard-obsolete** | 4 | superado/cancelado → fechar |
| **park-wish** | 58 | desejo sem sinal (ADR 0105) → arquivar como wish, NÃO US ativa |
| **backlog-now** | 34 | sinal qualificado → candidato a task (você aprova) |
| **decide-w** | 44 | estratégico/ambíguo → sua deliberação |

Sinal: 75 feature-wish · 24 cliente-paga-reporta · 22 métrica-drift · 20 p0-safety · 14 incerto. Estado: 81 not-started · 53 partial · 14 shipped · 4 unknown · 3 obsolete.

## BACKLOG-NOW (34) — sinal qualificado, acionável

### 🔴 P0 / segurança / prod-quebrado (10)
- **adr0270-cockpit-mock-kill** — `Jana/Cockpit.tsx:705` `startMockStream` ativa em rota live `/ia/dashboard` (gap P0 handoff 2026-06-11).
- **boleto-ocr-gpt4o-vision-prod** — `BoletoOcrService` em prod falha 403 silencioso (acesso OpenAI); Eliana usa, sem alternativa.
- **content-reconciler-safe-heal** — `ContentReconciler healable=false` por delete global sem business_id (risco corrupção Tier-0).
- **kb-acl-aware-rag** — P0 bloqueante: sem `kb_node_visibility` + ACL row-level, não libera RAG ao time MCP (risco LGPD).
- **knowledge-drift-rename-propagation** — 112 PHP em Modules/ ainda citam `Copiloto` + 27 `MemCofre` (renames não propagados).
- **hitl-audit-card-ui-copiloto** — view cliente `/copiloto/decisoes/{id}/revisao` (LGPD Art.20) ausente.
- **timezone-format-date-migracao** — bug histórico `transaction_date` afeta ROTA LIVRE (cliente real).
- **gates-onda2-item7-fusao-cor** — 4 gates de cor ainda separados; fusão aprovada nunca executada.
- **whatsapp-channel-reliability-roadmap / -consolidar** — 7/10 gaps fechados (incl. probe #3055 desta sessão); restam #6 nonce, #9 failover, #10 circuit-breaker.
- **whatsapp-channel-reliability-slo-observabilidade** — restam OTel spans (#8) + dashboard saúde (#10).

### 💰 Cliente paga + reporta (14)
- **recurring-billing-gateway-ativacao** — 109 assinaturas ativas (36 C6 + 51 Inter + 22 Cora) com gateway=NULL, cobranças dormentes. **Receita parada.**
- **roadmap-2-semanas-reconquista-martinho** — Martinho biz=164 (pagante): 4 P0 (B0 recovery 4.378 produtos, NFSe 500, final_total=0, ADR 0194).
- **nfe-foundation-us-nfe-040** — Martinho paga pacote fiscal R$850/mês; foundation NFe (16h débito) trava evoluções fiscais.
- **migracao-firebird-boletos-contratos** — 59 boletos órfãos + 3.372 fin_titulos com origem_id bug (handoff 2026-06-08).
- **fsm-rollout-vendas-legadas-biz1 / us-sell-036-fsm-bulk-start** — 14 vendas legadas biz=1 sem FSM; canary antes de Martinho replicar.
- **pricing-3-ajustes-urgentes** — recalibração ticket (Martinho compra ativa).
- **sells-nfce-inline-create** — Larissa biz=4 vende e fatura; botão 'Salvar e emitir NFC-e' (VdNfeEmitModal existe).
- **sells-v2-paridade-blade-biz4** — guard biz=4 já removido; restam configure-search/quick-add/preço-diferenciado.
- **vestuario-paridade-linx-2q** — G1 etiqueta feito; G3-G5 (estação/liquidação/fidelidade) abertos.
- **voc-omnichannel-gaps / voz-cliente-5-ondas** — customer_memory shipou; ondas 2-5 (Customer 360 sidebar, inferência IA) abertas.
- **paymentgateway-onda-5-dogfooding** — código feito; pendências humano-limitadas (smoke biz=1, canary Larissa).

### 📉 Métrica detecta drift (10)
adr0270-ciclo-vida-f1-f5 (F5 archive faltando) · css-hex-drift-fase2-158 (61 hex restantes) · design-request-ledger-mcp (processamento incremental) · governance-sprint-2-cleanup (pre-commit 3 blocos legados) · ia-os-onda2-endurecer (anchor-gate advisory→required) · manual-css-js-roadmap (CSS ~28k→20k) · screen-qa-dim16-sentinela (workflow ausente no CI) · sdd-gate-required (continue-on-error nos 3 steps) · sdd-kl-e2-e3 (27 renames classe A) · sdd-lane-d / sdd-sqlite-corruptors (237 corruptores burn-down).

## DECIDE-W (44) — sua deliberação (resumo)
Estratégicos/ambíguos: caixa-os-vinculada (C1/C2) · bi-temporal-adr0074 (ratificação + CI) · sdd-distiller-modulo-verdade (descomentar cron — gate CT100) · sdd-memoria-unificada / -uniao-keystone · ds-rollout-ledger-pr2682 (branch ~1671 arquivos fora de main) · gold-recuperacao (cliente fugiu p/ Mubisys) · larissa-validacao-sprint7 · legacy-migration-vargas-gold-extreme · ia-enable-tier0-prod / ia-champion-makers (custo/infra) · design-review-fase2 (custo) · roadmap-tecnico-12m (promover a ADR?) · srs-deprecacao-6-etapas · fiscal-sped-wave-10 · whatsapp-channel-access-cleanup-prod (rodar --fix em prod) · wa-anti-cross-contact recovery SQL · drop-views-legacy-jana · + 27 outros (lista completa no run).

## PARK-WISH (58) — desejo sem sinal (arquivar, não ativar)
ds-maturidade-6-ondas · ds-identidade-66-85 · kb-onda3/6 · kb-bench-v3 · repair-roadmap-3-fases · garantia-cross-vertical · api-docs-swagger · blog-editorial-30-posts · linkedin-outbound · programa-afiliados · canais-setoriais · modules-equipe-slack · top5-bancos-rest · itau-bolecode · santander-pix · banco-bb-pix · dam-nativo · network-effect-engine · mcp-server-vertical · atendimento-automatico-bot · wa-bot-policy-engine · sells-edit-parking-lot · sells-cowork-onda7 · pontowr2-fase4 · ux-blade-decommission · subagent-orchestration · tdad-lite · determinizacao-llm-judge · + ~30 outros (todos feature-wish sem cliente/métrica per ADR 0105).

## DISCARD (19) — fechar
**done (15):** customer-360-sidebar · deploy-sha-webhook-fix · f3-primitivos-layout · memory-index-regen · migracao-estoque-martinho · nfse-500-fix · oficina-whatsapp-aprovacao · oficinaauto-gap6-vrt · sdd-d4-d5 · sdd-us-gov-020 · sdd-without-global-scopes · visual-regression-harness · visual-regression-pixel-baselines · whatsapp-loggedout-fase-a · sdd-refutadores-d1.
**obsolete (4):** caixa-rest-api-driver · compras-blade-inertia (US-COM-002 cancelada) · migracao-legacy-agentes · adocao-claude-design-handoff-bundle (formato fantasma).

## Próximo
1. **Descartar os 19** — fechamento limpo (zero ação de código).
2. **Arquivar os 58 park-wish** — viram lista de desejos (ADR de wish), saem do radar de "ativo".
3. **34 backlog-now** → virar task MCP (`parent_plan`) — exige seu OK por item/lote (ADR 0105) + MCP online (hoje offline). Os 🔴 P0 (cockpit-mock, content-reconciler Tier-0, kb-acl LGPD, boleto-ocr) e 💰 receita parada (recurring-billing 109 assinaturas) são os de maior impacto.
4. **44 decide-w** → fila de deliberação [W].

> Run wf_1bfbefba (20 agentes, 1.1M tokens). Evidência por item no transcript.
