---
id: requisitos-jana-briefing
module: Jana
status: producao
updated_at: "2026-09-06"
distilled_at: "2026-09-06"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Jana (verdade destilada)

## Estado atual
Camada de IA do oimpresso: chat com memória persistente, brief diário, sugestões de metas e evals, sobre `laravel/ai` + Agents próprios (ADR 0035; Vizra rejeitada, ADR 0048). Em produção desde o CYCLE-01 (goal validado em prod, canon `why-oimpresso.md`); o estado corrente da qualidade das respostas é medido por `jana:ragas-real-eval` e vive em `governance/jana-ragas-real-baseline.json`. Não é BI nem dashboard genérico: é agente orientado a decisão. A área `/ia` tem as abas Painel, Conversa, Alertas, Ações, Memória e Plataforma (topologia em `ARCHITECTURE.md`, observabilidade em `OBSERVABILITY.md`, a tela nova em `RUNBOOK-plataforma.md`); a última (`/ia/superadmin/metas`; gate por `hasPermissionTo('jana.superadmin')`, porque `can()` é burlado pelo `Gate::before`) substituiu o Blade do superadmin em 2026-09-03 (#6609, delta #6627 que consertou as filhas `MetaPeriodo`/`MetaApuracao` fora do escopo — `UC-PLAT-03`); as outras views Blade do módulo seguem. Fronteiras da ADR 0366: Custos, Qualidade IA e Governança MCP foram para Governance; Roadmap e as migrations `mcp_*` para Forja — o código MCP (`Entities/Mcp`, servidor JSON-RPC, tools, `Services/Mcp`, comandos e `McpAuthMiddleware`) continua aqui, por isso `module:migrate Jana` não provisiona mais o schema que o código consome (mascarado em prod pelo `migrate --force` global do deploy). Permissões `jana.*` preservadas ao mudar dono de tela (ADR 0087). A lane `PHP / Pest (Jana · MySQL)` não está entre os contexts required — dono do fato: `governance/required-checks-baseline.json`. O item 4 da 0366 §D-C foi autorizado pela ADR 0378 (aceita, [W] 2026-08-13), que desenha a execução em ondas — a onda do schema já landou (migrations `mcp_*` em `Modules/Forja`, nenhuma na Jana); as Entities/Mcp e as tools seguem na Jana. As URLs antigas das telas devolvidas sobrevivem por 301. Module-grade (`governance/module-grades-baseline.json`) tem dono em git e não se restateia aqui; o gate foi demovido a advisory em 2026-06-30 (ADR 0314 D-1, #3466) — o que é required hoje é dito por `governance/required-checks-baseline.json`; errata que não volta: "85% das funcionalidades operacionais" era META dos audits de maio, não estado. Resultado negativo medido em 2026-08-27: estender `JanaViewsSemAndaimeTest` a `*.php` deu ~58 falsos-positivos em 60 hits — rejeitado.

## Capacidades
- Chat com memória persistente (`MemoriaContrato` + `MeilisearchDriver` com time-decay; `NullMemoriaDriver` em dev).
- Agents em `Modules/Jana/Ai/Agents/` (`ChatCopilotoAgent`, `ClarificadorAgent`, `SugestoesMetasAgent`, `BriefDiarioAgent`, entre outros) e comandos artisan de operação, incluindo `jana:health-check` e o próprio `jana:distill-module-truth`.
- Brief diário (ADR 0091) e sugestões de metas por business; `HitlEscalationService` (`Services/TaskRegistry/`) transporta sentinela em decisão via task idempotente em `mcp_tasks`.
- Evals: RAGAS gate e canary em CI, recall-eval, telemetria Langfuse/OTel.
- Telas Inertia (Index, Chat, Memoria, Alertas, Acoes, Pro, Plataforma); a paridade contra a âncora tem dono em `<Tela>-visual-comparison.md` e nas ondas #6655/#6662/#6664; a Plataforma diz o que não existe (tabelas de meta vazias em produção — medido em 2026-08-09; a tela abre sem linhas).
- Servidor MCP `mcp.oimpresso.com` alimentado pelo corpus `memory/` (ADR 0053).

- Gate Tier 0 explícito no `PeriodosController` (`assertMetaDoTenant`): a meta pai é validada no tenant antes de tocar o filho — fechou um IDOR cross-tenant em 2026-07-17 (#4474, ADR 0093), porque o backstop `ScopeByBusinessViaParent` só cobre SELECT.

## Gaps
- `context_recall` abaixo do alvo (US-COPI-133, p0 `_pendente_`); a janela centrada no match subiu o recall medido no run de 2026-09-05 (#6801, CT 100), com o piso de 0,36 armado (US-COPI-136, done); o estado corrente da série do gate vive em `governance/jana-ragas-real-baseline.json` (re-cura de 2026-09-06 registra fail/skipped em agosto e mantém os pisos).
- Eval online em traces reais (US-COPI-137, `doing`) e heartbeat Langfuse no `HealthCheckCommand` (US-COPI-138) em aberto; sem cadeia de fallback de provider (US-COPI-135); ratio negócio÷governança do fluxo de trabalho com `alarme: true` medido à mão — o alarme existe e nunca dispara sozinho (US-COPI-139); hybrid medido no spike de 2026-07-12 e rejeitado.
- Painel com botões mudos — `Ouvir áudio` (o único que ainda promete data: "em breve — TTS V2") e outros quatro, `Exportar` e os 3 chips do rodapé do brief (`Disparar régua WhatsApp pros {n} atrasados`, `Ver top devedores`, `Investigar queda ticket médio`), que apenas não fazem nada ao clicar — registrados como dívida forward-only (`UC-JPAIN-16`); decisão [W] aberta.
- Metas (`MetasController::index()`, grupo `can:jana.access`): sem filtro, sem permissão por ação e retorno Blade nas telas de metas — verificado em 2026-08-27; o Blade→JSON foi fechado só na tela de metas do superadmin.
- Agregar metas de clientes é Non-Goal declarado (`UC-PLAT-04`); o que a plataforma quer medir é decisão de [W]. Resíduo: a 2ª porta do gate (`user_type` superadmin) é inalcançável no grupo `/ia` — medido em 2026-08-31: nenhum usuário com esse `user_type`, e quem alcança a tela entra pela permissão real; removê-la é decisão [W].
- Flags que nascem `false` em `Config/config.php`: `COPILOTO_HYDE_ENABLED`, `COPILOTO_NEGATIVE_CACHE_ENABLED`, `JANA_CLARIFY_ENABLED` (ADR 0245) e `JANA_RETENTION_ENABLED`. `jana:retention-purge` segue agendado, porém inerte com a última em `false`. Ligá-lo iteraria biz=4 (roda sem `--business`), e a política [W] de 2026-07-27 é não apagar PII — desligar o agendamento de vez é decisão [W].
- Decisões abertas de [W]: trajetória projetada, alertas por WhatsApp, multi-idioma, cache do retrato, guardrails.

## Última mudança
Ondas de paridade de design das telas da Jana + abas Alertas e Ações (2026-09-03, #6607/#6608/#6655/#6660/#6662/#6664), a aba Plataforma em Inertia (#6609, com o delta #6627) e a janela centrada no match do recall (#6801, 2026-09-05).

## Proveniência (destilado de)

- audit `requisitos/Jana/AUDIT-GAPS-2026-08-10.md` — AUDIT-GAPS-2026-08-10.md
- audit `requisitos/Jana/AUDIT-SENIOR-2026-05-25.md` — AUDIT-SENIOR-2026-05-25.md
- audit `requisitos/Jana/AUDITORIA-IA-OS-2026-06-06.md` — AUDITORIA-IA-OS-2026-06-06.md
- audit `requisitos/Jana/AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md` — AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md
- audit `requisitos/Jana/AUDITORIA-MODO-C-2026-05-09.md` — AUDITORIA-MODO-C-2026-05-09.md
- audit `requisitos/Jana/AUDITORIA-SESSION-HANDOFF-2026-05-13.md` — AUDITORIA-SESSION-HANDOFF-2026-05-13.md
- audit `requisitos/Jana/AUDITORIA-design-as-code-token-driven-2026-06-22.md` — AUDITORIA-design-as-code-token-driven-2026-06-22.md
- audit `requisitos/Jana/AUDITORIA-fidelidade-anti-drift-codegen-2026-06-22.md` — AUDITORIA-fidelidade-anti-drift-codegen-2026-06-22.md
- audit `requisitos/Jana/AUDITORIA-reconciliacao-tripla-analise-por-setor-2026-06-22.md` — AUDITORIA-reconciliacao-tripla-analise-por-setor-2026-06-22.md
- session `sessions/2026-09-04-ragas-agosto-lead-refutado-juiz-mudo.md` (2026-09-04) — 2026-09-04-ragas-agosto-lead-refutado-juiz-mudo.md
- session `sessions/2026-09-03-ct100-breaker-sync-memory-e-veredito-unico.md` (2026-09-03) — 2026-09-03-ct100-breaker-sync-memory-e-veredito-unico.md
- session `sessions/2026-09-03-watchdog-g6-tres-achados-ragas.md` (2026-09-03) — 2026-09-03-watchdog-g6-tres-achados-ragas.md
- handoff `handoffs/2026-09-03-0920-ct100-fora-breaker-e-veredito-unico.md` (2026-09-03) — 2026-09-03-0920-ct100-fora-breaker-e-veredito-unico.md
- handoff `handoffs/2026-09-03-1054-jana-plataforma-duas-sessoes-um-alvo-delta-6627.md` (2026-09-03) — 2026-09-03-1054-jana-plataforma-duas-sessoes-um-alvo-delta-6627.md
- session `sessions/2026-09-02-jana-abas-paridade-3-prs.md` (2026-09-02) — 2026-09-02-jana-abas-paridade-3-prs.md
- session `sessions/2026-09-02-ragas-real-colapso-diagnostico-bloqueado-ct100.md` (2026-09-02) — 2026-09-02-ragas-real-colapso-diagnostico-bloqueado-ct100.md
- handoff `handoffs/2026-09-02-2140-jana-abas-alertas-acoes-plataforma.md` (2026-09-02) — 2026-09-02-2140-jana-abas-alertas-acoes-plataforma.md
- session `sessions/2026-08-31-jana-p0-vazamento-e-d0-identidade-view.md` (2026-08-31) — 2026-08-31-jana-p0-vazamento-e-d0-identidade-view.md
- handoff `handoffs/2026-08-31-1054-jana-p0-tier0-faxina-e-d0-identidade.md` (2026-08-31) — 2026-08-31-1054-jana-p0-tier0-faxina-e-d0-identidade.md
- session `sessions/2026-08-18-visreg-manifesto-cobertura-vs-escalonamento.md` (2026-08-18) — 2026-08-18-visreg-manifesto-cobertura-vs-escalonamento.md
- session `sessions/2026-08-17-jana-chat-gaps-do-card-tres-ja-existiam.md` (2026-08-17) — 2026-08-17-jana-chat-gaps-do-card-tres-ja-existiam.md
- handoff `handoffs/2026-08-17-1810-jana-instrumentos-que-calam-e-o-outage.md` (2026-08-17) — 2026-08-17-1810-jana-instrumentos-que-calam-e-o-outage.md
- session `sessions/2026-08-15-espelho-jana-baixar-nao-e-converter.md` (2026-08-15) — 2026-08-15-espelho-jana-baixar-nao-e-converter.md
- handoff `handoffs/2026-08-15-2035-jana-espelho-defasado-ciclo-e-9-prs.md` (2026-08-15) — 2026-08-15-2035-jana-espelho-defasado-ciclo-e-9-prs.md
- session `sessions/2026-08-14-censo-redacao-brl-em-codigo.md` (2026-08-14) — 2026-08-14-censo-redacao-brl-em-codigo.md
- session `sessions/2026-08-13-ancora-jana-consertada-e-o-p2-que-nao-era.md` (2026-08-13) — 2026-08-13-ancora-jana-consertada-e-o-p2-que-nao-era.md
- session `sessions/2026-08-13-jana-dark-ancora-defeituosa.md` (2026-08-13) — 2026-08-13-jana-dark-ancora-defeituosa.md
- handoff `handoffs/2026-08-13-1330-jana-dark-e-a-ancora-que-mentia.md` (2026-08-13) — 2026-08-13-1330-jana-dark-e-a-ancora-que-mentia.md
- handoff `handoffs/2026-08-13-1520-ancora-jana-consertada-p2-revertido.md` (2026-08-13) — 2026-08-13-1520-ancora-jana-consertada-p2-revertido.md
- session `sessions/2026-08-12-arte-shared-kernel-laravel.md` (2026-08-12) — 2026-08-12-arte-shared-kernel-laravel.md
- session `sessions/2026-08-12-refutacao-lote-pr5675.md` (2026-08-12) — 2026-08-12-refutacao-lote-pr5675.md
- session `sessions/2026-08-11-prototipo-jana-no-git-e-a-defesa-que-era-a-causa.md` (2026-08-11) — 2026-08-11-prototipo-jana-no-git-e-a-defesa-que-era-a-causa.md
- handoff `handoffs/2026-08-11-1245-prototipo-jana-no-git-e-espelho-com-live-only.md` (2026-08-11) — 2026-08-11-1245-prototipo-jana-no-git-e-espelho-com-live-only.md
- session `sessions/2026-08-10-jana-modulo-inteiro-e-o-comentario-que-virou-lei.md` (2026-08-10) — 2026-08-10-jana-modulo-inteiro-e-o-comentario-que-virou-lei.md
- handoff `handoffs/2026-08-10-1330-jana-lida-inteira-56-gaps-e-o-eixo-que-faltava.md` (2026-08-10) — 2026-08-10-1330-jana-lida-inteira-56-gaps-e-o-eixo-que-faltava.md
- handoff `handoffs/2026-08-09-2300-jana-o-retrato-ja-existia-e-zero-metas.md` (2026-08-09) — 2026-08-09-2300-jana-o-retrato-ja-existia-e-zero-metas.md
- session `sessions/2026-08-08-fatia-d-jana-memoria-metodo.md` (2026-08-08) — 2026-08-08-fatia-d-jana-memoria-metodo.md
- handoff `handoffs/2026-08-08-1721-jana-fatia-a-barra-unica-pageheader.md` (2026-08-08) — 2026-08-08-1721-jana-fatia-a-barra-unica-pageheader.md
- handoff `handoffs/2026-08-08-1936-jana-memoria-fatia-d-lgpd-motivo.md` (2026-08-08) — 2026-08-08-1936-jana-memoria-fatia-d-lgpd-motivo.md
- session `sessions/2026-08-07-jana-fusao-onda1.md` (2026-08-07) — 2026-08-07-jana-fusao-onda1.md
